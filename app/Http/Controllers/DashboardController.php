<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index(Request $request): JsonResponse
    {
        $programType = strtolower($request->query('type', 'reguler')); 
        
        // Menangkap parameter campaign secara fleksibel (mendukung 'campaign' maupun 'campaign_name')
        $campaignName = $request->query('campaign') ?? $request->query('campaign_name'); 

        // Panggil logika dari DashboardService
        $data = $this->dashboardService->getSummary($programType, $campaignName);

        return response()->json($data);
    }

    // Menambahkan alias method summary jika route Anda mengarah ke 'summary'
    public function summary(Request $request): JsonResponse
    {
        return $this->index($request);
    }
}