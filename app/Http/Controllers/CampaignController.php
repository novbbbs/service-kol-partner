<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    public function index(Request $request)
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

        $campaigns = $query->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar campaign berhasil diambil',
            'data' => $campaigns
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'campaign_name' => 'required|string|max:150',
            'status' => 'nullable|integer'
        ]);

        $campaign = Campaign::create([
            'uid' => (string) Str::uuid(),
            'campaign_name' => $request->campaign_name,
            'status' => $request->status !== null ? (int) $request->status : 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Campaign berhasil ditambahkan',
            'data' => $campaign
        ], 201);
    }

    public function update(Request $request, mixed $id)
    {
        $campaign = Campaign::find($id);
        if (!$campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign tidak ditemukan'], 404);
        }

        // Pastikan status tidak bernilai null dengan memeriksa apakah request mengirimkan status (termasuk angka 0)
        $statusValue = ($request->has('status') && $request->status !== null && $request->status !== '')
            ? (int) $request->status
            : $campaign->status;

        $campaignName = ($request->has('campaign_name') && $request->campaign_name !== null && $request->campaign_name !== '')
            ? $request->campaign_name
            : $campaign->campaign_name;

        $campaign->update([
            'campaign_name' => $campaignName,
            'status' => $statusValue,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Campaign berhasil diperbarui',
            'data' => $campaign
        ], 200);
    }

    public function destroy(mixed $id)
    {
        $campaign = Campaign::find($id);
        if (!$campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign tidak ditemukan'], 404);
        }

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign berhasil dihapus'
        ], 200);
    }
}