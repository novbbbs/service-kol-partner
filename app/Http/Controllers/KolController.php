<?php

namespace App\Http\Controllers;

use App\Models\Kol;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KolController extends Controller
{
    /**
     * Helper untuk mengubah ID angka provinsi menjadi teks nama provinsi secara lengkap (1-34)
     */
    private function resolveProvinceName(mixed $provinceInput): string
    {
        if (is_numeric($provinceInput)) {
            $provinceMap = [
                1 => 'ACEH',
                2 => 'SUMATERA UTARA',
                3 => 'SUMATERA BARAT',
                4 => 'RIAU',
                5 => 'JAMBI',
                6 => 'SUMATERA SELATAN',
                7 => 'BENGKULU',
                8 => 'LAMPUNG',
                9 => 'KEPULAUAN BANGKA BELITUNG',
                10 => 'KEPULAUAN RIAU',
                11 => 'DKI JAKARTA',
                12 => 'JAWA BARAT',
                13 => 'JAWA TENGAH',
                14 => 'DI YOGYAKARTA',
                15 => 'JAWA TIMUR',
                16 => 'BANTEN',
                17 => 'BALI',
                18 => 'NUSA TENGGARA BARAT',
                19 => 'NUSA TENGGARA TIMUR',
                20 => 'KALIMANTAN BARAT',
                21 => 'KALIMANTAN TENGAH',
                22 => 'KALIMANTAN SELATAN',
                23 => 'KALIMANTAN TIMUR',
                24 => 'KALIMANTAN UTARA',
                25 => 'SULAWESI UTARA',
                26 => 'SULAWESI TENGAH',
                27 => 'SULAWESI SELATAN',
                28 => 'SULAWESI TENGGARA',
                29 => 'GORONTALO',
                30 => 'SULAWESI BARAT',
                31 => 'MALUKU',
                32 => 'MALUKU UTARA',
                33 => 'PAPUA',
                34 => 'PAPUA BARAT'
            ];
            return $provinceMap[(int)$provinceInput] ?? 'JAWA TENGAH';
        }

        return strtoupper((string)$provinceInput);
    }

    /**
     * Helper untuk mengonversi type campaign teks menjadi integer ID
     */
    private function resolveTypeId(mixed $typeInput): int
    {
        if (is_numeric($typeInput)) {
            return (int)$typeInput;
        }

        $typeMap = [
            'Reguler' => 1,
            'Nataru' => 1,
            'Dance' => 2,
            'Jockers' => 3,
        ];

        return $typeMap[$typeInput] ?? 1;
    }

    /**
     * Menampilkan data KOL dengan Backend Search & Filter
     */
    public function index(Request $request)
    {
        $query = Kol::with('campaign');

        // 1. Backend Search Universal
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

        // 2. Backend Filter berdasarkan Status (ACTIVE / INACTIVE)
        if ($request->has('status') && $request->status !== 'Semua' && $request->status !== '') {
            $statusVal = ($request->status === 'ACTIVE' || $request->status === 'Aktif' || $request->status === '1') ? 1 : 0;
            $query->where('status', $statusVal);
        }

        // 3. Backend Filter berdasarkan Rentang Tanggal Campaign (Opsional)
        if ($request->has('start_date') && $request->start_date != '') {
            $query->where('campaign_start_date', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date != '') {
            $query->where('campaign_end_date', '<=', $request->end_date);
        }

        $kols = $query->latest()->get();

        // 4. Response Mapping dari Backend agar seragam dibaca Frontend
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
        $request->validate([
            'name' => 'required|string|max:150',
            'username' => 'required|string|max:150',
            'whatsapp' => 'required|string|max:20',
            'province_name' => 'required',
            'city_name' => 'required',
            'type' => 'required',
            'campaign_start_date' => 'required|date',
            'campaign_end_date' => 'required|date',
        ]);

        $letters = strtoupper(Str::random(3));
        $numbers = mt_rand(10000, 99999);
        $referralCode = $letters . $numbers;

        $rawProvince = $request->province_name ?? $request->provinsi;
        $resolvedProvinceName = $this->resolveProvinceName($rawProvince);
        $resolvedTypeId = $this->resolveTypeId($request->type);

        $kol = Kol::create([
            'uid' => (string) Str::uuid(),
            'referral_code' => $referralCode,
            'name' => $request->name ?? $request->nama_kol,
            'username' => $request->username,
            'whatsapp' => $request->whatsapp ?? $request->nomor_telepon,
            'province_id' => is_numeric($rawProvince) ? (int)$rawProvince : 13,
            'province_name' => $resolvedProvinceName,
            'city_id' => 1,
            'city_name' => $request->city_name ?? $request->kota_asal,
            'type' => $resolvedTypeId,
            'campaign_start_date' => $request->campaign_start_date ?? $request->campaign_start,
            'campaign_end_date' => $request->campaign_end_date ?? $request->campaign_end,
            'status' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data KOL berhasil disimpan',
            'data' => $kol
        ], 201);
    }

    /**
     * Memperbarui data KOL
     */
    public function update(Request $request, mixed $id)
    {
        $kol = Kol::find($id);
        if (!$kol) {
            return response()->json(['success' => false, 'message' => 'Data KOL tidak ditemukan'], 404);
        }

        $rawProvince = $request->province_name ?? $request->provinsi;
        $resolvedProvinceName = $rawProvince ? $this->resolveProvinceName($rawProvince) : $kol->province_name;
        $resolvedTypeId = $request->type ? $this->resolveTypeId($request->type) : $kol->type;

        $kol->update([
            'name' => $request->name ?? $request->nama_kol ?? $kol->name,
            'username' => $request->username ?? $kol->username,
            'whatsapp' => $request->whatsapp ?? $request->nomor_telepon ?? $kol->whatsapp,
            'province_id' => is_numeric($rawProvince) ? (int)$rawProvince : $kol->province_id,
            'province_name' => $resolvedProvinceName,
            'city_name' => $request->city_name ?? $request->kota_asal ?? $kol->city_name,
            'type' => $resolvedTypeId,
            'campaign_start_date' => $request->campaign_start_date ?? $request->campaign_start ?? $kol->campaign_start_date,
            'campaign_end_date' => $request->campaign_end_date ?? $request->campaign_end ?? $kol->campaign_end_date,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data KOL berhasil diperbarui',
            'data' => $kol
        ], 200);
    }

    /**
     * Menghapus data KOL
     */
    public function destroy(mixed $id)
    {
        $kol = Kol::find($id);
        if (!$kol) {
            return response()->json(['success' => false, 'message' => 'Data KOL tidak ditemukan'], 404);
        }

        $kol->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data KOL berhasil dihapus'
        ], 200);
    }
}