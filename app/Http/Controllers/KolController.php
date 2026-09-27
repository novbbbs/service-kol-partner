<?php

namespace App\Http\Controllers;

use App\Models\Kol;
use App\Models\Campaign;
use App\Services\KolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $query = Kol::with('campaign');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%")
                    ->orWhere('city_name', 'like', "%{$search}%")
                    ->orWhere('province_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'Semua' && $request->status !== '') {
            $statusVal = ($request->status === 'ACTIVE' || $request->status === 'Aktif' || $request->status === '1') ? 1 : 0;
            $query->where('status', $statusVal);
        }

        if ($request->has('start_date') && $request->start_date != '') {
            $query->where('campaign_start_date', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date != '') {
            $query->where('campaign_end_date', '<=', $request->end_date);
        }

        $kols = $query->latest()->get();

        $formatted = $kols->map(function($item) {
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
                'tipe_kol' => optional($item->campaign)->campaign_name ?? $item->type,
                'type' => optional($item->campaign)->campaign_name ?? $item->type,
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
    }

    /**
     * Menyimpan data KOL baru
     */
    public function store(Request $request)
    {
        try {
            // Mendelegasikan pembuatan data ke KolService yang sudah fleksibel menangani berbagai nama field
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
     * Memperbarui data KOL
     */
    public function update(Request $request, mixed $id)
    {
        try {
            $kol = $this->kolService->updateKol($id, $request->all());

            if (!$kol) {
                return response()->json(['success' => false, 'message' => 'Data KOL tidak ditemukan'], 404);
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