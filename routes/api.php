<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\KolController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;

// ==========================================
// ROUTE AUTENTIKASI PUBLIK
// ==========================================
Route::post('/login', [LoginController::class, 'login']);


// ==========================================
// ROUTE YANG DPROTEKSI SANCTUM (Wajib membawa Token)
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
    Route::post('/kols', [KolController::class, 'store']);
    Route::put('/kols/{id}', [KolController::class, 'update']);
    Route::delete('/kols/{id}', [KolController::class, 'destroy']);

    // Routes untuk Dashboard (Reguler & Event / Campaign Summary)
    Route::get('/dashboard/summary', [DashboardController::class, 'index']);

});