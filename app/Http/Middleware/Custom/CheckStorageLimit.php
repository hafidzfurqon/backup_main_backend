<?php

namespace App\Http\Middleware\Custom;

use App\Models\Folder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckStorageLimit
{
    // Batas penyimpanan dalam bytes (10GB = 10 * 1024 * 1024 * 1024 bytes)
    protected $storageLimit = 10 * 1024 * 1024 * 1024;

    private function calculateFolderSize(Folder $folder)
    {
        $totalSize = 0;

        // Hitung ukuran semua file di folder
        foreach ($folder->files as $file) {
            $totalSize += $file->size; // Asumsi kolom 'size' ada di model File
        }

        // Rekursif menghitung ukuran semua subfolder
        foreach ($folder->subfolders as $subfolder) {
            $totalSize += $this->calculateFolderSize($subfolder);
        }

        return $totalSize;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            // Dapatkan folder root milik user
            $rootFolder = Folder::where('user_id', $user->id)->whereNull('parent_id')->first();

            if ($rootFolder) {
                // Hitung total penyimpanan yang digunakan user
                $totalUsedStorage = $this->calculateFolderSize($rootFolder);

                // Jika total penyimpanan melebihi batas yang ditentukan
                if ($totalUsedStorage >= $this->storageLimit) {
                    return response()->json([
                        'errors' => 'You have exceeded your storage limit of 10GB.',
                    ], 403); // Kirimkan respons forbidden
                }
            }
        }

        return $next($request);
    }
}
