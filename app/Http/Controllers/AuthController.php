<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ApiFormatter;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'logout']]);
    }

    /**
     * Mendapatkan JWT dengan kredensial yang diberikan.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Email harus berupa alamat email yang valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $credentials = $request->only(['email', 'password']);

        if (!$token = Auth::attempt($credentials)) {
            return ApiFormatter::sendResponse(400, 'Pengguna tidak ditemukan', 'Silahkan cek kembali email dan kata sandi anda!');
        }

        $respondWithToken = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => auth()->user(),
            'expires_in' => auth()->factory()->getTTL() * 60 * 24
        ];

        return ApiFormatter::sendResponse(200, 'Berhasil login', $respondWithToken);
    }

    /**
     * Mendapatkan pengguna yang terautentikasi.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = auth()->user();
        $user->role = $user->role; // Asumsikan bahwa role adalah kolom di tabel users
        return ApiFormatter::sendResponse(200, 'Berhasil', $user);
    }
    
    /**
     * Keluar (menginvalidasi token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return ApiFormatter::sendResponse(200, 'Berhasil', 'Berhasil logout!');
    }
}
