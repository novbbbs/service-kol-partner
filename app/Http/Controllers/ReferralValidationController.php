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
            if ((int) $kol->status !== 1) {
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

            // Cari data master campaign berdasarkan ID maupun nama tipe pada kolom type KOL
            $campaign = null;
            $rawType = trim((string) ($kol->type ?? ''));

            if ($rawType !== '') {
                $campaign = Campaign::where(function ($q) use ($rawType) {
                    if (is_numeric($rawType)) {
                        $q->where('id', (int) $rawType);
                    }
                    $q->orWhereRaw('LOWER(TRIM(campaign_name)) = ?', [strtolower($rawType)]);
                })
                ->orderByDesc('status')
                ->first();
            }

            // Resolusi nama tipe campaign (prioritas dari master campaign, fallback ke kolom type KOL / 'Reguler')
            $resolvedCampaignType = $campaign
                ? ($campaign->campaign_name ?? $campaign->name ?? $campaign->title ?? 'Reguler')
                : ($rawType !== '' && !is_numeric($rawType) ? $rawType : 'Reguler');

            // Ambil nominal potongan harga per item hanya jika campaign ditemukan & aktif (default 0)
            $discount = ($campaign && (int) ($campaign->status ?? 1) === 1)
                ? max(0, (int) ($campaign->discount ?? 0))
                : 0;

            // Cek jika web pembelian ikut mengirimkan harga (asumsi: total harga sebelum diskon)
            $rawPrice = $request->input('original_price')
                ?? $request->input('ticket_price')
                ?? $request->input('price');

            $qty = max(1, (int) ($request->input('qty', 1)));
            $originalPrice = $rawPrice !== null ? max(0, (int) $rawPrice) : null;
            $rawTotalDiscount = $discount * $qty;

            $effectiveDiscount = $originalPrice !== null
                ? min($rawTotalDiscount, $originalPrice)
                : $rawTotalDiscount;

            $finalPrice = $originalPrice !== null
                ? max(0, $originalPrice - $effectiveDiscount)
                : null;

            return response()->json([
                'success' => true,
                'message' => 'Kode referral valid.',
                'data' => [
                    'id' => $kol->id,
                    'name' => $kol->name,
                    'username' => $kol->username,
                    'referral_code' => $kol->referral_code,
                    'type' => $resolvedCampaignType,
                    'type_id' => $campaign ? $campaign->id : (is_numeric($rawType) ? (int) $rawType : null),
                    'discount' => $discount,
                    'total_discount' => $effectiveDiscount,
                    'original_price' => $originalPrice,
                    'final_price' => $finalPrice,
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data validasi tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }
}