<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MovementReceiptController;
use App\Http\Controllers\MovementAttachmentPrintController;
use App\Http\Controllers\MaintenanceReceiptController;
use App\Http\Controllers\MaintenanceAttachmentPrintController;
use App\Http\Controllers\MaintenanceBolleDownloadController;
use App\Http\Controllers\UserDocumentDownloadController;
use App\Http\Controllers\VehiclePerformancePdfDownloadController;
use App\Http\Controllers\VehicleRevenueAttachmentsDownloadController;

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

Route::get('/admin/user-documents/files/{file}/download', UserDocumentDownloadController::class)
    ->middleware('auth')
    ->name('user-documents.files.download');

Route::get('/admin/vehicles/revenues/download', VehicleRevenueAttachmentsDownloadController::class)
    ->middleware('auth')
    ->name('vehicles.revenues.download');

Route::get('/admin/report-general/vehicle-performance/download', VehiclePerformancePdfDownloadController::class)
    ->middleware('auth')
    ->name('report-general.vehicle-performance.download');
