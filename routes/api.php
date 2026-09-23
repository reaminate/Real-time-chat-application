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
    Route::post('/conversation/{conversation}/users', [ConversationController::class, 'addUsers']);
    Route::delete('/conversation/{conversation}/users', [ConversationController::class, 'deleteUsers']);
    Route::get('/message/{message}/restore', [MessageController::class, 'restore'])->withTrashed();
    Route::delete('message{message}/force_delete', [MessageController::class, 'forceDelete'])->withTrashed();
    Route::get('/user/{user}', [UserController::class, 'view']);

    Route::apiResource('/user', UserController::class)->except('except', 'store');
    Route::apiResources([
        '/conversation' => ConversationController::class,
        '/message' => MessageController::class,
    ]);
});
