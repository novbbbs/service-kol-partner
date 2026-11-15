use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Kol;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Menjalankan otomatis penonaktifan KOL setiap hari saat campaign_end_date habis
Schedule::call(function () {
    Kol::where('status', 1)
        ->whereDate('campaign_end_date', '<', now()->toDateString())
        ->update(['status' => 0]);
})->daily();