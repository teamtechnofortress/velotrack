<?php

use App\Http\Controllers\Api\VeloTrackController;
use App\Http\Controllers\Api\FleetController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [VeloTrackController::class, 'register'])->middleware('throttle:6,1');
Route::post('/auth/login', [VeloTrackController::class, 'login'])->middleware('throttle:6,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [VeloTrackController::class, 'me']);
    Route::post('/auth/logout', [VeloTrackController::class, 'logout']);
    Route::get('/plans', [FleetController::class, 'catalog']);
    Route::get('/subscription', [FleetController::class, 'entitlement']);
    Route::get('/fleet', [FleetController::class, 'show']);
    Route::put('/fleet', [FleetController::class, 'save']);
    Route::post('/fleet/drivers', [FleetController::class, 'addDriver']);
    Route::put('/fleet/drivers/{id}', [FleetController::class, 'editDriver']);
    Route::delete('/fleet/drivers/{id}', [FleetController::class, 'removeDriver']);
    Route::get('/fleet/dashboard', [FleetController::class, 'dashboard']);
    Route::post('/fleet/invitations', [FleetController::class, 'invitation'])->middleware('throttle:10,1');
    Route::post('/fleet/invitations/accept', [FleetController::class, 'accept'])->middleware('throttle:10,1');
    Route::delete('/fleet/members/{id}', [FleetController::class, 'removeMember']);
    Route::post('/fleet/leave', [FleetController::class, 'leave']);
    Route::get('/backup', [VeloTrackController::class, 'download']);
    Route::put('/backup', [VeloTrackController::class, 'upload'])->middleware('throttle:20,1');
});
