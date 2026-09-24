<?php

namespace App\Services;

use App\Models\Kol;
use Illuminate\Support\Str;

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
     * Helper untuk mengonversi type campaign teks menjadi integer ID
     */
    public function resolveTypeId(mixed $typeInput): int
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

    public function getAllKols()
    {
        return Kol::with('campaign')->latest()->get();
    }

    public function createKol(array $data)
    {
        // Generate otomatis referral_code (3 huruf kapital + 5 angka acak) jika belum ada
        if (!isset($data['referral_code']) && !isset($data['kode_referral'])) {
            $letters = strtoupper(Str::random(3));
            $numbers = mt_rand(10000, 99999);
            $data['referral_code'] = $letters . $numbers;
        } else {
            $data['referral_code'] = $data['referral_code'] ?? $data['kode_referral'];
        }

        // Mapping field alternatif dari frontend jika diperlukan
        $data['uid'] = $data['uid'] ?? (string) Str::uuid();
        $data['name'] = $data['name'] ?? $data['nama_kol'] ?? null;
        $data['whatsapp'] = $data['whatsapp'] ?? $data['nomor_telepon'] ?? null;
        $data['city_name'] = $data['city_name'] ?? $data['kota_asal'] ?? null;
        
        $rawProvince = $data['province_name'] ?? $data['provinsi'] ?? null;
        $data['province_name'] = $this->resolveProvinceName($rawProvince);
        $data['province_id'] = is_numeric($rawProvince) ? (int)$rawProvince : 13;

        // Konversi type campaign teks menjadi integer ID
        $rawType = $data['type'] ?? $data['tipe_kol'] ?? 1;
        $data['type'] = $this->resolveTypeId($rawType);

        $data['campaign_start_date'] = $data['campaign_start_date'] ?? $data['campaign_start'] ?? null;
        $data['campaign_end_date'] = $data['campaign_end_date'] ?? $data['campaign_end'] ?? null;

        // Status integer (1 untuk Active/Aktif)
        $data['status'] = 1;
        $data['city_id'] = $data['city_id'] ?? 1;

        return Kol::create($data);
    }

    public function updateKol(Kol $kol, array $data)
    {
        if (isset($data['nama_kol'])) {
            $data['name'] = $data['nama_kol'];
        }
        if (isset($data['nomor_telepon'])) {
            $data['whatsapp'] = $data['nomor_telepon'];
        }
        if (isset($data['kota_asal'])) {
            $data['city_name'] = $data['kota_asal'];
        }

        if (isset($data['province_name']) || isset($data['provinsi'])) {
            $rawProvince = $data['province_name'] ?? $data['provinsi'];
            $data['province_name'] = $this->resolveProvinceName($rawProvince);
            $data['province_id'] = is_numeric($rawProvince) ? (int)$rawProvince : $kol->province_id;
        }

        if (isset($data['type']) || isset($data['tipe_kol'])) {
            $rawType = $data['type'] ?? $data['tipe_kol'];
            $data['type'] = $this->resolveTypeId($rawType);
        }

        $kol->update($data);
        return $kol;
    }

    public function deleteKol(Kol $kol)
    {
        return $kol->delete();
    }
}