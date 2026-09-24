<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Str;

class CampaignService
{
    public function getAllCampaigns($request)
    {
        $query = Campaign::query();

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('campaign_name', 'like', "%{$search}%");
        }

        if ($request->has('status') && $request->status !== 'Semua') {
            $statusVal = ($request->status === 'Aktif' || $request->status === '1') ? 1 : 0;
            $query->where('status', $statusVal);
        }

        return $query->latest()->get();
    }

    public function createCampaign(array $data)
    {
        return Campaign::create([
            'uid' => (string) Str::uuid(),
            'campaign_name' => $data['campaign_name'],
            'status' => $data['status'] ?? 1,
        ]);
    }

    public function updateCampaign(Campaign $campaign, array $data)
    {
        $campaign->update([
            'campaign_name' => $data['campaign_name'] ?? $campaign->campaign_name,
            'status' => $data['status'] ?? $campaign->status,
        ]);
        return $campaign;
    }

    public function deleteCampaign(Campaign $campaign)
    {
        return $campaign->delete();
    }
}