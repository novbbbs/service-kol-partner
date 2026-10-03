<?php

namespace App\Services;

use App\Models\Kol;
use App\Models\Campaign;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class KolService
{
    /**
     * Helper untuk mengubah ID angka provinsi atau teks nama provinsi menjadi teks nama provinsi
     */
    public function resolveProvinceName(mixed $provinceInput): string
    {
        if (empty($provinceInput)) {
            return '';
        }

        if (is_numeric($provinceInput)) {
            $provinceMap = [
                1 => 'Aceh',
                2 => 'Sumatera Utara',
                3 => 'Sumatera Barat',
                4 => 'Riau',
                5 => 'Jambi',
                6 => 'Sumatera Selatan',
                7 => 'Bengkulu',
                8 => 'Lampung',
                9 => 'Kepulauan Bangka Belitung',
                10 => 'Kepulauan Riau',
                11 => 'DKI Jakarta',
                12 => 'Jawa Barat',
                13 => 'Jawa Tengah',
                14 => 'DI Yogyakarta',
                15 => 'Jawa Timur',
                16 => 'Banten',
                17 => 'Bali',
                18 => 'Nusa Tenggara Barat',
                19 => 'Nusa Tenggara Timur',
                20 => 'Kalimantan Barat',
                21 => 'Kalimantan Tengah',
                22 => 'Kalimantan Selatan',
                23 => 'Kalimantan Timur',
                24 => 'Kalimantan Utara',
                25 => 'Sulawesi Utara',
                26 => 'Sulawesi Tengah',
                27 => 'Sulawesi Selatan',
                28 => 'Sulawesi Tenggara',
                29 => 'Gorontalo',
                30 => 'Sulawesi Barat',
                31 => 'Maluku',
                32 => 'Maluku Utara',
                33 => 'Papua',
                34 => 'Papua Barat'
            ];
            return $provinceMap[(int)$provinceInput] ?? '';
        }

        return trim((string)$provinceInput);
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

        // 2. Mapping field secara eksplisit
        $name = $data['name'] ?? $data['nama_kol'] ?? null;
        $username = $data['username'] ?? null;
        $whatsapp = $data['whatsapp'] ?? $data['nomor_telepon'] ?? null;
        $cityName = $data['city_name'] ?? $data['kota_asal'] ?? null;
        
        // Tangkap berbagai kemungkinan key provinsi dari request frontend
        $rawProvince = $data['province_name'] ?? $data['provinsi'] ?? $data['province'] ?? null;
        $resolvedProvinceName = $this->resolveProvinceName($rawProvince);
        
        // Fallback jika province_name masih kosong tapi ada province_id
        if (empty($resolvedProvinceName) && !empty($data['province_id'])) {
            $resolvedProvinceName = $this->resolveProvinceName($data['province_id']);
        }

        $provinceId = is_numeric($rawProvince) ? (int)$rawProvince : 13;

        $rawType = $data['type'] ?? $data['tipe_kol'] ?? 1;
        $resolvedTypeId = $this->resolveTypeId($rawType);

        $startDate = $data['campaign_start_date'] ?? $data['campaign_start'] ?? null;
        $endDate = $data['campaign_end_date'] ?? $data['campaign_end'] ?? null;

        // 3. Eksekusi Kol::create murni sesuai data yang dikirimkan
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

        if (isset($data['province_name']) || isset($data['provinsi']) || isset($data['province'])) {
            $rawProvince = $data['province_name'] ?? $data['provinsi'] ?? $data['province'];
            $resolvedProv = $this->resolveProvinceName($rawProvince);
            
            if (empty($resolvedProv) && !empty($data['province_id'])) {
                $resolvedProv = $this->resolveProvinceName($data['province_id']);
            }

            $updateData['province_name'] = $resolvedProv;
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