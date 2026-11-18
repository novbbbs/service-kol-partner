<?php

namespace App\Services;

use App\Models\Kol;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getSummary(string $programType, ?string $campaignName): array
    {
        // 1. QUERY UNTUK SISI KIRI (AKTUAL / AKTIF) -> Status 1
        $queryAktual = Kol::with('campaign')
            ->where(function($sQuery) {
                $sQuery->where('status', 1)
                       ->orWhere('status', '1');
            });

        // 2. QUERY UNTUK SISI KANAN (POTENSIAL / TOTAL KESELURUHAN) -> Tanpa filter status (ambil semua)
        $queryPotensial = Kol::with('campaign');

        // Terapkan filter programType & campaignName ke kedua query
        $filterLogic = function($query, $programType, $campaignName) {
            if (strtolower($programType) === 'reguler') {
                $query->where(function($q) {
                    $q->where(function($sub) {
                        $sub->whereNull('type')
                            ->orWhere('type', '')
                            ->orWhere('type', '0')
                            ->orWhereRaw('LOWER(TRIM(type)) LIKE ?', ['%reguler%']);
                    })->orWhereHas('campaign', function($cQuery) {
                        $cQuery->where(function($cq) {
                                   $cq->whereRaw('LOWER(TRIM(campaign_name)) LIKE ?', ['%reguler%'])
                                      ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ['%reguler%']);
                               });
                    });
                });
            } elseif (strtolower($programType) === 'event') {
                $query->where(function($q) {
                    $q->whereNotNull('type')
                      ->where('type', '!=', '')
                      ->where('type', '!=', '0')
                      ->whereRaw('LOWER(TRIM(type)) NOT LIKE ?', ['%reguler%']);
                });

                if (!empty($campaignName)) {
                    $cleanCampaign = strtolower(trim($campaignName));
                    $query->where(function($q) use ($cleanCampaign) {
                        $q->whereRaw('LOWER(TRIM(type)) LIKE ?', ["%{$cleanCampaign}%"])
                          ->orWhereHas('campaign', function($cQuery) use ($cleanCampaign) {
                              $cQuery->where(function($cq) use ($cleanCampaign) {
                                         $cq->whereRaw('LOWER(TRIM(campaign_name)) LIKE ?', ["%{$cleanCampaign}%"])
                                            ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ["%{$cleanCampaign}%"]);
                                     });
                          });
                    });
                }
            }
        };

        // Jalankan filter untuk aktual & potensial
        $filterLogic($queryAktual, $programType, $campaignName);
        $filterLogic($queryPotensial, $programType, $campaignName);

        // Hitung nilai Aktual (kiri) dan Potensial (kanan)
        $kolAktual = (clone $queryAktual)->count();
        $kolPotensial = (clone $queryPotensial)->count();

        $reservasiAktual = $kolAktual * 35;
        $reservasiPotensial = $kolPotensial * 35;

        $visitorAktual = $kolAktual * 1280;
        $visitorPotensial = $kolPotensial * 1280;

        $top5Kol = (clone $queryAktual)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get(['id', 'name as nama_kol', 'username', 'city_name as kota_asal', 'province_name', 'status']);

        $top5City = (clone $queryAktual)
            ->select('city_name', 'province_name', DB::raw('count(*) as total_kol'))
            ->groupBy('city_name', 'province_name')
            ->orderBy('total_kol', 'desc')
            ->take(5)
            ->get();

        $top5City = $top5City->map(function($item) {
            $item->total_visitor = $item->total_kol * 650;
            return $item;
        });

        // Kembalikan format data terpisah agar sesuai dengan pemanggilan di frontend (DashboardPage.tsx)
        return [
            'success' => true,
            'program_type' => $programType,
            'selected_campaign' => $campaignName,
            'metrics' => [
                'total_kol_aktif' => $kolAktual,
                'total_kol_potensial' => $kolPotensial,
                'total_reservasi_aktif' => $reservasiAktual,
                'total_reservasi_potensial' => $reservasiPotensial,
                'total_visitor_aktif' => $visitorAktual,
                'total_visitor_potensial' => $visitorPotensial,
            ],
            'top_5_kol' => $top5Kol,
            'top_5_city' => $top5City,
        ];
    }
}