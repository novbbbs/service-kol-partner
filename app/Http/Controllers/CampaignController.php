<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    private const BASE_TICKET_PRICE = 30000;

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
            $query->where(function ($q) use ($search) {
                $q->where('campaign_name', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && !in_array($request->status, ['Semua', 'Semua Status', ''])) {
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
            'discount' => 'required|integer|min:0|max:' . self::BASE_TICKET_PRICE,
            'status' => 'nullable'
        ], [
            'discount.min' => 'Potongan harga tidak boleh kurang dari Rp 0.',
            'discount.max' => 'Potongan harga tidak boleh melebihi harga acuan (Rp ' . number_format(self::BASE_TICKET_PRICE, 0, ',', '.') . ').',
        ]);

        $statusValue = $this->parseStatusValue($request->status, 1);
        $discountValue = (int) $request->discount;

        $campaign = Campaign::create([
            'uid' => (string) Str::uuid(),
            'campaign_name' => $request->campaign_name,
            'discount' => $discountValue,
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
        $campaign = Campaign::where('id', $id)->orWhere('uid', $id)->first();
        if (!$campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign tidak ditemukan'], 404);
        }

        $request->validate([
            'campaign_name' => 'nullable|string|max:150',
            'discount' => 'nullable|integer|min:0|max:' . self::BASE_TICKET_PRICE,
            'status' => 'nullable'
        ], [
            'discount.min' => 'Potongan harga tidak boleh kurang dari Rp 0.',
            'discount.max' => 'Potongan harga tidak boleh melebihi harga acuan (Rp ' . number_format(self::BASE_TICKET_PRICE, 0, ',', '.') . ').',
        ]);

        $campaignName = ($request->has('campaign_name') && $request->campaign_name !== null && $request->campaign_name !== '')
            ? $request->campaign_name
            : $campaign->campaign_name;

        $discountValue = ($request->has('discount') && $request->discount !== null && $request->discount !== '')
            ? (int) $request->discount
            : (int) ($campaign->discount ?? 0);

        $statusValue = $request->has('status')
            ? $this->parseStatusValue($request->status, (int) $campaign->status)
            : (int) $campaign->status;

        $campaign->update([
            'campaign_name' => $campaignName,
            'discount' => $discountValue,
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