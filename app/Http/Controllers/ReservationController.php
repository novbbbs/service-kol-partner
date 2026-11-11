<?php

namespace App\Http\Controllers; // <-- Diubah dari App\Http\Controllers\Api menjadi App\Http\Controllers

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Mulai query dengan relasi KOL
            $query = Reservation::with('kol');

            // Filter berdasarkan kata kunci pencarian (search)
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('nama_pembeli', 'like', "%{$search}%")
                      ->orWhere('kode_booking', 'like', "%{$search}%")
                      ->orWhere('id_order', 'like', "%{$search}%");
                });
            }

            // Filter berdasarkan Status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter berdasarkan Rentang Tanggal Kedatangan (startDate & endDate)
            if ($request->filled('startDate') && $request->filled('endDate')) {
                $query->whereBetween('kedatangan', [
                    $request->input('startDate') . ' 00:00:00', 
                    $request->input('endDate') . ' 23:59:59'
                ]);
            }

            // Ambil data terbaru
            $reservations = $query->latest()->get();

            return response()->json([
                'success' => true,
                'data' => $reservations
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }
}