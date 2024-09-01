<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/', $value)) {
                        $fail('Format email tidak valid.');
                    }
                },
            ],
            'password' => 'required',
        ], [
            'email.required' => 'Email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        // Respon error validasi
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ambil "email" dan "password" dari input
        $credentials = $request->only('email', 'password');

        try {
            // Coba melakukan autentikasi
            $token = auth()->guard('api')->attempt($credentials);

            if (!$token) {
                // Respon jika login gagal karena email atau password salah
                return response()->json([
                    'errors' => 'Email or Password is incorrect.',
                ], 401);
            }

            // Dapatkan informasi pengguna
            $user = auth()->guard('api')->user();
            $userData = $user->only(['name', 'email']);
            $roles = $user->roles->pluck('name');
            $accessToken = $token;

            // Simpan token JWT di cookie HTTP-only
            // $cookie = Cookie::make('token', $token, 30, null, null, false, true);

            // Inisialisasi array respons
            $responseData = [
                'accessToken' => $accessToken,
                'success' => true,
                'user' => $userData,
                'roles' => $roles,
                'permissions' => $user->getPermissionArray(),
            ];

            // Jika user adalah is_superadmin, tambahkan is_superadmin ke dalam respons JSON
            if ($user->is_superadmin == 1) {
                $responseData['is_superadmin'] = true;
            }

            // Kembalikan respons JSON dengan cookie
            // return response()->json($responseData)->withCookie($cookie);
            return response()->json($responseData);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json(['errors' => 'Terjadi kesalahan. Harap coba lagi nanti.'], 500);
        }
    }

    public function checkTokenValid()
    {
        try {

            $token = JWTAuth::getToken();

            $checkToken = JWTAuth::checkOrFail($token);

            if($checkToken) {
                return response()->json(['token_valid' => true]);
            }

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['token_valid' => false], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Invalid token or token cannot be found'], 401);
        }
    }

    // public function refreshToken(Request $request)
    // {
    //     try {
    //         // 1. Dapatkan token dari cookie HTTP-Only
    //         $token = $request->cookie('token');

    //         if (!$token) {
    //             return response()->json(['errors' => 'Token tidak ada.'], 401);
    //         }

    //         // 2. Refresh token menggunakan JWTAuth::refresh
    //         $newToken = JWTAuth::refresh($token);

    //         // 3. Kembalikan token yang sudah diperbarui melalui cookie HTTP-Only lagi
    //         $cookie = Cookie::make('token', $newToken, 30, null, null, false, true); // Cookie berlaku 30 menit

    //         return response()->json(['success' => true])->withCookie($cookie);
    //     } catch (\Exception $e) {
    //         Log::error('Terjadi Error saat refresh token: ' . $e->getMessage());
    //         return response()->json(['errors' => 'Terjadi kesalahan. Harap coba lagi nanti.'], 500);
    //     }
    // }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(['message' => 'Logout berhasil']);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());

            return response()->json(['errors' => 'Terjadi kesalahan ketika logout'], 500);
        }
    }
}
