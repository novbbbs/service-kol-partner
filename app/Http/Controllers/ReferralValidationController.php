<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
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
                'referral_code' => 'required|string',
                'price' => 'nullable|numeric|min:0',
                'ticket_price' => 'nullable|numeric|min:0',
                'original_price' => 'nullable|numeric|min:0',
                'qty' => 'nullable|integer|min:1',
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

            // Cari data segment / campaign berdasarkan kolom type pada KOL
            $campaign = null;
            if (!empty($kol->type)) {
                $campaign = Campaign::whereRaw('LOWER(TRIM(campaign_name)) = ?', [strtolower(trim((string) $kol->type))])
                    ->orWhere('id', $kol->type)
                    ->first();
            }

            // Ambil nominal potongan harga dari master campaign (default 0)
            $discount = $campaign ? (int) ($campaign->discount ?? 0) : 0;

            // Cek jika web pembelian ikut mengirimkan harga tiket (opsional)
            $rawPrice = $request->input('original_price')
                ?? $request->input('ticket_price')
                ?? $request->input('price');

            $qty = max(1, (int) ($request->input('qty', 1)));
            $originalPrice = $rawPrice !== null ? (int) $rawPrice : null;
            $totalDiscount = $discount * $qty;
            $finalPrice = $originalPrice !== null
                ? max(0, $originalPrice - $totalDiscount)
                : null;

            // Jika valid, kembalikan data informasi KOL beserta nominal potongan & harga akhir
            return response()->json([
                'success' => true,
                'message' => 'Kode referral valid.',
                'data' => [
                    'id' => $kol->id,
                    'name' => $kol->name,
                    'username' => $kol->username,
                    'referral_code' => $kol->referral_code,
                    'type' => $kol->type,
                    'discount' => $discount,
                    'total_discount' => $totalDiscount,
                    'original_price' => $originalPrice,
                    'final_price' => $finalPrice,
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