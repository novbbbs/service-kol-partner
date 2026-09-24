<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'nama_kol' => $this->name,
            'name' => $this->name,
            'username' => $this->username,
            'kode_referral' => $this->referral_code,
            'referral_code' => $this->referral_code,
            'nomor_telepon' => $this->whatsapp,
            'whatsapp' => $this->whatsapp,
            'kota_asal' => $this->city_name,
            'city_name' => $this->city_name,
            'provinsi' => $this->province_name,
            'province_name' => $this->province_name,
            'tipe_kol' => optional($this->campaign)->campaign_name ?? $this->type,
            'type' => optional($this->campaign)->campaign_name ?? $this->type,
            'campaign_start_date' => $this->campaign_start_date,
            'campaign_end_date' => $this->campaign_end_date,
            'status' => $this->status == 1 ? 'ACTIVE' : 'INACTIVE',
        ];
    }
}