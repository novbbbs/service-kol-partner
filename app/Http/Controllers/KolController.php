<?php

namespace App\Http\Controllers;

use App\Models\Kol;
use App\Services\KolService;
use Illuminate\Http\Request;

class KolController extends Controller
{
    protected $kolService;

    public function __construct(KolService $kolService)
    {
        $this->kolService = $kolService;
    }

    /**
     * Menampilkan data KOL dengan Backend Search & Filter
     */
    public function index(Request $request)
    {
        try {
            $query = Kol::with('campaign');

            // 1. Backend Search
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('referral_code', 'like', "%{$search}%")
                      ->orWhere('whatsapp', 'like', "%{$search}%")
                      ->orWhere('city_name', 'like', "%{$search}%")
                      ->orWhere('province_name', 'like', "%{$search}%");
                });
            }

            // 2. Backend Filter Status (Hanya filter jika bukan 'Semua' atau kosong)
            if ($request->filled('status') && !in_array($request->status, ['Semua', 'Semua Status', ''])) {
                $statusVal = (strtoupper($request->status) === 'ACTIVE' || $request->status === '1') ? 1 : 0;
                $query->where('status', $statusVal);
            }

            // 3. Backend Filter Tanggal
            if ($request->filled('start_date')) {
                $query->where('campaign_start_date', '>=', $request->input('start_date'));
            }
            if ($request->filled('end_date')) {
                $query->where('campaign_end_date', '<=', $request->input('end_date'));
            }

            $kols = $query->latest()->get();

            $formatted = $kols->map(function($item) {
                $campaignName = optional($item->campaign)->campaign_name 
                    ?? optional($item->campaign)->name 
                    ?? optional($item->campaign)->title;

                $resolvedType = $campaignName ?? ($item->type ? (is_numeric($item->type) ? 'Reguler' : $item->type) : 'Reguler');

                return [
                    'id' => $item->id,
                    'uid' => $item->uid,
                    'nama_kol' => $item->name,
                    'name' => $item->name,
                    'username' => $item->username,
                    'kode_referral' => $item->referral_code,
                    'referral_code' => $item->referral_code,
                    'nomor_telepon' => $item->whatsapp,
                    'whatsapp' => $item->whatsapp,
                    'kota_asal' => $item->city_name,
                    'city_name' => $item->city_name,
                    'provinsi' => $item->province_name,
                    'province_name' => $item->province_name,
                    'tipe_kol' => $resolvedType,
                    'type' => $resolvedType,
                    'campaign_start_date' => $item->campaign_start_date,
                    'campaign_end_date' => $item->campaign_end_date,
                    'status' => $item->status == 1 ? 'ACTIVE' : 'INACTIVE',
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data KOL berhasil diambil dari backend',
                'data' => $formatted
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data KOL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data KOL baru
     */
    public function store(Request $request)
    {
        try {
            $kol = $this->kolService->createKol($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Data KOL berhasil disimpan',
                'data' => $kol
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Memperbarui data KOL (Termasuk Toggle Status secara aman)
     */
    public function update(Request $request, mixed $id)
    {
        try {
            $kol = Kol::where('id', $id)->orWhere('uid', $id)->first();

            if (!$kol) {
                return response()->json(['success' => false, 'message' => 'Data KOL tidak ditemukan'], 404);
            }

            // Jika request hanya membawa status (seperti saat toggle switch), langsung update kolom status
            if ($request->has('status') && count($request->all()) <= 2) {
                $rawStatus = $request->input('status');
                $statusVal = in_array(strtolower(trim((string)$rawStatus)), ['1', 'active', 'aktif', 'true'], true) ? 1 : 0;
                
                $kol->update(['status' => $statusVal]);
            } else {
                // Jika melalui form edit lengkap, gunakan service
                $this->kolService->updateKol($kol, $request->all());
            }

            return response()->json([
                'success' => true,
                'message' => 'Data KOL berhasil diperbarui',
                'data' => $kol
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Menghapus data KOL secara permanen dari MySQL (Hard Delete)
     */
    public function destroy(mixed $id)
    {
        $deleted = $this->kolService->deleteKol($id);

        if (!$deleted) {
            return response()->json([
                'success' => false, 
                'message' => 'Data KOL tidak ditemukan dengan ID/UID: ' . $id
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data KOL berhasil dihapus dari database'
        ], 200);
    }
}