<?php

namespace Modules\FlowBuilder\Services;

use InvalidArgumentException;

class FlowGraphValidatorService
{
    /** Supported Node Types Enum */
    public const ALLOWED_NODE_TYPES = [
        'message',
        'question',
        'condition',
        'api_call',
    ];

    /** Private IP & Metadata Ranges for SSRF Protection */
    protected const PRIVATE_IP_PATTERNS = [
        '/^127\./',                  // Loopback (127.0.0.0/8)
        '/^10\./',                   // Private (10.0.0.0/8)
        '/^172\.(1[6-9]|2[0-9]|3[01])\./', // Private (172.16.0.0/12)
        '/^192\.168\./',             // Private (192.168.0.0/16)
        '/^169\.254\./',             // Link-Local / AWS Metadata (169.254.0.0/16)
        '/^0\./',                    // Current network
    ];

    /** Blocked Internal Hostnames */
    protected const BLOCKED_HOSTNAMES = [
        'localhost',
        'redis',
        'pgsql',
        'postgres',
        'reverb',
        'laravel.test',
        'internal',
    ];

    /**
     * Validate full Flow Graph JSON payload on save / update.
     */
    public function validateGraph(array $graph): bool
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        if (!is_array($nodes) || !is_array($edges)) {
            throw new InvalidArgumentException("Flow graph must contain valid 'nodes' and 'edges' arrays.");
        }

        $nodeIds = [];

        foreach ($nodes as $node) {
            $nodeId = $node['id'] ?? null;
            $nodeType = $node['type'] ?? null;
            $data = $node['data'] ?? [];

            if (!$nodeId || !is_string($nodeId)) {
                throw new InvalidArgumentException("Every node must have a valid string 'id'.");
            }

            if (!$nodeType || !in_array($nodeType, self::ALLOWED_NODE_TYPES, true)) {
                throw new InvalidArgumentException("Invalid node type '{$nodeType}'. Allowed types: " . implode(', ', self::ALLOWED_NODE_TYPES));
            }

            $nodeIds[$nodeId] = true;

            // Type-specific Schema Validation & SSRF Guard
            $this->validateNodeContent($nodeType, $data);
        }

        // Validate Condition Rules target_node_id reference existent nodes
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'condition') {
                $rules = $node['data']['rules'] ?? [];
                foreach ($rules as $rule) {
                    $targetId = $rule['target_node_id'] ?? null;
                    if ($targetId && !isset($nodeIds[$targetId])) {
                        throw new InvalidArgumentException("Condition node '{$node['id']}' specifies non-existent target node ID '{$targetId}'.");
                    }
                }
            }
        }

        // Validate Graph Topology & Edge Links
        foreach ($edges as $edge) {
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if (!$source || !isset($nodeIds[$source])) {
                throw new InvalidArgumentException("Edge source '{$source}' does not reference an existing node.");
            }

            if (!$target || !isset($nodeIds[$target])) {
                throw new InvalidArgumentException("Edge target '{$target}' does not reference an existing node.");
            }
        }

        return true;
    }

    /**
     * Validate Node-Specific Payload Content & SSRF Guard for api_call nodes.
     */
    public function validateNodeContent(string $nodeType, array $data): void
    {
        switch ($nodeType) {
            case 'message':
                if (empty($data['text']) && empty($data['buttons'])) {
                    throw new InvalidArgumentException("Message node requires 'text' or 'buttons' payload.");
                }
                break;

            case 'question':
                if (empty($data['variable_name'])) {
                    throw new InvalidArgumentException("Question node requires 'variable_name' to store customer response.");
                }
                break;

            case 'condition':
                if (empty($data['rules']) || !is_array($data['rules'])) {
                    throw new InvalidArgumentException("Condition node requires a valid 'rules' array.");
                }
                break;

            case 'api_call':
                $url = $data['url'] ?? null;
                $method = strtoupper($data['method'] ?? 'GET');

                if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException("API call node requires a valid HTTP/HTTPS 'url'.");
                }

                if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    throw new InvalidArgumentException("Invalid HTTP method '{$method}' for API call node.");
                }

                // SSRF Security Guard Validation
                $this->assertSafeUrl($url);
                break;
        }
    }

    /**
     * SSRF Security Guard: Rejects internal IP ranges, metadata endpoints, and Docker hostnames.
     */
    public function assertSafeUrl(string $url): void
    {
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = strtolower($parsed['host'] ?? '');

        // 1. Enforce HTTP/HTTPS Scheme Only
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("SSRF Security Violation: Only 'http' and 'https' schemes are permitted.");
        }

        // 2. Reject Internal Hostnames
        if (in_array($host, self::BLOCKED_HOSTNAMES, true) || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException("SSRF Security Violation: Access to internal host '{$host}' is strictly forbidden.");
        }

        // 3. Resolve IP & Check Private/Loopback Ranges
        $ips = gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            throw new InvalidArgumentException("SSRF Security Violation: Unable to resolve hostname '{$host}'.");
        }

        foreach ($ips as $ip) {
            foreach (self::PRIVATE_IP_PATTERNS as $pattern) {
                if (preg_match($pattern, $ip)) {
                    throw new InvalidArgumentException("SSRF Security Violation: Target IP '{$ip}' belongs to a private/internal network range.");
                }
            }
        }
    }
}
