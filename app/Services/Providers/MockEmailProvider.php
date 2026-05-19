<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Log;
use Random\RandomException;
use RuntimeException;

class MockEmailProvider
{
    /**
     * @throws RandomException
     */
    public function send(int $recipientId, string $message): bool
    {
        usleep(150000);

        if (random_int(1, 100) <= 5) {
            throw new RuntimeException("Temporary Email provider error.");
        }

        if ($recipientId < 1000) {
            Log::warning("Mock Email: Invalid recipient ID {$recipientId}.");
            return false;
        }

        Log::info("Mock Email sent to {$recipientId}.");
        return true;
    }
}
