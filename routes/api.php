<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/notifications/bulk', [NotificationController::class, 'bulkSend']);
Route::get('/subscribers/{subscriberId}/notifications', [NotificationController::class, 'history']);
