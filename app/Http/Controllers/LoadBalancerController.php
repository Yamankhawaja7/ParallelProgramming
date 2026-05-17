<?php

namespace App\Http\Controllers;

use App\Services\LoadBalancerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LoadBalancerController — Requirement #5 (Load Distribution)
 *
 * Exposes API endpoints to demonstrate request dispatching simulation and Health Checks.
 */
class LoadBalancerController extends Controller
{
    public function __construct(
        private readonly LoadBalancerService $lb
    ) {}

    // GET /api/lb/health — Query server status reports
    public function health(): JsonResponse
    {
        return response()->json([
            'status'  => 'ok',
            'servers' => $this->lb->healthCheck(),
        ]);
    }

    // POST /api/lb/simulate — Execute Round-Robin request load simulation
    public function simulate(Request $request): JsonResponse
    {
        $count = (int) $request->input('requests', 100);
        $count = min(max($count, 1), 1000);

        $result = $this->lb->simulate($count);

        return response()->json([
            'status' => 'ok',
            'data'   => $result,
            'note'   => 'Weighted Round-Robin: server-1(3x) > server-2(2x) > server-3(1x)',
        ]);
    }

    // POST /api/lb/dispatch — Route a single virtual request
    public function dispatch(Request $request): JsonResponse
    {
        $requestId = $request->input('request_id', uniqid('REQ-'));
        $endpoint  = $request->input('endpoint', '/api/orders');

        $routed = $this->lb->dispatch($requestId, $endpoint);

        return response()->json(['status' => 'ok', 'data' => $routed]);
    }

    // POST /api/lb/server-down — Simulate node failure (for live presentation failover demonstration)
    public function markDown(Request $request): JsonResponse
    {
        $serverId = $request->input('server_id', 'server-3');
        $this->lb->markServerDown($serverId);
        return response()->json(['status' => 'ok', 'message' => "{$serverId} marked DOWN. Failover active."]);
    }

    // POST /api/lb/server-up — Restore failed server node
    public function markUp(Request $request): JsonResponse
    {
        $serverId = $request->input('server_id', 'server-3');
        $this->lb->markServerUp($serverId);
        return response()->json(['status' => 'ok', 'message' => "{$serverId} restored."]);
    }
}
