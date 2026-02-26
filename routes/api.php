<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserDocumentController;
use App\Http\Controllers\Api\MovementController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/movements', [MovementController::class, 'index']);
    Route::get('/movements/km-start', [MovementController::class, 'kmStart']);
    Route::post('/movements', [MovementController::class, 'store']);

    Route::get('/stations', [StationController::class, 'index']);
    Route::get('/vehicles', [VehicleController::class, 'index']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::get('/maintenances', [MaintenanceController::class, 'index']);
    Route::post('/maintenances', [MaintenanceController::class, 'store']);

    Route::get('/documents', [UserDocumentController::class, 'index']);
    Route::post('/documents/files/{file}/open', [UserDocumentController::class, 'open']);
    Route::get('/documents/files/{file}/download', [UserDocumentController::class, 'download']);
});
