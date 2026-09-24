<?php

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::post('/register', [AuthController::class, 'register']);
Route::get('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/logout', [AuthController::class, 'logout']);

    Route::controller(ConversationController::class)->group(function(){
        Route::post('/conversation/{conversation}/users', 'addUsers');
        Route::delete('/conversation/{conversation}/users', 'deleteUsers');
    });
    Route::controller(MessageController::class)->group(function(){
        Route::get('/message/{message}/restore', 'restore');
        Route::delete('/message/{message}/force_delete', 'forceDelete');
    });
    Route::controller(ConversationController::class)->group(function(){
        Route::get('/conversation/{conversation}/restore', 'restore');
        Route::delete('/conversation/{conversation}/force_delete', 'forceDelete');
    });
    Route::apiResource('/user', UserController::class)->except('store');
    Route::apiResources([
        '/conversation' => ConversationController::class,
        '/message' => MessageController::class,
    ]);
});
