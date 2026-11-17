<?php

namespace App\Http\Controllers;

use App\Models\Kol;
use Illuminate\Http\Request;

class ReferralValidationController extends Controller
{
    /**
     * API Mandiri untuk mencocokkan dan memvalidasi kode referral dari web pembelian eksternal
     */
    public function validateCode(Request $request)
    {
        try {
            $request->validate([
                'referral_code' => 'required|string'
            ]);

            $code = trim($request->input('referral_code'));

            // Cari data KOL berdasarkan kode referral
            $kol = Kol::where('referral_code', $code)
                      ->orWhere('referral_code', strtoupper($code))
                      ->first();

            if (!$kol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode referral tidak valid atau tidak ditemukan.'
                ], 404);
            }

            // Periksa apakah status KOL aktif (1 = ACTIVE)
            if ($kol->status != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode referral sudah non-aktif.'
                ], 422);
            }

            // Periksa masa berlaku campaign
            $today = date('Y-m-d');
            if ($kol->campaign_end_date && $today > $kol->campaign_end_date) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masa berlaku campaign untuk kode referral ini telah berakhir.'
                ], 422);
            }

            // Jika valid, kembalikan data informasi KOL agar web bisa melanjutkan reservasi
            return response()->json([
                'success' => true,
                'message' => 'Kode referral valid.',
                'data' => [
                    'id' => $kol->id,
                    'name' => $kol->name,
                    'username' => $kol->username,
                    'referral_code' => $kol->referral_code,
                    'type' => $kol->type,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }
}