<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|digits:4',
            'password' => 'required',
            'source'   => 'nullable|string|in:manual,salokapark_app',
        ]);

        $username = trim((string) $request->input('username'));
        $password = (string) $request->input('password');
        $source   = $request->input('source', 'manual');

        // Cek apakah user dengan username tersebut terdaftar
        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code'    => 'USER_NOT_REGISTERED',
                'source'  => $source,
                'message' => 'Akun belum terdaftar.',
            ], 401);
        }

        if (!Auth::attempt(['username' => $username, 'password' => $password])) {
            return response()->json([
                'success' => false,
                'code'    => 'INVALID_CREDENTIALS',
                'source'  => $source,
                'message' => 'Username atau password salah.',
            ], 401);
        }

        // Cek status aktif / akses ke modul KOL jika kolom tersedia di tabel users
        if (isset($user->is_active) && !(bool) $user->is_active) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'code'    => 'ACCESS_DENIED',
                'source'  => $source,
                'message' => 'Akun belum memiliki akses ke aplikasi KOL.',
            ], 403);
        }

        if (
            isset($user->status) &&
            in_array(strtolower(trim((string) $user->status)), ['inactive', 'non-active', 'nonaktif', 'suspended', '0'], true)
        ) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'code'    => 'ACCESS_DENIED',
                'source'  => $source,
                'message' => 'Akun belum memiliki akses ke aplikasi KOL.',
            ], 403);
        }

        // Generate token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success'      => true,
            'source'       => $source,
            'message'      => 'Login Berhasil',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ]);
    }

    /**
     * Menghapus token Sanctum saat logout
     */
    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar akun.',
        ]);
    }
}