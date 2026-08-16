<?php

namespace Modules\FlowBuilder\Services;

use App\Events\MessageReceived;
use App\Jobs\SendMsg91Message;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Msg91PayloadBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\FlowBuilder\Models\Flow;
use Modules\FlowBuilder\Models\FlowSession;

class FlowExecutionService
{
    protected FlowGraphValidatorService $validator;

    public function __construct(FlowGraphValidatorService $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Start a new Flow Session for a customer.
     */
    public function startFlow(int $conversationId, string $customerNumber, string $flowId, int $tenantId): bool
    {
        return DB::transaction(function () use ($conversationId, $customerNumber, $flowId, $tenantId) {
            $flow = Flow::where('id', $flowId)->where('is_active', true)->first();

            if (!$flow || empty($flow->graph['nodes'])) {
                Log::warning("FlowExecutionService: Flow ID {$flowId} not found or has empty graph.");
                return false;
            }

            // Find Start / First Node in graph
            $startNode = $this->findStartNode($flow->graph['nodes']);
            if (!$startNode) {
                Log::warning("FlowExecutionService: Flow ID {$flowId} contains no valid start node.");
                return false;
            }

            // Initialize Flow Session (30-minute sliding TTL, 24-hour hard cap from created_at)
            $session = FlowSession::create([
                'id'              => Str::uuid()->toString(),
                'tenant_id'       => $tenantId,
                'conversation_id' => $conversationId,
                'customer_number' => $customerNumber,
                'flow_id'         => $flowId,
                'current_node_id' => $startNode['id'],
                'status'          => 'active',
                'variables'       => [],
                'expires_at'      => now()->addMinutes(30),
            ]);

            // Process initial start node
            return $this->processCurrentNode($session, $flow, $startNode, null, 0);
        });
    }

    /**
     * Advance an existing active Flow Session with customer input text/payload (Wrapped in DB::transaction).
     */
    public function advanceSession(FlowSession $session, string $inputText): bool
    {
        return DB::transaction(function () use ($session, $inputText) {
            // 1. Check 24-Hour Hard Cap (from session created_at)
            if ($session->created_at->addHours(24)->isPast()) {
                Log::info("FlowExecutionService: Session {$session->id} reached 24-hour hard cap. Expiring session.");
                $session->update(['status' => 'expired']);
                return false;
            }

            // 2. Check 30-Minute Sliding TTL
            if ($session->expires_at->isPast()) {
                Log::info("FlowExecutionService: Session {$session->id} sliding 30-minute TTL expired.");
                $session->update(['status' => 'expired']);
                return false;
            }

            $flow = $session->flow;
            if (!$flow || !$flow->is_active) {
                $session->update(['status' => 'completed']);
                return false;
            }

            $nodes = $flow->graph['nodes'] ?? [];
            $currentNode = $this->findNodeById($nodes, $session->current_node_id);

            // 3. Orphan Node Safety: If current_node_id no longer exists in republished graph
            if (!$currentNode) {
                Log::error("FlowExecutionService: Current node ID {$session->current_node_id} not found in flow graph. Sending friendly fallback and ending session {$session->id}.");
                $this->sendWhatsAppReply($session, "We encountered an issue processing your request. Please text 'menu' to start over.");
                $session->update(['status' => 'completed']);
                return true;
            }

            // Refresh 30-minute sliding TTL
            $session->update(['expires_at' => now()->addMinutes(30)]);

            // 4. Handle input collection if current node is a question node
            if (($currentNode['type'] ?? '') === 'question') {
                $varName = $currentNode['data']['variable_name'] ?? 'last_input';
                $vars = $session->variables ?? [];
                $vars[$varName] = trim($inputText);
                $session->update(['variables' => $vars]);
            }

            // Find next target node ID
            $nextNodeId = $this->resolveNextNodeId($flow->graph, $currentNode, $session, $inputText);

            if (!$nextNodeId) {
                Log::info("FlowExecutionService: Flow {$flow->id} reached end of path for session {$session->id}. Marking completed.");
                $session->update(['status' => 'completed']);
                return true;
            }

            $nextNode = $this->findNodeById($nodes, $nextNodeId);
            if (!$nextNode) {
                Log::warning("FlowExecutionService: Next node ID {$nextNodeId} not found in graph. Sending fallback and completing session.");
                $this->sendWhatsAppReply($session, "Thank you! Your flow session has completed.");
                $session->update(['status' => 'completed']);
                return true;
            }

            $session->update(['current_node_id' => $nextNode['id']]);
            return $this->processCurrentNode($session, $flow, $nextNode, $inputText, 0);
        });
    }

    /**
     * Process current node logic with MAX-DEPTH RECURSION GUARD ($depth max 10).
     */
    protected function processCurrentNode(FlowSession $session, Flow $flow, array $node, ?string $inputText, int $depth = 0): bool
    {
        // 🛡️ RECURSION & CYCLE GUARD: Limit max continuous node depth to 10 to prevent infinite graph loop crashes
        if ($depth > 10) {
            Log::error("FlowExecutionService: Max node execution depth (10) exceeded for session {$session->id}. Possible infinite cycle detected in flow {$flow->id}.");
            $session->update(['status' => 'completed']);
            return true;
        }

        $nodeType = $node['type'] ?? 'message';
        $data = $node['data'] ?? [];

        switch ($nodeType) {
            case 'message':
                $text = $this->interpolateVariables($data['text'] ?? '', $session->variables);
                $this->sendWhatsAppReply($session, $text);
                
                // Immediately advance to the next connected node (non-blocking)
                $nodes = $flow->graph['nodes'] ?? [];
                $nextNodeId = $this->resolveNextNodeId($flow->graph, $node, $session, null);
                if ($nextNodeId) {
                    $targetNode = $this->findNodeById($nodes, $nextNodeId);
                    if ($targetNode) {
                        $session->update(['current_node_id' => $targetNode['id']]);
                        return $this->processCurrentNode($session, $flow, $targetNode, null, $depth + 1);
                    }
                }
                $session->update(['status' => 'completed']);
                return true;

            case 'question':
                $promptText = $this->interpolateVariables($data['text'] ?? '', $session->variables);
                $this->sendWhatsAppReply($session, $promptText);
                // Stops execution here, waiting for customer's next incoming message
                return true;

            case 'condition':
                // Evaluate condition rules immediately and advance to target branch
                $nextNodeId = $this->evaluateConditionRules($data['rules'] ?? [], $session->variables);
                if ($nextNodeId) {
                    $nodes = $flow->graph['nodes'] ?? [];
                    $targetNode = $this->findNodeById($nodes, $nextNodeId);
                    if ($targetNode) {
                        $session->update(['current_node_id' => $targetNode['id']]);
                        return $this->processCurrentNode($session, $flow, $targetNode, $inputText, $depth + 1);
                    }
                }
                $session->update(['status' => 'completed']);
                return true;

            case 'api_call':
                // Execute Outbound REST API Call with REDIS ATOMIC IDEMPOTENCY GUARD, EXECUTION-TIME SSRF Guard & NO REDIRECTS
                $this->executeApiCallNode($session, $node);
                
                // Immediately advance to next node after API call completes
                $nodes = $flow->graph['nodes'] ?? [];
                $nextNodeId = $this->resolveNextNodeId($flow->graph, $node, $session, null);
                if ($nextNodeId) {
                    $targetNode = $this->findNodeById($nodes, $nextNodeId);
                    if ($targetNode) {
                        $session->update(['current_node_id' => $targetNode['id']]);
                        return $this->processCurrentNode($session, $flow, $targetNode, null, $depth + 1);
                    }
                }
                $session->update(['status' => 'completed']);
                return true;
        }

        return true;
    }

    /**
     * Execute API Call Node with ATOMIC REDIS IDEMPOTENCY GUARD, EXECUTION-TIME SSRF Guard & NO REDIRECTS.
     */
    public function executeApiCallNode(FlowSession $session, array $node): void
    {
        $nodeId = $node['id'] ?? 'api_node';
        $data = $node['data'] ?? $node;
        $rawUrl = $data['url'] ?? '';
        $method = strtoupper($data['method'] ?? 'GET');
        $targetVar = $data['response_variable'] ?? 'api_response';

        // 🛡️ ATOMIC REDIS IDEMPOTENCY GUARD:
        // Stored unconditionally in Redis OUTSIDE PostgreSQL transactions so it survives DB rollbacks.
        $redisKey = "api_executed:{$session->id}:{$nodeId}";
        try {
            $alreadyFired = !Cache::store('redis')->add($redisKey, true, 86400);
        } catch (\Throwable $e) {
            $alreadyFired = !Cache::add($redisKey, true, 86400);
        }

        if ($alreadyFired) {
            Log::info("FlowExecutionService: Skipping duplicate API call for node {$nodeId} on session {$session->id} (Atomic Redis Guard). Reusing stored variables.");
            return;
        }

        $vars = $session->variables ?? [];
        $url = $this->interpolateVariables($rawUrl, $vars);

        try {
            // 🛡️ SECURITY FIX 1: EXECUTION-TIME SSRF & DNS Rebinding Guard
            $this->validator->assertSafeUrl($url);

            Log::info("FlowExecutionService: Executing safe outbound API call to {$url} for session {$session->id}");

            // 🛡️ SECURITY FIX 2: NO REDIRECTS + HARD 5s TIMEOUT GUARD
            $response = Http::withOptions([
                'allow_redirects' => false, // Disables HTTP 301/302 SSRF redirect loops
                'connect_timeout' => 3,     // Fast 3s connect timeout
                'timeout'         => 5,     // Hard 5s execution timeout
            ])
            ->withHeaders($data['headers'] ?? [])
            ->send($method, $url, [
                'json' => $data['body'] ?? null,
            ]);

            $resultData = $response->json() ?? ['status' => $response->status(), 'body' => $response->body()];

            $vars[$targetVar] = $resultData;
            $session->update(['variables' => $vars]);

        } catch (\Throwable $e) {
            Log::error("FlowExecutionService: API call failed for session {$session->id} (URL: {$url}): " . $e->getMessage());
            $vars[$targetVar] = ['error' => true, 'message' => $e->getMessage()];
            $session->update(['variables' => $vars]);
        }
    }

    /**
     * Sends a text reply to the session's customer through WhatsApp.
     *
     * @param FlowSession $session The flow session containing the recipient and tenant context.
     * @param string $text The reply text.
     */
    protected function sendWhatsAppReply(FlowSession $session, string $text): void
    {
        $integratedNumber = app(\App\Services\TenantResolverService::class)->getIntegratedNumber($session->tenant_id);

        $contentStruct = [
            'type' => 'text',
            'text' => $text,
        ];

        $msg91Payload = \App\Services\Msg91PayloadBuilder::build(
            $session->customer_number,
            'text',
            ['text' => $text],
            $integratedNumber
        );

        \App\Services\OutboundReplyService::send(
            $session->conversation_id,
            $session->tenant_id,
            $contentStruct,
            $msg91Payload
        );
    }

    /**
     * Interpolate variables like {{session.variables.name}} or {{name}} into string template.
     */
    public function interpolateVariables(string $template, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];
            $key = str_replace('session.variables.', '', $key);

            // Nested key lookup (e.g. api_response.data.status)
            $parts = explode('.', $key);
            $val = $variables;
            foreach ($parts as $part) {
                if (is_array($val) && isset($val[$part])) {
                    $val = $val[$part];
                } else {
                    return ''; // Variable missing
                }
            }

            return is_string($val) || is_numeric($val) ? (string)$val : json_encode($val);
        }, $template);
    }

    protected function findStartNode(array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'message' || ($node['data']['is_start'] ?? false)) {
                return $node;
            }
        }
        return $nodes[0] ?? null;
    }

    protected function findNodeById(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? '') === $id) {
                return $node;
            }
        }
        return null;
    }

    protected function resolveNextNodeId(array $graph, array $currentNode, FlowSession $session, ?string $inputText): ?string
    {
        $edges = $graph['edges'] ?? [];
        foreach ($edges as $edge) {
            if (($edge['source'] ?? '') === $currentNode['id']) {
                return $edge['target'] ?? null;
            }
        }
        return null;
    }

    protected function evaluateConditionRules(array $rules, array $variables): ?string
    {
        foreach ($rules as $rule) {
            $varName = $rule['variable'] ?? '';
            $operator = $rule['operator'] ?? 'equals';
            $targetValue = $rule['value'] ?? '';
            $targetNodeId = $rule['target_node_id'] ?? null;

            $actualValue = $variables[$varName] ?? null;

            $matched = false;
            switch ($operator) {
                case 'equals':
                    $matched = strtolower((string)$actualValue) === strtolower((string)$targetValue);
                    break;
                case 'contains':
                    $matched = str_contains(strtolower((string)$actualValue), strtolower((string)$targetValue));
                    break;
                case 'greater_than':
                    $matched = (float)$actualValue > (float)$targetValue;
                    break;
            }

            if ($matched && $targetNodeId) {
                return $targetNodeId;
            }
        }

        return null;
    }
}
