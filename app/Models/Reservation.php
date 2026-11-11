<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $table = 'reservations';

    protected $fillable = [
        'id_order',
        'kode_booking',
        'nama_pembeli',
        'email_pembeli',
        'kol_id',
        'total_bayar',
        'qty',
        'kedatangan',
        'status',
    ];

    // INI WAJIB ADA agar Reservation::with('kol') tidak error 500
    public function kol()
    {
        return $this->belongsTo(Kol::class, 'kol_id');
    }
}