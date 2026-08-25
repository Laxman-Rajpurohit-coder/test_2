<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\BillingSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class MessageLogController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        $billing = BillingSetting::getForTenant($tenantId);

        $query = Message::query()
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.tenant_id', $tenantId)
            ->select('messages.*', 'conversations.customer_number');

        // RBAC: Members only see logs for conversations with contacts assigned to them
        if (auth()->user() && method_exists(auth()->user(), 'isMember') && auth()->user()->isMember()) {
            $query->whereExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                  ->from('contacts')
                  ->whereColumn('contacts.phone_number', 'conversations.customer_number')
                  ->where('contacts.tenant_id', $tenantId)
                  ->where('contacts.assigned_user_id', auth()->id());
            });
        }

        if ($request->filled('status')) {
            $query->where('messages.status', $request->status);
        }
        
        if ($request->filled('direction')) {
            $query->where('messages.direction', $request->direction);
        }

        // Date & Time Range Filter
        if ($request->filled('start_date')) {
            try {
                $query->where('messages.created_at', '>=', \Carbon\Carbon::parse($request->start_date));
            } catch (\Throwable $e) {
                $query->where('messages.created_at', '>=', $request->start_date);
            }
        }

        if ($request->filled('end_date')) {
            try {
                $query->where('messages.created_at', '<=', \Carbon\Carbon::parse($request->end_date));
            } catch (\Throwable $e) {
                $query->where('messages.created_at', '<=', $request->end_date);
            }
        }

        // Content Type Filter (Robust PostgreSQL + MySQL JSON matching)
        if ($request->filled('type')) {
            $type = $request->type;
            if ($type === 'text') {
                $query->where(function($q) {
                    $q->where('messages.content', 'like', '%"type"%"text"%')
                      ->orWhere('messages.content', 'like', '%"type": "text"%')
                      ->orWhere('messages.content', 'not like', '%"type"%');
                });
            } else {
                $query->where(function($q) use ($type) {
                    $q->where('messages.content', 'like', '%"type":"' . $type . '"%')
                      ->orWhere('messages.content', 'like', '%"type": "' . $type . '"%')
                      ->orWhere('messages.content', 'like', '%"type"%"' . $type . '"%');
                });
            }
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('conversations.customer_number', 'like', "%{$search}%")
                  ->orWhere('messages.id', 'like', "%{$search}%");
            });
        }

        $perPageInput = $request->input('per_page', 50);
        $perPage = ($perPageInput === 'all' || $perPageInput === 'ALL') ? 5000 : min(max((int)$perPageInput, 10), 5000);

        $paginatedLogs = $query->orderBy('messages.created_at', 'desc')->paginate($perPage)->withQueryString();

        // Calculate billing cost and categorization for each message
        $unitDivider = $billing->rate_unit > 0 ? $billing->rate_unit : 1000;
        
        $paginatedLogs->getCollection()->transform(function ($msg) use ($billing, $unitDivider) {
            $category = 'Service Message';
            $unitRate = $billing->service_message_rate;

            $contentJson = $msg->content;
            if (is_string($contentJson)) {
                try {
                    $parsed = json_decode($contentJson, true);
                    if (is_array($parsed)) {
                        $type = $parsed['type'] ?? 'text';
                        if ($type === 'template') {
                            $tmplCategory = strtolower($parsed['category'] ?? $parsed['template_category'] ?? 'utility');
                            if (str_contains($tmplCategory, 'market')) {
                                $category = 'Template (Marketing)';
                                $unitRate = $billing->marketing_template_rate;
                            } elseif (str_contains($tmplCategory, 'auth')) {
                                $category = 'Template (Authentication)';
                                $unitRate = $billing->authentication_template_rate;
                            } else {
                                $category = 'Template (Utility)';
                                $unitRate = $billing->utility_template_rate;
                            }
                        } elseif (in_array($type, ['image', 'audio', 'video', 'document'])) {
                            $category = 'Media Message (' . ucfirst($type) . ')';
                            $unitRate = $billing->base_message_rate;
                        } elseif ($type === 'interactive') {
                            $category = 'Interactive Message';
                            $unitRate = $billing->service_message_rate;
                        } else {
                            $category = 'Text Message';
                            $unitRate = $billing->base_message_rate;
                        }
                    }
                } catch (\Throwable $e) {}
            }

            // Per message cost calculation (Rate / Unit)
            $perMessageCost = round($unitRate / $unitDivider, 4);

            $msg->billing_category = $category;
            $msg->unit_rate = $unitRate;
            $msg->estimated_cost = $perMessageCost;
            $msg->currency = $billing->currency;

            return $msg;
        });

        return Inertia::render('Logs/Index', [
            'logs' => $paginatedLogs,
            'filters' => $request->only(['status', 'direction', 'search', 'start_date', 'end_date', 'type', 'per_page']),
            'billing' => $billing,
        ]);
    }

    /**
     * Admin action to update billing rates.
     */
    public function updateBillingSettings(Request $request)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        $validated = $request->validate([
            'rate_unit' => 'required|integer|in:1000,10000',
            'currency' => 'required|string|max:10',
            'base_message_rate' => 'required|numeric|min:0',
            'utility_template_rate' => 'required|numeric|min:0',
            'marketing_template_rate' => 'required|numeric|min:0',
            'authentication_template_rate' => 'required|numeric|min:0',
            'service_message_rate' => 'required|numeric|min:0',
        ]);

        $setting = BillingSetting::getForTenant($tenantId);
        $setting->update($validated);

        return back()->with('success', 'Admin billing rates updated successfully.');
    }
}
