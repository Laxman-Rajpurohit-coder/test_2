<?php

namespace Modules\Analytics\Services;

use App\Models\BotTrigger;
use App\Models\Conversation;
use App\Models\Message;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Aggregates operational metrics for a date range and optional tenant in the specified timezone.
     *
     * @param string|null $dateFrom The start date, defaulting to 30 days before the current date.
     * @param string|null $dateTo The end date, defaulting to the current date.
     * @param string|null $timezone The timezone used for date boundaries and time-based aggregations.
     * @param int|null $tenantId The tenant to scope metrics to, or null for the active tenant scope.
     * @return array Aggregated totals, trends, status and message-type breakdowns, busiest hours,
     *               average first-response time, date-range information, and recent messages.
     */
    public function getOverviewMetrics(?string $dateFrom = null, ?string $dateTo = null, ?string $timezone = 'UTC', ?int $tenantId = null): array
    {
        // 1. Timezone Validation Guard
        $timezone = ($timezone && in_array($timezone, DateTimeZone::listIdentifiers())) ? $timezone : 'UTC';
        
        // Default range: Last 30 days
        $dateFrom = $dateFrom ?: now($timezone)->subDays(30)->toDateString();
        $dateTo = $dateTo ?: now($timezone)->toDateString();

        // 2. Timezone-Aware UTC Boundary Conversion
        $startUtc = Carbon::createFromFormat('Y-m-d H:i:s', $dateFrom . ' 00:00:00', $timezone)
            ->setTimezone('UTC')
            ->toDateTimeString();

        $endUtc = Carbon::createFromFormat('Y-m-d H:i:s', $dateTo . ' 23:59:59', $timezone)
            ->setTimezone('UTC')
            ->toDateTimeString();

        // Helper to apply tenant scoping manually if an explicit tenantId is provided, bypassing the session scope
        $applyTenantScope = function ($query) use ($tenantId) {
            if ($tenantId !== null) {
                return $query->withoutGlobalScope('tenant_isolation')->where($query->getModel()->getTable() . '.tenant_id', $tenantId);
            }
            return $query;
        };

        // Base Eloquent Query Builder for messages inside date range
        $messagesQuery = $applyTenantScope(Message::query())
            ->whereBetween('created_at', [$startUtc, $endUtc]);

        $totalConversations = $applyTenantScope(Conversation::query())
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        // Active bot trigger rules count
        $activeTriggersCount = $applyTenantScope(BotTrigger::query())->where('is_active', true)->count();

        // Inbound vs Outbound counts
        $inboundMessages = (clone $messagesQuery)->where('direction', 'inbound')->count();
        $outboundMessages = (clone $messagesQuery)->where('direction', 'outbound')->count();

        // Delivery Status breakdown within range
        $statusCounts = (clone $messagesQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        // Type breakdown within range (safe for non-JSON content in both SQLite and Postgres)
        $typeCounts = [
            'text'     => 0,
            'image'    => 0,
            'audio'    => 0,
            'template' => 0,
        ];
        $allContents = (clone $messagesQuery)->select('content')->get();
        foreach ($allContents as $msgRow) {
            $rawContent = $msgRow->content;
            $type = 'text';
            if (is_array($rawContent)) {
                $type = $rawContent['type'] ?? 'text';
            } elseif (is_string($rawContent) && (str_starts_with(trim($rawContent), '{') || str_starts_with(trim($rawContent), '['))) {
                $decoded = json_decode($rawContent, true);
                if (is_array($decoded) && !empty($decoded['type'])) {
                    $type = $decoded['type'];
                }
            }
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }

        // 1. Messages per day trend line
        $dateSelect = $isSqlite 
            ? "DATE(created_at) as date" 
            : "DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as date";
            
        $bindingsDay = $isSqlite ? [] : [$timezone];

        $messagesPerDay = $applyTenantScope(Message::query())
            ->selectRaw(
                "$dateSelect, " .
                "COUNT(CASE WHEN direction = 'inbound' THEN 1 END) as inbound, " .
                "COUNT(CASE WHEN direction = 'outbound' THEN 1 END) as outbound, " .
                "COUNT(*) as total",
                $bindingsDay
            )
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->groupByRaw("1")
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn($row) => [
                'date'     => (string)$row->date,
                'inbound'  => (int)$row->inbound,
                'outbound' => (int)$row->outbound,
                'total'    => (int)$row->total,
            ])
            ->toArray();

        // 2. Busiest hours distribution
        $hourSelect = $isSqlite
            ? "CAST(strftime('%H', created_at) AS INTEGER) as hour"
            : "EXTRACT(HOUR FROM (created_at AT TIME ZONE 'UTC' AT TIME ZONE ?)) as hour";

        $bindingsHour = $isSqlite ? [] : [$timezone];

        $busiestHoursRaw = $applyTenantScope(Message::query())
            ->selectRaw("$hourSelect, COUNT(*) as count", $bindingsHour)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->groupByRaw("1")
            ->orderBy('hour', 'asc')
            ->pluck('count', 'hour')
            ->toArray();

        // Ensure all 24 hours (0..23) are represented for smooth chart rendering
        $busiestHours = [];
        for ($h = 0; $h < 24; $h++) {
            $busiestHours[] = [
                'hour'  => sprintf('%02d:00', $h),
                'count' => (int)($busiestHoursRaw[$h] ?? 0),
            ];
        }

        // 3. Average First Response Time (Minutes) within range
        $targetTenantId = $tenantId ?? app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        
        $avgMinutesSelect = $isSqlite 
            ? "AVG((julianday(fo.first_outbound_at) - julianday(fi.first_inbound_at)) * 24 * 60) as avg_minutes"
            : "AVG(EXTRACT(EPOCH FROM (fo.first_outbound_at - fi.first_inbound_at)) / 60) as avg_minutes";

        $avgFirstResponseMinutes = null;
        try {
            if ($targetTenantId !== null) {
                $avgResponse = DB::select("
                    WITH first_inbound AS (
                        SELECT conversation_id, MIN(created_at) as first_inbound_at
                        FROM messages
                        WHERE tenant_id = ? AND direction = 'inbound' AND created_at BETWEEN ? AND ?
                        GROUP BY conversation_id
                    ),
                    first_outbound AS (
                        SELECT m.conversation_id, MIN(m.created_at) as first_outbound_at
                        FROM messages m
                        JOIN first_inbound fi ON m.conversation_id = fi.conversation_id
                        WHERE m.tenant_id = ? AND m.direction = 'outbound' AND m.created_at >= fi.first_inbound_at
                        GROUP BY m.conversation_id
                    )
                    SELECT $avgMinutesSelect
                    FROM first_inbound fi
                    JOIN first_outbound fo ON fi.conversation_id = fo.conversation_id
                ", [$targetTenantId, $startUtc, $endUtc, $targetTenantId]);
            } else {
                $avgResponse = DB::select("
                    WITH first_inbound AS (
                        SELECT conversation_id, MIN(created_at) as first_inbound_at
                        FROM messages
                        WHERE direction = 'inbound' AND created_at BETWEEN ? AND ?
                        GROUP BY conversation_id
                    ),
                    first_outbound AS (
                        SELECT m.conversation_id, MIN(m.created_at) as first_outbound_at
                        FROM messages m
                        JOIN first_inbound fi ON m.conversation_id = fi.conversation_id
                        WHERE m.direction = 'outbound' AND m.created_at >= fi.first_inbound_at
                        GROUP BY m.conversation_id
                    )
                    SELECT $avgMinutesSelect
                    FROM first_inbound fi
                    JOIN first_outbound fo ON fi.conversation_id = fo.conversation_id
                ", [$startUtc, $endUtc]);
            }

            if (isset($avgResponse[0]->avg_minutes) && !is_null($avgResponse[0]->avg_minutes)) {
                $avgFirstResponseMinutes = round((float)$avgResponse[0]->avg_minutes, 1);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("AnalyticsService: Avg response calculation error: " . $e->getMessage());
            $avgFirstResponseMinutes = null;
        }

        // 4. Recent Activity Stream
        $recentMessages = $applyTenantScope(Message::with('conversation'))
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->map(function ($msg) {
                $content = is_string($msg->content) ? json_decode($msg->content, true) : $msg->content;
                return [
                    'id'              => $msg->id,
                    'customer_number' => $msg->conversation->customer_number ?? 'Unknown',
                    'direction'       => $msg->direction,
                    'status'          => $msg->status,
                    'text'            => $content['text'] ?? $content['caption'] ?? 'Media Message',
                    'time'            => $msg->created_at ? $msg->created_at->diffForHumans() : 'Just now',
                ];
            })
            ->toArray();

        return [
            'date_range' => [
                'from'     => $dateFrom,
                'to'       => $dateTo,
                'timezone' => $timezone,
            ],
            'totals' => [
                'conversations'     => $totalConversations,
                'inbound_messages'  => $inboundMessages,
                'outbound_messages' => $outboundMessages,
                'total_messages'    => $inboundMessages + $outboundMessages,
                'active_triggers'   => $activeTriggersCount,
            ],
            'messages_per_day' => $messagesPerDay,
            'delivery_status'  => [
                'queued'    => (int)($statusCounts['queued'] ?? 0),
                'sent'      => (int)($statusCounts['sent'] ?? 0),
                'delivered' => (int)($statusCounts['delivered'] ?? 0),
                'read'      => (int)($statusCounts['read'] ?? 0),
                'failed'    => (int)($statusCounts['failed'] ?? 0),
                'received'  => (int)($statusCounts['received'] ?? 0),
            ],
            'busiest_hours'              => $busiestHours,
            'avg_first_response_minutes' => $avgFirstResponseMinutes,
            'type_breakdown'             => [
                'text'     => (int)($typeCounts['text'] ?? 0),
                'image'    => (int)($typeCounts['image'] ?? 0),
                'audio'    => (int)($typeCounts['audio'] ?? 0),
                'template' => (int)($typeCounts['template'] ?? 0),
            ],
            'recent_messages' => $recentMessages,
        ];
    }
}
