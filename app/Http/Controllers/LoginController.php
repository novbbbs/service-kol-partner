<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|digits:4',
            'password' => 'required',
        ]);

        $credentials = $request->only('username', 'password');

        // Cek apakah user dengan username tersebut ada
        $user = User::where('username', $request->username)->first();

        // [PENGAMAN OPSIONAL]: Jika user 1234 belum ada atau passwordnya ingin di-reset otomatis jadi 'password123' untuk testing
        /*
        if (!$user && $request->username === '1234') {
            $user = User::create([
                'name' => 'Admin Saloka',
                'username' => '1234',
                'email' => 'admin@saloka.com',
                'password' => Hash::make('password123')
            ]);
        }
        */

        if (!$user || !Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Username atau password salah.'
            ], 401);
        }

        // Generate token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login Berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
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
            'message' => 'Berhasil keluar akun.'
        ]);
    }
}