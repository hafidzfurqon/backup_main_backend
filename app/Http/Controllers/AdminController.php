<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    // Informasi tentang akun Admin yang sedang login saat ini
    public function index()
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') && $user->is_superadmin == 1)) {
            return response()->json([
                'error' => 'Anda tidak di izinkan untuk mengupdate user.',
            ], 403);
        }

        try {

            $admin = User::where('id', $user->id)->first();

            return response()->json([
                'data' => $admin
            ]);
        } catch (Exception $e) {
            Log::error('Error occurred on getting admin information: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan ketika mengambil data tentang admin.',
            ], 500);
        }
    }

    public function listUser(Request $request)
    {
        $user = Auth::user();

        // Perbaikan logika pada otorisasi: Superadmin atau admin bisa mengakses
        if (!($user->hasRole('admin') ||  ($user->hasRole('admin') && $user->is_superadmin == 1))) {
            return response()->json([
                'error' => 'Anda tidak diizinkan untuk melakukan tindakan ini.',
            ], 403);
        }

        try {
            // Cari data berdasarkan Nama User
            if ($request->query('name')) {
                $keywordName = $request->query('name');
                $allUser = User::where('name', 'like', '%' . $keywordName . '%')->paginate(10);
                return response()->json($allUser, 200);  // Kembalikan hasil pagination tanpa membungkus lagi
            }
            // Cari data berdasarkan Email User
            else if ($request->query('email')) {
                $keywordEmail = $request->query('email');
                $allUser = User::where('email', 'like', '%' . $keywordEmail . '%')->paginate(10);
                return response()->json($allUser, 200);  // Kembalikan hasil pagination tanpa membungkus lagi
            } else {
                // Mengambil semua data pengguna dengan pagination
                $allUser = User::paginate(10);
                return response()->json($allUser, 200);  // Kembalikan hasil pagination tanpa membungkus lagi
            }
        } catch (\Exception $e) {
            Log::error("Terjadi kesalahan ketika mengambil data user: " . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan ketika mengambil data user.',
            ], 500);
        }
    }

    // informasi akun user spesifik
    public function user_info($id)
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') && $user->is_superadmin == 1)) {
            return response()->json([
                'error' => 'Anda tidak di izinkan untuk melakukan tindakan ini.',
            ], 403);
        }

        try {

            $user = User::where('id', $id)->first();

            return response()->json([
                'data' => $user
            ]);
        } catch (Exception $e) {
            Log::error('Error occurred on getting user information: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan ketika mengambil data user.',
            ], 500);
        }
    }

    public function createUserFromAdmin(Request $request)
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') && $user->is_superadmin == 1)) {
            return response()->json([
                'error' => 'Anda tidak di izinkan untuk mengupdate user.',
            ], 403);
        }

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
            'role' => ['required', 'string', 'exists:roles,name'],
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);

            $newUser->assignRole($request->role);

            $newUser->instances()->sync($request->instance_id);

            DB::commit();

            return response()->json([
                'message' => 'User berhasil ditambahkan',
                'data' => $newUser
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error occurred on adding user: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan ketika menambahkan user.',
            ], 500);
        }
    }

    public function updateUserFromAdmin(Request $request, $userIdToBeUpdated)
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') && $user->is_superadmin == 1)) {
            return response()->json([
                'error' => 'Anda tidak di izinkan untuk mengupdate user.',
            ], 403);
        }

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
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $userToBeUpdated = User::where('id', $userIdToBeUpdated)->first();

            if (!$userToBeUpdated) {
                return response()->json([
                    'errors' => 'User tidak ditemukan.'
                ], 404);
            }

            $userToBeUpdated->update([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);

            // Perbarui instance user
            $userToBeUpdated->instances()->sync($request->instance_id);

            // Cari folder yang terkait dengan user
            $userFolders = Folder::where('user_id', $userToBeUpdated->id)->get();

            foreach ($userFolders as $folder) {
                // Perbarui relasi instance pada setiap folder terkait
                $folder->instances()->sync($request->instance_id);
            }

            DB::commit();

            return response()->json([
                'message' => 'User berhasil diupdate',
                'data' => $userToBeUpdated
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error occurred on updating user: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan ketika mengupdate user.',
            ], 500);
        }
    }

    /**
     * Delete a user from admin (DELETE).
     *
     * This function is DANGEROUS and should be used with caution.
     *
     * @param int $userIdToBeDeleted The ID of the user to be deleted.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUserFromAdmin($userIdToBeDeleted)
    {
        $user = Auth::user();

        // Check if the user has the required permission to delete users.
        if (!($user->hasRole('admin') && $user->is_superadmin == 1)) {
            return response()->json([
                'error' => 'Anda tidak diizinkan untuk menghapus user.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Delete the user from the database.
            $userData = User::where('id', $userIdToBeDeleted)->first();

            if (!$userData) {
                return response()->json([
                    'errors' => 'User tidak ditemukan.'
                ], 404);
            }

            // Hapus folder dan file terkait dari local storage
            $folders = Folder::where('user_id', $userData->id)->get();
            if (!$folders->isEmpty()) {
                foreach ($folders as $folder) {
                    $this->deleteFolderAndFiles($folder);
                }
            }

            $userData->delete();
            DB::commit();

            return response()->json([
                'message' => 'User berhasil dihapus'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            // Log the error if an exception occurs.
            Log::error('Error occurred on deleting user: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json([
                'errors' => 'Terjadi kesalahan ketika menghapus user.',
            ], 500);
        }
    }

    /**
     * Menghapus folder beserta file-file di dalamnya dari local storage
     *
     * @throws \Exception
     */
    private function deleteFolderAndFiles(Folder $folder)
    {
        DB::beginTransaction();

        try {
            // Hapus semua file dalam folder
            $files = $folder->files;
            foreach ($files as $file) {
                try {
                    // Hapus file dari storage
                    Storage::delete($file->path);
                    // Hapus data file dari database
                    $file->delete();
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error occurred while deleting file with ID ' . $file->id . ': ' . $e->getMessage());
                    // Lemparkan kembali exception agar dapat ditangani di tingkat pemanggil
                    throw $e;
                }
            }

            // Hapus subfolder dan file dalam subfolder
            $subfolders = $folder->subfolders;
            foreach ($subfolders as $subfolder) {
                $this->deleteFolderAndFiles($subfolder);
            }

            // Hapus folder dari storage
            try {
                $folderPath = $this->getFolderPath($folder->id);
                if (Storage::exists($folderPath)) {
                    Storage::deleteDirectory($folderPath);
                }
            } catch (\Exception $e) {
                Log::error('Error occurred while deleting folder with ID ' . $folder->id . ': ' . $e->getMessage());
                // Lemparkan kembali exception agar dapat ditangani di tingkat pemanggil
                throw $e;
            }

            // Hapus data folder dari database
            try {
                $folder->delete();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error occurred while deleting folder record with ID ' . $folder->id . ': ' . $e->getMessage());
                // Lemparkan kembali exception agar dapat ditangani di tingkat pemanggil
                throw $e;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error occurred while processing folder with ID ' . $folder->id . ': ' . $e->getMessage());
            // Lemparkan kembali exception agar dapat ditangani di tingkat pemanggil
            throw $e;
        }
    }
}
