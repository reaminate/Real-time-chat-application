<?php

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download']);
    Route::get('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'meSelf']);
    Route::get('/active-users', [UserController::class, 'currentlyActive']);
    Route::get('/notifications', [NotificationController::class, 'notifications']);
    Route::post('/notify/{user}/in/{conversation}', [NotificationController::class, 'notifyUser']);
    Route::prefix('pin')->group(function(){
        Route::controller(ConversationController::class)->group(function(){
            Route::put('/{conversation}/add', 'pinMessage');
            Route::put('/{conversation}/remove', 'removePinMessage');
            Route::get('/{conversation}/pinned', 'viewPinnedOnly');
        });
    });
    Route::controller(MessageController::class)->group(function(){
        Route::get('/message/{message}/restore', 'restore')->withTrashed();
        Route::delete('/message/{message}/force_delete', 'forceDelete')->withTrashed();
    });
    Route::controller(ConversationController::class)->group(function(){
        Route::prefix('conversation/{conversation}')->group(function(){
            Route::get('/messages', 'show');
            Route::get('/members_in', 'showMembers');
            Route::post('/users', 'addUsers');
            Route::delete('/users', 'deleteUsers');
            Route::post('/typing', 'userTyping');
            Route::post('/stopped-typing', 'userStoppedTyping');
            Route::post('/restore', 'restore')->withTrashed();
            Route::delete('/force_delete', 'forceDelete')->withTrashed();
        });
    });
    Route::apiResource('/user', UserController::class)->except('store');
    Route::apiResource('/conversation', ConversationController::class)->except('show');
    Route::apiResource('/message', MessageController::class);
});
