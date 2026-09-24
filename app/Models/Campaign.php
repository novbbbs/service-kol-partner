<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $table = 'campaigns';

    protected $fillable = [
        'uid', 
        'campaign_name', 
        'status'
    ];

    /**
     * Casting tipe data kolom agar sesuai saat disimpan/diambil dari database
     */
    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Relasi ke tabel Kol
     */
    public function kols(): HasMany
    {
        return $this->hasMany(Kol::class, 'type', 'id');
    }
}