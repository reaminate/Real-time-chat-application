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
    Route::apiResource('/user', UserController::class)->except('store');
           
    Route::controller(MessageController::class)->group(function(){
        Route::get('/message/{message}/restore', 'restore')->withTrashed();
        Route::delete('/message/{message}/force_delete', 'forceDelete')->withTrashed();
    });
    Route::controller(ConversationController::class)->group(function(){
        Route::get('/conversation/{conversation}/messages', 'show');
        Route::post('/conversation/{conversation}/users', 'addUsers');
        Route::delete('/conversation/{conversation}/users', 'deleteUsers');
        Route::post('/conversation/{conversation}/typing', 'userTyping');
        Route::post('/conversation/{conversation}/restore', 'restore');
        Route::delete('/conversation/{conversation}/force_delete', 'forceDelete');
    });

    Route::apiResource('/conversation', ConversationController::class)->except('show');
    Route::apiResource('/message', MessageController::class);
});
