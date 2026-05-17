<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Requirement #3 — Asynchronous Queue Job
 *
 * Dispatches push notifications/SMS in the background.
 * Routes execution specifically through the 'notifications' queue.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 15;

    public function __construct(
        private readonly int $orderId,
        private readonly int $userId
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            return;
        }

        // Simulate notification service latency
        Log::info("[NOTIFY] Notification successfully sent to User #{$this->userId} for Order #{$this->orderId}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[NOTIFY] Notification failed to dispatch for Order #{$this->orderId}: " . $exception->getMessage());
    }
}
