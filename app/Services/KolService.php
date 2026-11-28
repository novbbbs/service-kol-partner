<?php

namespace App\Services;

use App\Models\Kol;
use App\Models\Campaign;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KolService
{
    private function logActivity(string $description, ?int $subjectId = null)
    {
        try {
            if (DB::getSchemaBuilder()->hasTable('activity_logs')) {
                DB::table('activity_logs')->insert([
                    'description' => $description,
                    'subject_type' => Kol::class,
                    'subject_id' => $subjectId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Gagal mencatat activity log: ' . $e->getMessage());
        }
    }

    private function getReferralCodeMaxLength(): int
    {
        try {
            $columnInfo = DB::selectOne("
                SELECT CHARACTER_MAXIMUM_LENGTH AS max_len
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'kols'
                  AND COLUMN_NAME = 'referral_code'
            ");

            if ($columnInfo && !empty($columnInfo->max_len)) {
                return (int) $columnInfo->max_len;
            }
        } catch (\Exception $e) {
            // Abaikan jika bukan MySQL/information_schema tidak tersedia
        }

        return 15;
    }

    private function buildArchivedReferralCode(?string $currentCode, int|string $kolId): string
    {
        $maxLen = $this->getReferralCodeMaxLength();
        $cleanBase = preg_replace('/-(HIST|H)\d+$/i', '', (string) $currentCode);
        $suffix = '-H' . $kolId;

        if (strlen($suffix) >= $maxLen) {
            return substr((string) $kolId, -$maxLen);
        }

        $allowedBaseLen = $maxLen - strlen($suffix);
        return substr($cleanBase, 0, $allowedBaseLen) . $suffix;
    }

    private function getProvinceMapping(mixed $input): array
    {
        $provinceMap = [
            1 => 'Aceh', 2 => 'Sumatera Utara', 3 => 'Sumatera Barat', 4 => 'Riau', 5 => 'Jambi',
            6 => 'Sumatera Selatan', 7 => 'Bengkulu', 8 => 'Lampung', 9 => 'Kepulauan Bangka Belitung', 10 => 'Kepulauan Riau',
            11 => 'DKI Jakarta', 12 => 'Jawa Barat', 13 => 'Jawa Tengah', 14 => 'DI Yogyakarta', 15 => 'Jawa Timur',
            16 => 'Banten', 17 => 'Bali', 18 => 'Nusa Tenggara Barat', 19 => 'Nusa Tenggara Timur', 20 => 'Kalimantan Barat',
            21 => 'Kalimantan Tengah', 22 => 'Kalimantan Selatan', 23 => 'Kalimantan Timur', 24 => 'Kalimantan Utara', 25 => 'Sulawesi Utara',
            26 => 'Sulawesi Tengah', 27 => 'Sulawesi Selatan', 28 => 'Sulawesi Tenggara', 29 => 'Gorontalo', 30 => 'Sulawesi Barat',
            31 => 'Maluku', 32 => 'Maluku Utara', 33 => 'Papua', 34 => 'Papua Barat'
        ];

        if (empty($input)) {
            return ['id' => 13, 'name' => 'Jawa Tengah'];
        }

        if (is_numeric($input)) {
            $id = (int) $input;
            return [
                'id' => $id,
                'name' => $provinceMap[$id] ?? 'Jawa Tengah'
            ];
        }

        $name = trim((string) $input);
        $matchedId = 13;
        foreach ($provinceMap as $id => $provName) {
            if (strcasecmp($provName, $name) === 0) {
                $matchedId = $id;
                break;
            }
        }

        return [
            'id' => $matchedId,
            'name' => $name
        ];
    }

    public function resolveTypeId(mixed $typeInput): int
    {
        if (is_numeric($typeInput)) {
            $exists = Campaign::where('id', (int) $typeInput)->exists();
            if ($exists) {
                return (int) $typeInput;
            }
        }

        $campaignName = is_string($typeInput) ? trim($typeInput) : '';

        if (!empty($campaignName)) {
            $campaign = Campaign::whereRaw('LOWER(TRIM(campaign_name)) = ?', [strtolower($campaignName)])->first()
                ?? Campaign::where('campaign_name', 'like', "%{$campaignName}%")->first();
            if ($campaign) {
                return $campaign->id;
            }
        }

        $defaultCampaign = Campaign::first();
        return $defaultCampaign ? $defaultCampaign->id : 1;
    }

    private function resolveCityId(?string $cityName, int $fallbackId = 1): int
    {
        try {
            if (!empty($cityName)) {
                $tableName = DB::getSchemaBuilder()->hasTable('regencies')
                    ? 'regencies'
                    : (DB::getSchemaBuilder()->hasTable('cities') ? 'cities' : null);

                if ($tableName) {
                    $cityRecord = DB::table($tableName)
                        ->where('name', 'like', "%{$cityName}%")
                        ->first();
                    if ($cityRecord) {
                        return (int) $cityRecord->id;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback aman
        }

        return $fallbackId;
    }

    public function getAllKols(?array $filters = [])
    {
        $query = Kol::with('campaign');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('referral_code', 'like', "%{$search}%")
                  ->orWhere('whatsapp', 'like', "%{$search}%")
                  ->orWhere('city_name', 'like', "%{$search}%")
                  ->orWhere('province_name', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && !in_array($filters['status'], ['Semua', 'Semua Status', ''])) {
            $statusVal = (strtoupper($filters['status']) === 'ACTIVE' || $filters['status'] === '1') ? 1 : 0;
            $query->where('status', $statusVal);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('campaign_start_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('campaign_end_date', '<=', $filters['end_date']);
        }

        return $query->latest()->get();
    }

    public function createKol(array $data)
    {
        unset($data['id'], $data['uid'], $data['referral_code'], $data['kode_referral']);

        do {
            $letters = '';
            for ($i = 0; $i < 3; $i++) {
                $letters .= chr(mt_rand(65, 90));
            }
            $numbers = mt_rand(10000, 99999);
            $referralCode = $letters . $numbers;

            $exists = Kol::where('referral_code', $referralCode)->exists();
        } while ($exists);

        $name = $data['name'] ?? $data['nama_kol'] ?? null;
        $username = $data['username'] ?? null;
        $whatsapp = $data['whatsapp'] ?? $data['nomor_telepon'] ?? null;
        $cityName = $data['city_name'] ?? $data['kota_asal'] ?? null;

        $rawProvince = $data['province_name'] ?? $data['provinsi'] ?? $data['province'] ?? $data['province_id'] ?? null;
        $resolvedProvince = $this->getProvinceMapping($rawProvince);
        $provinceId = $resolvedProvince['id'];
        $resolvedProvinceName = $resolvedProvince['name'];

        $cityId = $this->resolveCityId($cityName, 1);

        $rawType = $data['type'] ?? $data['tipe_kol'] ?? 1;
        $resolvedTypeId = $this->resolveTypeId($rawType);

        $startDate = $data['campaign_start_date'] ?? $data['campaign_start'] ?? null;
        $endDate = $data['campaign_end_date'] ?? $data['campaign_end'] ?? null;

        $kol = Kol::create([
            'uid' => (string) Str::uuid(),
            'referral_code' => $referralCode,
            'name' => $name,
            'username' => $username,
            'whatsapp' => $whatsapp,
            'province_id' => $provinceId,
            'province_name' => $resolvedProvinceName,
            'city_id' => $cityId,
            'city_name' => $cityName,
            'type' => $resolvedTypeId,
            'campaign_start_date' => $startDate,
            'campaign_end_date' => $endDate,
            'status' => isset($data['status']) ? (int) $data['status'] : 1,
        ]);

        $this->logActivity("Menambahkan data KOL baru: {$name} (Kode: {$referralCode})", $kol->id);

        return $kol;
    }

    public function updateKol(mixed $kolOrId, array $data)
    {
        if (!$kolOrId instanceof Kol) {
            $kol = Kol::where('id', $kolOrId)
                ->orWhere('id', (int) $kolOrId)
                ->orWhere('uid', (string) $kolOrId)
                ->first();
        } else {
            $kol = $kolOrId;
        }

        if (!$kol) {
            return null;
        }

        // Jika hanya mengubah status (toggle)
        if (array_key_exists('status', $data) && count($data) === 1) {
            $rawStatus = $data['status'];
            $statusVal = in_array(strtolower(trim((string) $rawStatus)), ['1', 'active', 'aktif', 'true'], true) ? 1 : 0;
            $kol->update(['status' => $statusVal]);

            $statusText = $statusVal === 1 ? 'ACTIVE' : 'INACTIVE';
            $this->logActivity("Mengubah status KOL '{$kol->name}' menjadi {$statusText}", $kol->id);
            return $kol->fresh();
        }

        $newName = $data['name'] ?? $data['nama_kol'] ?? $kol->name;
        $newUsername = $data['username'] ?? $kol->username;
        $newWhatsapp = $data['whatsapp'] ?? $data['nomor_telepon'] ?? $kol->whatsapp;
        $newCityName = $data['city_name'] ?? $data['kota_asal'] ?? $kol->city_name;
        $newCityId = (isset($data['city_name']) || isset($data['kota_asal']))
            ? $this->resolveCityId($newCityName, (int) ($kol->city_id ?? 1))
            : (int) ($kol->city_id ?? 1);

        if (isset($data['province_name']) || isset($data['provinsi']) || isset($data['province']) || isset($data['province_id'])) {
            $rawProvince = $data['province_name'] ?? $data['provinsi'] ?? $data['province'] ?? $data['province_id'];
            $resolvedProv = $this->getProvinceMapping($rawProvince);
            $newProvinceName = $resolvedProv['name'];
            $newProvinceId = $resolvedProv['id'];
        } else {
            $newProvinceName = $kol->province_name;
            $newProvinceId = (int) ($kol->province_id ?? 13);
        }

        $newTypeId = (isset($data['type']) || isset($data['tipe_kol']))
            ? $this->resolveTypeId($data['type'] ?? $data['tipe_kol'])
            : (int) $kol->type;

        $newStartDate = $data['campaign_start_date'] ?? $data['campaign_start'] ?? $kol->campaign_start_date;
        $newEndDate = $data['campaign_end_date'] ?? $data['campaign_end'] ?? $kol->campaign_end_date;

        $newStatus = array_key_exists('status', $data)
            ? (in_array(strtolower(trim((string) $data['status'])), ['1', 'active', 'aktif', 'true'], true) ? 1 : 0)
            : (int) $kol->status;

        $oldStart = $kol->campaign_start_date ? substr((string) $kol->campaign_start_date, 0, 10) : null;
        $cmpNewStart = $newStartDate ? substr((string) $newStartDate, 0, 10) : null;

        $oldEnd = $kol->campaign_end_date ? substr((string) $kol->campaign_end_date, 0, 10) : null;
        $cmpNewEnd = $newEndDate ? substr((string) $newEndDate, 0, 10) : null;

        $isCampaignChanged = ((int) $kol->type !== $newTypeId)
            || ($oldStart !== $cmpNewStart)
            || ($oldEnd !== $cmpNewEnd);

        // Jika tipe campaign atau tanggal berubah saat KOL sedang aktif:
        // Nonaktifkan record lama (status = 0) agar tersimpan sebagai riwayat, lalu buat record baru yang aktif
        if ($isCampaignChanged && (int) $kol->status === 1) {
            return DB::transaction(function () use (
                $kol, $newName, $newUsername, $newWhatsapp,
                $newProvinceId, $newProvinceName, $newCityId, $newCityName,
                $newTypeId, $newStartDate, $newEndDate, $newStatus
            ) {
                $activeReferralCode = preg_replace('/-(HIST|H)\d+$/i', '', (string) $kol->referral_code);
                $archivedReferralCode = $this->buildArchivedReferralCode($activeReferralCode, $kol->id);

                // Arsipkan baris lama (nonaktif) & bebaskan kode referral utama agar tidak bentrok UNIQUE
                $kol->update([
                    'status' => 0,
                    'referral_code' => $archivedReferralCode,
                ]);

                $newKol = Kol::create([
                    'uid' => (string) Str::uuid(),
                    'referral_code' => $activeReferralCode,
                    'name' => $newName,
                    'username' => $newUsername,
                    'whatsapp' => $newWhatsapp,
                    'province_id' => $newProvinceId,
                    'province_name' => $newProvinceName,
                    'city_id' => $newCityId,
                    'city_name' => $newCityName,
                    'type' => $newTypeId,
                    'campaign_start_date' => $newStartDate,
                    'campaign_end_date' => $newEndDate,
                    'status' => $newStatus,
                ]);

                $this->logActivity("Memperbarui campaign KOL '{$newName}' (riwayat lama diarsipkan)", $newKol->id);

                return $newKol;
            });
        }

        // Jika hanya edit profil tanpa mengubah tipe/tanggal campaign, perbarui baris yang sama
        $kol->update([
            'name' => $newName,
            'username' => $newUsername,
            'whatsapp' => $newWhatsapp,
            'province_id' => $newProvinceId,
            'province_name' => $newProvinceName,
            'city_id' => $newCityId,
            'city_name' => $newCityName,
            'type' => $newTypeId,
            'campaign_start_date' => $newStartDate,
            'campaign_end_date' => $newEndDate,
            'status' => $newStatus,
        ]);

        $this->logActivity("Memperbarui data KOL: {$newName}", $kol->id);

        return $kol->fresh();
    }

    public function deleteKol(mixed $kolOrId): bool
    {
        $id = $kolOrId instanceof Kol ? $kolOrId->id : $kolOrId;

        $kol = Kol::where('id', $id)
            ->orWhere('id', (int) $id)
            ->orWhere('uid', (string) $id)
            ->first();

        if (!$kol) {
            return false;
        }

        $kolName = $kol->name;
        $kolId = $kol->id;

        $deleted = DB::table('kols')->where('id', $kol->id)->delete() > 0;

        if ($deleted) {
            $this->logActivity("Menghapus data KOL: {$kolName}", $kolId);
        }

        return $deleted;
    }
}