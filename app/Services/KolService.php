<?php

namespace App\Services;

use App\Models\Kol;
use App\Models\Campaign;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class KolService
{
    /**
     * Helper untuk mengubah ID angka provinsi menjadi teks nama provinsi secara lengkap (1-34)
     */
    public function resolveProvinceName(mixed $provinceInput): string
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
     * Helper untuk mengonversi type campaign teks/angka menjadi ID campaign yang valid di database
     */
    public function resolveTypeId(mixed $typeInput): int
    {
        if (is_numeric($typeInput)) {
            $exists = Campaign::where('id', (int)$typeInput)->exists();
            if ($exists) {
                return (int)$typeInput;
            }
        }

        $campaign = Campaign::where('campaign_name', 'like', "%{$typeInput}%")->first();

        if ($campaign) {
            return $campaign->id;
        }

        $defaultCampaign = Campaign::first();
        return $defaultCampaign ? $defaultCampaign->id : 1;
    }

    public function getAllKols()
    {
        return Kol::with('campaign')->latest()->get();
    }

    public function createKol(array $data)
    {
        // 1. Generate otomatis referral_code jika belum ada
        $referralCode = $data['referral_code'] ?? $data['kode_referral'] ?? null;
        if (empty($referralCode)) {
            $letters = strtoupper(Str::random(3));
            $numbers = mt_rand(10000, 99999);
            $referralCode = $letters . $numbers;
        }

        // 2. Mapping field secara eksplisit agar aman dari field tidak dikenal
        $name = $data['name'] ?? $data['nama_kol'] ?? null;
        $username = $data['username'] ?? null;
        $whatsapp = $data['whatsapp'] ?? $data['nomor_telepon'] ?? null;
        $cityName = $data['city_name'] ?? $data['kota_asal'] ?? null;
        
        $rawProvince = $data['province_name'] ?? $data['provinsi'] ?? null;
        $resolvedProvinceName = $this->resolveProvinceName($rawProvince);
        $provinceId = is_numeric($rawProvince) ? (int)$rawProvince : 13;

        $rawType = $data['type'] ?? $data['tipe_kol'] ?? 1;
        $resolvedTypeId = $this->resolveTypeId($rawType);

        $startDate = $data['campaign_start_date'] ?? $data['campaign_start'] ?? null;
        $endDate = $data['campaign_end_date'] ?? $data['campaign_end'] ?? null;

        // 3. Eksekusi Kol::create dengan data yang sudah bersih
        return Kol::create([
            'uid' => (string) Str::uuid(),
            'referral_code' => $referralCode,
            'name' => $name,
            'username' => $username,
            'whatsapp' => $whatsapp,
            'province_id' => $provinceId,
            'province_name' => $resolvedProvinceName,
            'city_id' => 1,
            'city_name' => $cityName,
            'type' => $resolvedTypeId,
            'campaign_start_date' => $startDate,
            'campaign_end_date' => $endDate,
            'status' => 1,
        ]);
    }

    public function updateKol(mixed $kolOrId, array $data)
    {
        if (!$kolOrId instanceof Kol) {
            $kol = Kol::where('id', $kolOrId)
                ->orWhere('id', (int)$kolOrId)
                ->orWhere('uid', (string)$kolOrId)
                ->first();
        } else {
            $kol = $kolOrId;
        }

        if (!$kol) {
            return null;
        }

        $updateData = [];

        if (isset($data['name']) || isset($data['nama_kol'])) {
            $updateData['name'] = $data['name'] ?? $data['nama_kol'];
        }
        if (isset($data['username'])) {
            $updateData['username'] = $data['username'];
        }
        if (isset($data['whatsapp']) || isset($data['nomor_telepon'])) {
            $updateData['whatsapp'] = $data['whatsapp'] ?? $data['nomor_telepon'];
        }
        if (isset($data['city_name']) || isset($data['kota_asal'])) {
            $updateData['city_name'] = $data['city_name'] ?? $data['kota_asal'];
        }

        if (isset($data['province_name']) || isset($data['provinsi'])) {
            $rawProvince = $data['province_name'] ?? $data['provinsi'];
            $updateData['province_name'] = $this->resolveProvinceName($rawProvince);
            $updateData['province_id'] = is_numeric($rawProvince) ? (int)$rawProvince : $kol->province_id;
        }

        if (isset($data['type']) || isset($data['tipe_kol'])) {
            $rawType = $data['type'] ?? $data['tipe_kol'];
            $updateData['type'] = $this->resolveTypeId($rawType);
        }

        if (isset($data['campaign_start_date']) || isset($data['campaign_start'])) {
            $updateData['campaign_start_date'] = $data['campaign_start_date'] ?? $data['campaign_start'];
        }

        if (isset($data['campaign_end_date']) || isset($data['campaign_end'])) {
            $updateData['campaign_end_date'] = $data['campaign_end_date'] ?? $data['campaign_end'];
        }

        $kol->update($updateData);
        return $kol;
    }

    /**
     * Menghapus KOL secara permanen dari tabel MySQL
     */
    public function deleteKol(mixed $kolOrId): bool
    {
        $id = $kolOrId instanceof Kol ? $kolOrId->id : $kolOrId;

        $kol = Kol::where('id', $id)
            ->orWhere('id', (int)$id)
            ->orWhere('uid', (string)$id)
            ->first();

        if (!$kol) {
            return false;
        }

        return DB::table('kols')->where('id', $kol->id)->delete() > 0;
    }
}