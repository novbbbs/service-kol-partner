<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Kol; // Sesuaikan dengan model KOL Anda
use Carbon\Carbon;

class DeactivateExpiredKols extends Command
{
    protected $signature = 'kols:deactivate-expired';
    protected $description = 'Menonaktifkan KOL secara otomatis jika campaign_end_date sudah lewat';

    public function handle()
    {
        $today = Carbon::today()->toDateString();

        // Cari KOL yang statusnya masih aktif (1) tapi tanggal selesainya sudah < hari ini
        $expiredCount = Kol::where('status', 1)
            ->whereDate('campaign_end_date', '<', $today)
            ->update(['status' => 0]); // Ubah status jadi non-aktif (0)

        $this->info("Berhasil menonaktifkan {$expiredCount} KOL yang telah habis masa periodenya.");
    }
}