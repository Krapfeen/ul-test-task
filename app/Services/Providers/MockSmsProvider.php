<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Log;
use Random\RandomException;
use RuntimeException;

class MockSmsProvider
{
    /**
     * @throws RandomException
     */
    public function send(int $recipientId, string $message): bool
    {
        usleep(200000);

        if (random_int(1, 100) <= 10) {
            throw new RuntimeException("Temporary SMS provider error.");
        }

        if ($recipientId < 1000) {
            Log::warning("Mock SMS: Invalid recipient ID {$recipientId}.");
            return false;
        }

        Log::info("Mock SMS sent to {$recipientId}.");
        return true;
    }
}
