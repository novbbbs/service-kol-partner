<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    /**
     * Helper privat untuk mengonversi berbagai format status menjadi integer (1 atau 0)
     */
    private function parseStatusValue(mixed $status, int $default = 1): int
    {
        if ($status === null || $status === '') {
            return $default;
        }

        $strStatus = strtolower(trim((string) $status));

        if (in_array($strStatus, ['1', 'active', 'aktif', 'true'], true)) {
            return 1;
        }

        if (in_array($strStatus, ['0', 'non active', 'non-active', 'nonaktif', 'false'], true)) {
            return 0;
        }

        return is_numeric($status) ? (int) $status : $default;
    }

    public function index(Request $request)
    {
        $query = Campaign::query();

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('campaign_name', 'like', "%{$search}%");
        }

        if ($request->has('status') && $request->status !== 'Semua') {
            $statusVal = $this->parseStatusValue($request->status, 1);
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
            'status' => 'nullable'
        ]);

        $statusValue = $this->parseStatusValue($request->status, 1);

        $campaign = Campaign::create([
            'uid' => (string) Str::uuid(),
            'campaign_name' => $request->campaign_name,
            'status' => $statusValue,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Campaign berhasil ditambahkan',
            'data' => $campaign
        ], 201);
    }

    public function update(Request $request, mixed $id)
    {
        // Mendukung pencarian berdasarkan ID integer atau UUID string
        $campaign = Campaign::where('id', $id)->orWhere('uid', $id)->first();
        if (!$campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign tidak ditemukan'], 404);
        }

        // Tangkap nama campaign jika diisi
        $campaignName = ($request->has('campaign_name') && $request->campaign_name !== null && $request->campaign_name !== '')
            ? $request->campaign_name
            : $campaign->campaign_name;

        // Tangkap dan konversi status dari frontend
        $statusValue = $request->has('status') 
            ? $this->parseStatusValue($request->status, (int) $campaign->status)
            : (int) $campaign->status;

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
        // Mendukung hapus berdasarkan ID integer atau UUID string
        $campaign = Campaign::where('id', $id)->orWhere('uid', $id)->first();
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