<?php

namespace App\Http\Controllers;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\IdempotencyService;
use App\Services\Providers\MockEmailProvider;
use App\Services\Providers\MockSmsProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function bulkSend(Request $request, IdempotencyService $idempotency): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:sms,email',
            'message' => 'required|string|max:1000',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'integer',
            'priority' => 'required|in:transactional,marketing',
            'idempotency_key' => 'sometimes|string',
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? null;
        if (!$idempotency->checkAndStore($idempotencyKey, $validated)) {
            return response()->json(['error' => 'Duplicate request'], 409);
        }

        $notificationIds = [];

        foreach ($validated['recipient_ids'] as $subscriberId) {
            $notification = Notification::create([
                'subscriber_id' => $subscriberId,
                'channel' => $validated['channel'],
                'message' => $validated['message'],
                'priority' => $validated['priority'],
                'status' => NotificationStatus::QUEUED,
            ]);

            $notificationIds[] = $notification->id;

            $notification->update(['status' => NotificationStatus::SENT]);

            $provider = $validated['channel'] === 'sms'
                ? new MockSmsProvider()
                : new MockEmailProvider();

            try {
                $delivered = $provider->send($subscriberId, $validated['message']);
                if ($delivered) {
                    $notification->update(['status' => NotificationStatus::DELIVERED]);
                } else {
                    $notification->update(['status' => NotificationStatus::DROPPED]);
                }
            } catch (\Exception $e) {
                $notification->update(['status' => NotificationStatus::DROPPED]);
                Log::error($e->getMessage());
            }
        }

        return response()->json([
            'notification_ids' => $notificationIds,
            'total' => count($notificationIds),
            'idempotency_key' => $idempotencyKey ?? 'generated-on-server',
        ], 202);
    }

    public function history(int $subscriberId): JsonResponse
    {
        $notifications = Notification::where('subscriber_id', $subscriberId)->get();
        return response()->json($notifications);
    }
}
