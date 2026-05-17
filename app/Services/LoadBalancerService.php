<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LoadBalancerService
{
    // Define virtual servers and their respective capacity weights
    private const SERVERS = [
        ['id' => 'server-1', 'host' => '10.0.0.1', 'port' => 8001, 'weight' => 3, 'zone' => 'primary'],
        ['id' => 'server-2', 'host' => '10.0.0.2', 'port' => 8002, 'weight' => 2, 'zone' => 'primary'],
        ['id' => 'server-3', 'host' => '10.0.0.3', 'port' => 8003, 'weight' => 1, 'zone' => 'secondary'],
    ];

    private const COUNTER_KEY  = 'lb:round_robin_counter';
    private const METRICS_KEY  = 'lb:server_metrics';
    private const HEALTH_KEY   = 'lb:server_health';

    // =========================================================
    // Weighted Round-Robin Request Dispatching
    // =========================================================
    public function dispatch(string $requestId, string $endpoint): array
    {
        $weightedPool = $this->buildWeightedPool();

        // [DISTRIBUTED SYNCHRONIZATION POINT] Redis Atomic Increment
        // Utilizes atomic INCR operation in Redis to avoid concurrency race conditions
        // where multiple parallel PHP worker processes try to access and update the counter.
        $counter = Cache::getRedis()->incr(self::COUNTER_KEY);
        $index   = ($counter - 1) % count($weightedPool);
        $server  = $weightedPool[$index];

        // Failover logic if the designated server is unhealthy
        if (!$this->isHealthy($server['id'])) {
            $server = $this->failover($server['id'], $weightedPool, $index);
        }

        // Track metrics in Redis
        Cache::getRedis()->hincrby(self::METRICS_KEY, $server['id'], 1);

        $result = [
            'request_id' => $requestId,
            'endpoint'   => $endpoint,
            'routed_to'  => $server['id'],
            'host'       => "{$server['host']}:{$server['port']}",
            'zone'       => $server['zone'],
            'counter'    => $counter,
            'strategy'   => 'Weighted Round-Robin',
        ];

        Log::info("[LB] Request {$requestId} routed to {$server['id']} ({$server['host']}:{$server['port']})");

        return $result;
    }

    // =========================================================
    // Health Check Status Report
    // =========================================================
    public function healthCheck(): array
    {
        $report = [];
        foreach (self::SERVERS as $server) {
            $isHealthy = $this->isHealthy($server['id']);
            $requests  = (int) (Cache::getRedis()->hget(self::METRICS_KEY, $server['id']) ?? 0);

            $report[] = [
                'server'   => $server['id'],
                'host'     => "{$server['host']}:{$server['port']}",
                'zone'     => $server['zone'],
                'weight'   => $server['weight'],
                'healthy'  => $isHealthy ? 'Healthy' : 'Unhealthy',
                'requests' => $requests,
            ];
        }
        return $report;
    }

    // =========================================================
    // Simulation runner for N requests
    // =========================================================
    public function simulate(int $requestCount = 100): array
    {
        $distribution = [];
        foreach (self::SERVERS as $s) {
            $distribution[$s['id']] = 0;
        }

        for ($i = 1; $i <= $requestCount; $i++) {
            $result = $this->dispatch("REQ-{$i}", '/api/orders');
            $distribution[$result['routed_to']]++;
        }

        $summary = [];
        foreach ($distribution as $serverId => $count) {
            $summary[] = [
                'server'  => $serverId,
                'handled' => $count,
                'pct'     => round(($count / $requestCount) * 100, 1) . '%',
            ];
        }

        return [
            'total_requests' => $requestCount,
            'strategy'       => 'Weighted Round-Robin',
            'distribution'   => $summary,
        ];
    }

    private function buildWeightedPool(): array
    {
        $pool = [];
        foreach (self::SERVERS as $server) {
            for ($w = 0; $w < $server['weight']; $w++) {
                $pool[] = $server;
            }
        }
        return $pool;
    }

    private function isHealthy(string $serverId): bool
    {
        $status = Cache::getRedis()->hget(self::HEALTH_KEY, $serverId);
        return $status !== 'down';
    }

    private function failover(string $failedId, array $pool, int $startIndex): array
    {
        Log::warning("[LB] Server {$failedId} is down. Failover activated.");
        $size = count($pool);
        for ($i = 1; $i < $size; $i++) {
            $candidate = $pool[($startIndex + $i) % $size];
            if ($this->isHealthy($candidate['id'])) {
                return $candidate;
            }
        }
        return $pool[0];
    }

    public function markServerDown(string $serverId): void
    {
        Cache::getRedis()->hset(self::HEALTH_KEY, $serverId, 'down');
        Log::warning("[LB] Server {$serverId} marked as DOWN.");
    }

    public function markServerUp(string $serverId): void
    {
        Cache::getRedis()->hdel(self::HEALTH_KEY, $serverId);
        Log::info("[LB] Server {$serverId} marked as UP.");
    }
}
