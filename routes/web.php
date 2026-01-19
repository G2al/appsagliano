<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MovementReceiptController;
use App\Http\Controllers\MovementAttachmentPrintController;
use App\Http\Controllers\MaintenanceReceiptController;
use App\Http\Controllers\MaintenanceAttachmentPrintController;
use App\Http\Controllers\MaintenanceBolleDownloadController;

Route::get('/', function () {
    return redirect('/worker/login.html');
});


Route::get('/admin/movements/{movement}/receipt', MovementReceiptController::class)
    ->middleware('auth')
    ->name('movements.receipt');

Route::get('/admin/movements/{movement}/attachment', MovementAttachmentPrintController::class)
    ->middleware('auth')
    ->name('movements.attachment');

Route::get('/admin/maintenances/{maintenance}/receipt', MaintenanceReceiptController::class)
    ->middleware('auth')
    ->name('maintenances.receipt');

Route::get('/admin/maintenances/{maintenance}/attachment', MaintenanceAttachmentPrintController::class)
    ->middleware('auth')
    ->name('maintenances.attachment');

Route::get('/admin/maintenances/download-bolle', MaintenanceBolleDownloadController::class)
    ->middleware('auth')
    ->name('maintenances.download-bolle');
