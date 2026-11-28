<?php

namespace App\Http\Controllers;

use App\Models\Kol;
use App\Services\KolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

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
            $kols = $this->buildFilteredQuery($request)->latest()->get();

            $formatted = $kols->map(function ($item) {
                $resolvedType = $this->resolveCampaignType($item);

                return [
                    'id'                  => $item->id,
                    'uid'                 => $item->uid,
                    'nama_kol'            => $item->name,
                    'name'                => $item->name,
                    'username'            => $item->username,
                    'kode_referral'       => $item->referral_code,
                    'referral_code'       => $item->referral_code,
                    'nomor_telepon'       => $item->whatsapp,
                    'whatsapp'            => $item->whatsapp,
                    'kota_asal'           => $item->city_name,
                    'city_name'           => $item->city_name,
                    'provinsi'            => $item->province_name,
                    'province_name'       => $item->province_name,
                    'tipe_kol'            => $resolvedType,
                    'type'                => $resolvedType,
                    'campaign_start_date' => $item->campaign_start_date,
                    'campaign_end_date'   => $item->campaign_end_date,
                    'status'              => $item->status == 1 ? 'ACTIVE' : 'INACTIVE',
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data KOL berhasil dimuat.',
                'data'    => $formatted,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data KOL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengambil riwayat (Log History) seluruh campaign/tipe KOL berdasarkan username (Exact Match)
     */
    public function getHistory($username)
    {
        try {
            $cleanUsername = strtolower(trim(ltrim($username, '@')));

            $histories = Kol::with('campaign')
                ->whereRaw('LOWER(TRIM(LEADING "@" FROM username)) = ?', [$cleanUsername])
                ->orderByDesc('status')
                ->orderByDesc('campaign_start_date')
                ->orderByDesc('id')
                ->get();

            if ($histories->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Riwayat KOL tidak ditemukan.',
                ], 404);
            }

            $formattedHistories = $histories->map(function ($item) {
                $resolvedType = $this->resolveCampaignType($item);

                return [
                    'id'            => $item->id,
                    'uid'           => $item->uid,
                    'name'          => $item->name,
                    'username'      => $item->username,
                    'kode_referral' => $item->referral_code,
                    'whatsapp'      => $item->whatsapp,
                    'city_name'     => $item->city_name,
                    'province_name' => $item->province_name,
                    'type'          => $resolvedType,
                    'start_date'    => $item->campaign_start_date,
                    'end_date'      => $item->campaign_end_date,
                    'status'        => $item->status == 1 ? 'ACTIVE' : 'INACTIVE',
                    'created_at'    => $item->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Log history KOL berhasil dimuat.',
                'data'    => $formattedHistories,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat log history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memeriksa ketersediaan data sebelum export dilakukan
     */
    public function checkExport(Request $request)
    {
        try {
            $count = $this->buildFilteredQuery($request)->count();

            if ($count === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data KOL yang tersedia untuk diexport.',
                    'total'   => 0,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data tersedia untuk diexport.',
                'total'   => $count,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa data export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memproses data export Excel dengan OpenSpout Writer & lebar kolom otomatis
     */
    public function export(Request $request)
    {
        try {
            $kols = $this->buildFilteredQuery($request)->latest()->get();

            $fileName = 'Master_KOL_' . now()->format('Y-m-d') . '.xlsx';
            $tempPath = storage_path('app/' . $fileName);

            $options = new Options();
            $writer = new Writer($options);
            $writer->openToFile($tempPath);

            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Master KOL');

            // Atur lebar kolom secara eksplisit (1-based index: 1 = A, 2 = B, dst.)
            $sheet->setColumnWidth(6, 1);   // A: No
            $sheet->setColumnWidth(18, 2);  // B: Kode Referral
            $sheet->setColumnWidth(26, 3);  // C: Nama KOL
            $sheet->setColumnWidth(18, 4);  // D: Username
            $sheet->setColumnWidth(20, 5);  // E: No. WhatsApp
            $sheet->setColumnWidth(30, 6);  // F: Kota Asal
            $sheet->setColumnWidth(24, 7);  // G: Provinsi
            $sheet->setColumnWidth(20, 8);  // H: Tipe Campaign
            $sheet->setColumnWidth(22, 9);  // I: Campaign Start
            $sheet->setColumnWidth(22, 10); // J: Campaign End
            $sheet->setColumnWidth(14, 11); // K: Status

            $headerStyle = (new Style())->setFontBold();

            $headers = [
                'No',
                'Kode Referral',
                'Nama KOL',
                'Username',
                'No. WhatsApp',
                'Kota Asal',
                'Provinsi',
                'Tipe Campaign',
                'Campaign Start',
                'Campaign End',
                'Status',
            ];

            $writer->addRow(Row::fromValues($headers, $headerStyle));

            $no = 1;
            foreach ($kols as $item) {
                $resolvedType = $this->resolveCampaignType($item);

                $rowValues = [
                    $no++,
                    $item->referral_code ?: '-',
                    $item->name ?: '-',
                    $item->username ?: '-',
                    $item->whatsapp ? (string) $item->whatsapp : '-',
                    $item->city_name ?: '-',
                    $item->province_name ?: '-',
                    $resolvedType,
                    $item->campaign_start_date ?: '-',
                    $item->campaign_end_date ?: '-',
                    $item->status == 1 ? 'Aktif' : 'Nonaktif',
                ];

                $writer->addRow(Row::fromValues($rowValues));
            }

            $writer->close();

            return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengexport data: ' . $e->getMessage(),
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
                    'message' => 'Semua field formulir wajib diisi dengan lengkap.',
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

            $this->sendFonnteNotification($kol);

            return response()->json([
                'success' => true,
                'message' => "KOL \"{$kolName}\" berhasil ditambahkan.",
                'data'    => $kol,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'KOL gagal dibuat: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Memperbarui data KOL (Mendelegasikan versioning riwayat campaign & update profil ke KolService)
     */
    public function update(Request $request, mixed $id)
    {
        try {
            $kol = Kol::where('id', $id)->orWhere('uid', $id)->first();

            if (!$kol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data KOL tidak ditemukan di sistem.',
                ], 404);
            }

            $kolName = $kol->name ?? 'KOL';

            // Jika hanya toggle status aktif/nonaktif
            if ($request->has('status') && count($request->all()) <= 2) {
                $updatedKol = $this->kolService->updateKol($kol, ['status' => $request->input('status')]);

                return response()->json([
                    'success' => true,
                    'message' => "Status KOL \"{$kolName}\" berhasil diubah.",
                    'data'    => $updatedKol,
                ], 200);
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

            $updatedKol = $this->kolService->updateKol($kol, $data) ?? $kol->fresh();

            $this->sendFonnteNotification($updatedKol);

            return response()->json([
                'success' => true,
                'message' => "Data KOL \"{$kolName}\" berhasil diperbarui dan pesan WhatsApp terkirim.",
                'data'    => $updatedKol,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage(),
            ], 422);
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
                    'message' => 'Data KOL tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => "Data KOL \"{$kolName}\" berhasil dihapus.",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Helper query filter terpusat untuk index, checkExport, dan export
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Kol::with('campaign');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
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

        return $query;
    }

    /**
     * Helper resolusi nama/tipe campaign
     */
    private function resolveCampaignType($item): string
    {
        $campaignName = optional($item->campaign)->campaign_name
            ?? optional($item->campaign)->name
            ?? optional($item->campaign)->title;

        return $campaignName ?? ($item->type ? (is_numeric($item->type) ? 'Reguler' : $item->type) : 'Reguler');
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

        $campaignType = $this->resolveCampaignType($kol);
        $referralCode = $kol->referral_code ?? '-';
        $startDate = $kol->campaign_start_date ?? '-';
        $endDate = $kol->campaign_end_date ?? '-';

        $message = "Halo *{$kol->name}* (@{$kol->username}),\n\n" .
                   "Data Campaign KOL Anda telah diperbarui:\n" .
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
                'target'      => $phone,
                'message'     => $message,
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
}