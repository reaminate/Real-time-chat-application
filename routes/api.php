<?php

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\ConversationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::post('/register', [AuthController::class, 'register']);
Route::get('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('/conversation', ConversationController::class);
});
