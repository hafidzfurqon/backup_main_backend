<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\FAQController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Route::post('/register', [UserController::class, 'register']); // Register user baru (bukan melalui admin)
Route::post('/login', [AuthController::class, 'login']); // login user

Route::post('/checkTokenValid', [AuthController::class, 'checkTokenValid']); // TODO: periksa apakah token jwt masih valid atau tidak

// Route::post('/refreshToken', [AuthController::class, 'refreshToken'])->middleware('auth:api'); // TODO: refresh token jwt

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');  // logout user

Route::middleware(['auth:api', 'remove_nanoid', 'protectRootFolder', 'check_admin', 'hide_superadmin_flag'])->group(function () {

    Route::prefix('user')->group(function () {
        Route::get('/index', [UserController::class, 'index']); // Mendapatkan informasi user
        Route::put('/update', [UserController::class, 'update']); // Update user
        Route::delete('/delete', [UserController::class, 'delete']); // Menghapus user
    });

    Route::prefix('folder')->middleware('check_storage_limit')->group(function () {
        Route::get('/', [FolderController::class, 'index']); // dapatkan list folder dan file yang ada pada user yang login saat ini pada folder rootnya.
        Route::get('/info/{id}', [FolderController::class, 'info']); // Mendapatkan informasi lengkap isi folder tertentu, termasuk file dan subfolder
        Route::get('/storageSizeUsage', [FolderController::class, 'storageSizeUsage']); // Informasi total penyimpanan yang digunakan
        Route::post('/create', [FolderController::class, 'create']);  // Membuat folder baru
        Route::put('/update/{id}', [FolderController::class, 'update']); // Memperbarui folder
        Route::post('/delete', [FolderController::class, 'delete']); // Menghapus folder. (NOTE: HARUS MENGGUNAKAN ARRAY BERISI ID FOLDER!)
        Route::post('/removeTag', [FolderController::class, 'removeTagFromFolder']); // hapus tag dari folder
        Route::put('/move', [FolderController::class, 'move']); // Memindahkan folder ke folder lain menggunakan metode parent_id yang baru
        Route::get('/path/{id}', [FolderController::class, 'getFullPath']); // Mendapatkan full path dari folder
    });

    Route::prefix('file')->middleware('check_storage_limit')->group(function () {
        Route::get('/{id}', [FileController::class, 'info']); // Mendapatkan informasi file
        Route::post('/create', [FileController::class, 'create']); // Membuat file baru
        Route::post('/upload', [FileController::class, 'upload']); // Mengunggah file
        Route::put('/change_name/{id}', [FileController::class, 'updateFileName']); // Memperbarui nama file
        Route::post('/delete', [FileController::class, 'delete']); // Menghapus file
        Route::put('/move/{id}', [FileController::class, 'move']); // Memindahkan file ke folder lain atau ke root
    });
});


// ROUTE KHUSUS UNTUK ADMIN
Route::middleware(['auth:api', 'validate_admin'])->group(function () {

    Route::prefix('admin')->group(function () {

        Route::get('/index', [AdminController::class, 'index']); // dapatkan informasi tentang akun admin yang sedang login saat ini.

        Route::prefix('users')->group(function () {
            Route::get('/list', [AdminController::class, 'listUser']); // dapatkan list user (bisa juga menggunakan query seperti ini: /list?name=namauseryangingindicari)
            Route::get('/info/{userId}', [AdminController::class, 'user_info']); // dapatkan informasi tentang user
            Route::post('/create_user', [AdminController::class, 'createUserFromAdmin']); // route untuk membuat user baru melalui admin.
            Route::put('/update_user/{userIdToBeUpdated}', [AdminController::class, 'updateUserFromAdmin']); // route untuk mengupdate user yang sudah ada melalui admin.
            Route::delete('/delete_user/{userIdToBeDeleted}', [AdminController::class, 'deleteUserFromAdmin']); // route untuk menghapus user yang sudah ada melalui admin. (DANGEROUS!)
        });

        Route::prefix('folder')->middleware('check_storage_limit')->group(function () {
            Route::get('/', [FolderController::class, 'index']); // dapatkan list folder dan file yang ada pada user yang login saat ini pada folder rootnya.
            Route::get('/info/{id}', [FolderController::class, 'info']); // Mendapatkan informasi lengkap isi folder tertentu, termasuk file dan subfolder
            Route::get('/storageSizeUsage', [FolderController::class, 'storageSizeUsage']); // Informasi total penyimpanan yang digunakan
            Route::post('/removeTag', [FolderController::class, 'removeTagFromFolder']); // hapus tag dari folder
            Route::post('/create', [FolderController::class, 'create']);  // Membuat folder baru
            Route::put('/update/{id}', [FolderController::class, 'update']); // Memperbarui folder
            Route::post('/delete', [FolderController::class, 'delete']); // Menghapus folder
            Route::put('/move', [FolderController::class, 'move']); // Memindahkan folder ke folder lain menggunakan metode parent_id yang baru
            Route::get('/path/{id}', [FolderController::class, 'getFullPath']); // Mendapatkan full path dari folder
        });

        Route::prefix('file')->middleware('check_storage_limit')->group(function () {
            Route::get('/{id}',  [FileController::class, 'info']); // Mendapatkan informasi file
            Route::post('/create', [FileController::class, 'create']); // Membuat file baru
            Route::post('/upload', [FileController::class, 'upload']); // Mengunggah file
            Route::put('/change_name/{id}', [FileController::class, 'updateFileName']); // Memperbarui nama file
            Route::post('/delete', [FileController::class, 'delete']); // Menghapus file
            Route::put('/move/{id}', [FileController::class, 'move']); // Memindahkan file ke folder lain atau ke root
        });

        Route::prefix('tag')->group(function () {
            Route::get('/', [TagController::class, 'index']); // dapatkan semua list tag yang ada
            Route::post('/getTagsId', [TagController::class, 'getTagsId']); // dapatkan id dari tags dengan query parameter nama tag (contoh: /getTagsId?name=tag)
            Route::post('/create', [TagController::class, 'store']); // Buat tag baru
            Route::put('/update', [TagController::class, 'update']); // Update tag yang ada sebelumnya
            Route::post('/delete', [TagController::class, 'destroy']); // Hapus tag yang ada sebelumnya dengan array request body
        });

        Route::prefix('instansi')->group(function () {
            Route::get('/', [InstanceController::class, 'index']); // dapatkan semua list instansi yang ada
            Route::post('/instance', [InstanceController::class, 'getInstanceWithName']); // Mendapatkan daftar ID instansi berdasarkan nama (contoh: /instance?name=instansi)
            Route::post('/create', [InstanceController::class, 'store']); // Membuat instansi baru
            Route::put('/update/{id}', [InstanceController::class, 'update']); // Update instansi yang ada sebelumnya
            Route::post('/delete', [InstanceController::class, 'destroy']); // Hapus instansi yang ada sebelumnya dengan array request body
        });

        Route::prefix('faq')->group(function () {
            Route::get('/', [FAQController::class, 'index']); // dapatkan semua list FAQ yang ada
            Route::post('/create', [FAQController::class, 'store']); // Buat FAQ baru
            Route::put('/update/{id}', [FAQController::class, 'update']); // Update FAQ yang ada sebelumnya
            Route::delete('/delete/{id}', [FAQController::class, 'destroy']); // Hapus FAQ yang ada sebelumnya
        });
    });
});
