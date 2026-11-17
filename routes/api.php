<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\KolController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReferralValidationController; // 👈 1. IMPORT CONTROLLER BARU

// ==========================================
// ROUTE AUTENTIKASI PUBLIK
// ==========================================
Route::post('/login', [LoginController::class, 'login']);

// ==========================================
// ROUTE PUBLIK UNTUK EKSTERNAL (Web Pembelian Tiket)
// ==========================================
// Endpoint POST mandiri untuk memvalidasi kode referral dari web pembelian
Route::post('/validate-referral', [ReferralValidationController::class, 'validateCode']);


// ==========================================
// ROUTE YANG DIPROTEKSI SANCTUM (Wajib membawa Token)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    
    // Endpoint untuk verifikasi sesi dan mengambil data user dari database
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });

    // Endpoint untuk Logout (menghapus token akses aktif di database)
    Route::post('/logout', [LoginController::class, 'logout']);

    // Routes untuk Master Campaign
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::put('/campaigns/{id}', [CampaignController::class, 'update']);
    Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy']);

    // Routes untuk Master KOL
    Route::get('/kols', [KolController::class, 'index']);
    Route::get('/kols/check-export', [KolController::class, 'checkExport']);
    // 👇 ROUTE UNTUK LOG HISTORY KOL
    Route::get('/kols/history/{username}', [KolController::class, 'getHistory']);
    
    Route::post('/kols', [KolController::class, 'store']);
    Route::put('/kols/{id}', [KolController::class, 'update']);
    Route::delete('/kols/{id}', [KolController::class, 'destroy']);

    // Routes untuk Dashboard (Reguler & Event / Campaign Summary)
    Route::get('/dashboard/summary', [DashboardController::class, 'index']);

    // ==========================================
    // Routes untuk Data Reservasi (Menggunakan prefix admin/)
    // ==========================================
    Route::get('/admin/reservations', [ReservationController::class, 'index']);
    // Route::post('/admin/reservations', [ReservationController::class, 'store']);
    // Route::put('/admin/reservations/{id}', [ReservationController::class, 'update']);
    // Route::delete('/admin/reservations/{id}', [ReservationController::class, 'destroy']);
    
});