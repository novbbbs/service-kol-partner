<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kol extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kols';

    protected $fillable = [
        'uid', 
        'referral_code', 
        'name', 
        'username', 
        'whatsapp',
        'province_id', 
        'province_name', 
        'city_id', 
        'city_name',
        'type', 
        'campaign_start_date', 
        'campaign_end_date', 
        'status'
    ];

    /**
     * Casting tipe data kolom agar sesuai saat disimpan/diambil dari database
     */
    protected $casts = [
        'status' => 'integer',
        'province_id' => 'integer',
        'city_id' => 'integer',
        'type' => 'integer',
        'campaign_start_date' => 'date:Y-m-d',
        'campaign_end_date' => 'date:Y-m-d',
    ];

    /**
     * Relasi ke tabel Campaign
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'type', 'id');
    }
}