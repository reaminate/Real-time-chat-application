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
    Route::get('/active-users', [UserController::class, 'currentlyActive']);
    Route::controller(AuthController::class)->group(function(){
        Route::get('/logout','logout');
        Route::get('/me', 'meSelf');
    });
    Route::controller(NotificationController::class)->group(function(){
        Route::get('/notifications', 'notifications');
        Route::post('/notify/{user}/in/{conversation}','notifyUser');
    });
    Route::controller(ConversationController::class)->prefix('pin/{conversation}')->group(function(){
        Route::put('/add', 'pinMessage');
        Route::put('/remove', 'removePinMessage');
        Route::get('/pinned', 'viewPinnedOnly');
    });
    Route::controller(MessageController::class)->prefix('message/{message}')->group(function(){
        Route::get('/restore', 'restore')->withTrashed();
        Route::delete('/force_delete', 'forceDelete')->withTrashed();
        Route::middleware('throttle:reactions')->group(function(){
            Route::post('/like', 'like');
            Route::post('/dislike', 'dislike');
            Route::delete('/remove_reaction', 'removeReaction');
        });
    });
    Route::controller(ConversationController::class)->prefix('conversation/{conversation}')->group(function(){
        Route::get('/messages', 'show');
        Route::get('/members_in', 'showMembers');
        Route::post('/users', 'addUsers');
        Route::delete('/users', 'deleteUsers');
        Route::post('/typing', 'userTyping');
        Route::post('/stopped-typing', 'userStoppedTyping');
        Route::post('/restore', 'restore')->withTrashed();
        Route::delete('/force_delete', 'forceDelete')->withTrashed();
    });
    Route::apiResource('/user', UserController::class)->except('store');
    Route::apiResource('/conversation', ConversationController::class)->except('show');
    Route::apiResource('/message', MessageController::class);
});
