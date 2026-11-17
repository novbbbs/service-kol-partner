<?php

namespace App\Http\Controllers;

use App\Models\Kol;
use App\Services\KolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

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

            if ($request->filled('status') && !in_array($request->status, ['Semua', 'Semua Status', ''])) {
                $statusVal = (strtoupper($request->status) === 'ACTIVE' || $request->status === '1') ? 1 : 0;
                $query->where('status', $statusVal);
            }

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
                'message' => 'Data KOL berhasil dimuat.',
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
     * Mengambil riwayat (Log History) seluruh campaign/tipe KOL berdasarkan username
     */
    public function getHistory($username)
    {
        try {
            $cleanUsername = ltrim($username, '@');

            $histories = Kol::with('campaign')
                            ->where('username', 'like', "%{$cleanUsername}%")
                            ->orWhere('username', $username)
                            ->orderBy('campaign_start_date', 'desc')
                            ->latest()
                            ->get();

            if ($histories->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Riwayat KOL tidak ditemukan.'
                ], 404);
            }

            $formattedHistories = $histories->map(function($item) {
                $campaignName = optional($item->campaign)->campaign_name 
                    ?? optional($item->campaign)->name 
                    ?? optional($item->campaign)->title;

                $resolvedType = $campaignName ?? ($item->type ? (is_numeric($item->type) ? 'Reguler' : $item->type) : 'Reguler');

                return [
                    'id' => $item->id,
                    'uid' => $item->uid,
                    'name' => $item->name,
                    'username' => $item->username,
                    'kode_referral' => $item->referral_code,
                    'whatsapp' => $item->whatsapp,
                    'city_name' => $item->city_name,
                    'province_name' => $item->province_name,
                    'type' => $resolvedType,
                    'start_date' => $item->campaign_start_date,
                    'end_date' => $item->campaign_end_date,
                    'status' => $item->status == 1 ? 'ACTIVE' : 'INACTIVE',
                    'created_at' => $item->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Log history KOL berhasil dimuat.',
                'data' => $formattedHistories
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat log history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Memeriksa ketersediaan data sebelum export dilakukan
     */
    public function checkExport(Request $request)
    {
        try {
            $query = Kol::query();

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

            if ($request->filled('status') && !in_array($request->status, ['Semua', 'Semua Status', ''])) {
                $statusVal = (strtoupper($request->status) === 'ACTIVE' || $request->status === '1') ? 1 : 0;
                $query->where('status', $statusVal);
            }

            $count = $query->count();

            if ($count === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data KOL yang tersedia untuk diexport.',
                    'total' => 0
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data tersedia untuk diexport.',
                'total' => $count
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa data export: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Memproses data export Excel
     */
    public function export(Request $request)
    {
        try {
            $query = Kol::with('campaign');

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

            if ($request->filled('status') && !in_array($request->status, ['Semua', 'Semua Status', ''])) {
                $statusVal = (strtoupper($request->status) === 'ACTIVE' || $request->status === '1') ? 1 : 0;
                $query->where('status', $statusVal);
            }

            $kols = $query->latest()->get();

            $dataExport = $kols->map(function($item, $index) {
                $campaignName = optional($item->campaign)->campaign_name 
                    ?? optional($item->campaign)->name 
                    ?? optional($item->campaign)->title;

                $resolvedType = $campaignName ?? ($item->type ? (is_numeric($item->type) ? 'Reguler' : $item->type) : 'Reguler');

                return [
                    'No' => $index + 1,
                    'Kode Referral' => $item->referral_code,
                    'Nama KOL' => $item->name,
                    'Username' => $item->username,
                    'No. WhatsApp' => " " . $item->whatsapp,
                    'Kota Asal' => $item->city_name,
                    'Provinsi' => $item->province_name,
                    'Tipe Campaign' => $resolvedType,
                    'Mulai Campaign' => $item->campaign_start_date,
                    'Selesai Campaign' => $item->campaign_end_date,
                    'Status' => $item->status == 1 ? 'ACTIVE' : 'INACTIVE',
                ];
            })->toArray();

            return Excel::create('data-kol-' . date('Y-m-d'), function($excel) use ($dataExport) {
                $excel->sheet('Data KOL', function($sheet) use ($dataExport) {
                    $sheet->fromArray($dataExport);
                    
                    $sheet->row(1, function($row) {
                        $row->setFontWeight('bold');
                    });
                    
                    $delegate = $sheet->getDelegate();
                    $delegate->getColumnDimension('A')->setWidth(8);
                    $delegate->getColumnDimension('B')->setWidth(22);
                    $delegate->getColumnDimension('C')->setWidth(25);
                    $delegate->getColumnDimension('D')->setWidth(20);
                    $delegate->getColumnDimension('E')->setWidth(18);
                    $delegate->getColumnDimension('F')->setWidth(20);
                    $delegate->getColumnDimension('G')->setWidth(20);
                    $delegate->getColumnDimension('H')->setWidth(22);
                    $delegate->getColumnDimension('I')->setWidth(20);
                    $delegate->getColumnDimension('J')->setWidth(20);
                    $delegate->getColumnDimension('K')->setWidth(15);
                });
            })->download('xlsx');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengexport data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data KOL baru
     */
    public function store(Request $request)
    {
        try {
            $name = $request->input('name') ?? $request->input('nama_kol');
            $province = $request->input('province_name') ?? $request->input('provinsi');
            $city = $request->input('city_name') ?? $request->input('kota_asal');

            if (
                empty($name) || 
                !$request->filled('username') || 
                !$request->filled('whatsapp') || 
                empty($province) || 
                empty($city)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semua field formulir wajib diisi dengan lengkap.'
                ], 422);
            }

            $data = $request->all();
            if (isset($data['nama_kol']) && !isset($data['name'])) {
                $data['name'] = $data['nama_kol'];
            }
            if (isset($data['provinsi']) && !isset($data['province_name'])) {
                $data['province_name'] = $data['provinsi'];
            }
            if (isset($data['kota_asal']) && !isset($data['city_name'])) {
                $data['city_name'] = $data['kota_asal'];
            }

            $kol = $this->kolService->createKol($data);
            $kolName = $kol->name ?? 'KOL';

            // === INTEGRASI WHATSAPP FONNTE API SAAT STORE ===
            $this->sendFonnteNotification($kol);

            return response()->json([
                'success' => true,
                'message' => "KOL \"{$kolName}\" berhasil ditambahkan.",
                'data' => $kol
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'KOL gagal dibuat: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Memperbarui data KOL (Termasuk Toggle Status dan Integrasi Fonnte WhatsApp API)
     */
    public function update(Request $request, mixed $id)
    {
        try {
            $kol = Kol::where('id', $id)->orWhere('uid', $id)->first();

            if (!$kol) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Data KOL tidak ditemukan di sistem.'
                ], 404);
            }

            $kolName = $kol->name ?? 'KOL';

            // Jika hanya mengubah status (toggle)
            if ($request->has('status') && count($request->all()) <= 2) {
                $rawStatus = $request->input('status');
                $statusVal = in_array(strtolower(trim((string)$rawStatus)), ['1', 'active', 'aktif', 'true'], true) ? 1 : 0;
                
                $kol->update(['status' => $statusVal]);
                
                return response()->json([
                    'success' => true,
                    'message' => "Status KOL \"{$kolName}\" berhasil diubah.",
                    'data' => $kol
                ], 200);
            } else {
                $data = $request->all();
                if (isset($data['nama_kol']) && !isset($data['name'])) {
                    $data['name'] = $data['nama_kol'];
                }
                if (isset($data['provinsi']) && !isset($data['province_name'])) {
                    $data['province_name'] = $data['provinsi'];
                }
                if (isset($data['kota_asal']) && !isset($data['city_name'])) {
                    $data['city_name'] = $data['kota_asal'];
                }

                $updatedKol = $this->kolService->updateKol($kol, $data);
                
                if (!$updatedKol) {
                    $updatedKol = $kol->fresh();
                }

                // === INTEGRASI WHATSAPP FONNTE API DENGAN DATA TERBARU ===
                $this->sendFonnteNotification($updatedKol);

                return response()->json([
                    'success' => true,
                    'message' => "Data KOL \"{$kolName}\" berhasil diperbarui dan pesan WhatsApp terkirim.",
                    'data' => $updatedKol
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Helper privat untuk mengirim notifikasi WhatsApp via Fonnte
     */
    private function sendFonnteNotification($kol)
    {
        $phone = $kol->whatsapp;
        if (empty($phone)) {
            return;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        $campaignType = optional($kol->campaign)->campaign_name 
            ?? optional($kol->campaign)->name 
            ?? ($kol->type ? (is_numeric($kol->type) ? 'Reguler' : $kol->type) : 'Reguler');

        $referralCode = $kol->referral_code ?? '-';
        $startDate = $kol->campaign_start_date ?? '-';
        $endDate = $kol->campaign_end_date ?? '-';

        $message = "Halo *{$kol->name}* (@{$kol->username}),\n\n" .
                   "Data Campaign KOL Anda telah diperbarui (Regenerated):\n" .
                   "🎫 *Kode Referral*: *{$referralCode}*\n" .
                   "📋 *Type Campaign*: {$campaignType}\n" .
                   "📅 *Mulai Campaign*: {$startDate}\n" .
                   "📅 *Selesai Campaign*: {$endDate}\n" .
                   "📍 *Kota Asal*: " . ($kol->city_name ?? '-') . ", " . ($kol->province_name ?? '-') . "\n\n" .
                   "Terima kasih atas partisipasi Anda di Saloka AppSys!";

        try {
            $response = Http::withHeaders([
                'Authorization' => env('FONNTE_API_TOKEN'),
            ])->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            if ($response->successful()) {
                Log::info("Fonnte WA berhasil dikirim ke: {$phone}");
            } else {
                Log::error('Gagal kirim WA Fonnte: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Exception Fonnte API: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus data KOL secara permanen dari MySQL (Hard Delete)
     */
    public function destroy(mixed $id)
    {
        try {
            $kol = Kol::where('id', $id)->orWhere('uid', $id)->first();
            $kolName = $kol ? $kol->name : 'KOL';

            $deleted = $this->kolService->deleteKol($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Data KOL tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => "Data KOL \"{$kolName}\" berhasil dihapus."
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 422);
        }
    }
}