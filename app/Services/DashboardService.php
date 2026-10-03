<?php

namespace App\Services;

use App\Models\Kol;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getSummary(string $programType, ?string $campaignName): array
    {
        $query = Kol::with('campaign');

        if (strtolower($programType) === 'reguler') {
            // Reguler: Cek jika kolom type bernilai 0, kosong, teks 'reguler', atau relasi campaign-nya bernama 'reguler'
            $query->where(function($q) {
                $q->whereNull('type')
                  ->orWhere('type', '')
                  ->orWhere('type', '0')
                  ->orWhereRaw('LOWER(TRIM(type)) LIKE ?', ['%reguler%'])
                  ->orWhereHas('campaign', function($cQuery) {
                      $cQuery->whereRaw('LOWER(TRIM(campaign_name)) LIKE ?', ['%reguler%'])
                            ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ['%reguler%']);
                  });
            });
        } elseif (strtolower($programType) === 'event') {
            // Event: Pastikan bukan reguler (baik dari teks type, angka ID, maupun relasi campaign)
            $query->where(function($q) {
                $q->whereNotNull('type')
                  ->where('type', '!=', '')
                  ->where('type', '!=', '0')
                  ->whereRaw('LOWER(TRIM(type)) NOT LIKE ?', ['%reguler%']);
            });

            if (!empty($campaignName)) {
                $cleanCampaign = strtolower(trim($campaignName));
                
                // Filter berdasarkan nama campaign pada relasi atau kecocokan teks type
                $query->where(function($q) use ($cleanCampaign) {
                    $q->whereRaw('LOWER(TRIM(type)) LIKE ?', ["%{$cleanCampaign}%"])
                      ->orWhereHas('campaign', function($cQuery) use ($cleanCampaign) {
                          $cQuery->whereRaw('LOWER(TRIM(campaign_name)) LIKE ?', ["%{$cleanCampaign}%"])
                                ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ["%{$cleanCampaign}%"]);
                      });
                });
            }
        }

        $totalKol = (clone $query)->count();
        $totalReservasi = $totalKol * 35; 
        $totalVisitor = $totalKol * 1280; 

        $top5Kol = (clone $query)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get(['id', 'name as nama_kol', 'username', 'city_name as kota_asal', 'province_name']);

        $top5City = (clone $query)
            ->select('city_name', 'province_name', DB::raw('count(*) as total_kol'))
            ->groupBy('city_name', 'province_name')
            ->orderBy('total_kol', 'desc')
            ->take(5)
            ->get();

        $top5City = $top5City->map(function($item) {
            $item->total_visitor = $item->total_kol * 650;
            return $item;
        });

        return [
            'success' => true,
            'program_type' => $programType,
            'selected_campaign' => $campaignName,
            'metrics' => [
                'total_kol' => $totalKol,
                'total_reservasi' => $totalReservasi,
                'total_visitor' => $totalVisitor,
            ],
            'top_5_kol' => $top5Kol,
            'top_5_city' => $top5City,
        ];
    }
}