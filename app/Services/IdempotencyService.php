<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Hash;

class IdempotencyService
{
    private const int TTL_SECONDS = 86400;

    public function checkAndStore(?string $clientKey, array $requestData): ?string
    {
        if (!$clientKey) {
            $content = json_encode([
                'channel' => $requestData['channel'],
                'message' => $requestData['message'],
                'recipient_ids' => $requestData['recipient_ids'],
                'priority' => $requestData['priority'],
            ]);
            $clientKey = Hash::make($content);
        }

        $redisKey = "idempotency:{$clientKey}";

        if (Redis::exists($redisKey)) {
            return null;
        }

        Redis::setex($redisKey, self::TTL_SECONDS, 'processed');
        return $clientKey;
    }
}
