<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'subscriber_id',
        'channel',
        'message',
        'priority',
        'status',
        'retry_count'
    ];

    protected $casts = [
        'status' => NotificationStatus::class,
        'retry_count' => 'integer',
    ];
}
