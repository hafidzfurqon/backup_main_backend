<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'unique:users,email', // Menentukan kolom yang dicek di tabel users
                function ($attribute, $value, $fail) {
                    // Validasi format email menggunakan Laravel's 'email' rule
                    if (!preg_match('/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,})+$/', $value)) {
                        $fail('Format email tidak valid.');
                    }

                    // Daftar domain yang valid
                    $allowedDomains = [
                        'outlook.com',
                        'yahoo.com',
                        'aol.com',
                        'lycos.com',
                        'mail.com',
                        'icloud.com',
                        'yandex.com',
                        'protonmail.com',
                        'tutanota.com',
                        'zoho.com',
                        'gmail.com'
                    ];

                    // Ambil domain dari alamat email
                    $domain = strtolower(substr(strrchr($value, '@'), 1));

                    // Periksa apakah domain email diizinkan
                    if (!in_array($domain, $allowedDomains)) {
                        $fail('Domain email tidak valid.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);

            $roles = 'user';
            $user->assignRole($roles);

            return response()->json([
                'message' => 'Berhasil Mendaftarkan Akun!',
                'data' => $user
            ], 201);
        } catch (Exception $e) {
            Log::error('Error occurred on registering user: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan ketika mendaftarkan akun.',
            ], 500);
        }
    }

    public function index()
    {
        $user = Auth::user();

        try {
            $getUserData = User::where('id', $user->id)->first();
            
            $getFolderRootId = Folder::where('user_id', $user->id)->first();

            $getUserData['folder_root_id'] = $getFolderRootId->id;

            return response()->json([
                'data' => $getUserData
            ], 200);
        } catch (Exception $e) {
            Log::error('Error occurred on getting user data: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan ketika mendapatkan mengambil data tentang user.',
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'unique:users,email', // Menentukan kolom yang dicek di tabel users
                function ($attribute, $value, $fail) {
                    // Validasi format email menggunakan Laravel's 'email' rule
                    if (!preg_match('/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,})+$/', $value)) {
                        $fail('Format email tidak valid.');
                    }

                    // Daftar domain yang valid
                    $allowedDomains = [
                        'outlook.com',
                        'yahoo.com',
                        'aol.com',
                        'lycos.com',
                        'mail.com',
                        'icloud.com',
                        'yandex.com',
                        'protonmail.com',
                        'tutanota.com',
                        'zoho.com',
                        'gmail.com'
                    ];

                    // Ambil domain dari alamat email
                    $domain = strtolower(substr(strrchr($value, '@'), 1));

                    // Periksa apakah domain email diizinkan
                    if (!in_array($domain, $allowedDomains)) {
                        $fail('Domain email tidak valid.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updatedUser = User::where('id', $user->id)->update([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);
            
            return response()->json([
                'message' => 'Data user berhasil diperbarui',
                'data' => $updatedUser
            ], 200);
        } catch (Exception $e) {
            Log::error('Error occurred on updating user: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan ketika mengupdate data user.',
            ], 500);
        }
    }

    /**
     * Delete the current user (DELETE).
     * 
     * This function is DANGEROUS and should be used with caution.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete()
    {
        $user = Auth::user();

        try {
            // Delete the user from the database.
            User::where('id', $user->id)->delete();

            // Return a success response.
            return response()->json([
                'message' => 'User berhasil di hapus'
            ], 200);
        } catch (Exception $e) {
            // Log the error if an exception occurs.
            Log::error('Error occurred on deleting user: ' . $e->getMessage());

            // Return an error response.
            return response()->json([
                'errors' => 'Terjadi kesalahan ketika menghapus user.',
            ], 500);
        }
    }
}