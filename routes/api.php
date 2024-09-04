<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\FileController;
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

    Route::prefix('folder')->group(function () {
        Route::get('/', [FolderController::class, 'index']); // dapatkan list folder dan file yang ada pada user yang login saat ini pada folder rootnya.
        Route::get('/info/{id}', [FolderController::class, 'info']); // Mendapatkan informasi lengkap isi folder tertentu, termasuk file dan subfolder
        Route::post('/create', [FolderController::class, 'create']);  // Membuat folder baru
        Route::put('/update/{id}', [FolderController::class, 'update']); // Memperbarui folder
        Route::delete('/delete/{id}', [FolderController::class, 'delete']); // Menghapus folder
        Route::put('/move', [FolderController::class, 'move']); // Memindahkan folder ke folder lain menggunakan metode parent_id yang baru
        Route::get('/path/{id}', [FolderController::class, 'getFullPath']); // Mendapatkan full path dari folder
    });

    Route::prefix('file')->group(function () {
        Route::get('/{id}', [FileController::class, 'info']); // Mendapatkan informasi file
        Route::post('/create', [FileController::class, 'create']); // Membuat file baru
        Route::post('/upload', [FileController::class, 'upload']); // Mengunggah file
        Route::put('/change_name/{id}', [FileController::class, 'updateFileName']); // Memperbarui nama file
        Route::delete('/delete/{id}', [FileController::class, 'delete']); // Menghapus file
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

        Route::prefix('folder')->group(function () {
            Route::get('/', [FolderController::class, 'index']); // dapatkan list folder dan file yang ada pada user yang login saat ini pada folder rootnya.
            Route::get('/info/{id}', [FolderController::class, 'info']); // Mendapatkan informasi lengkap isi folder tertentu, termasuk file dan subfolder
            Route::post('/create', [FolderController::class, 'create']);  // Membuat folder baru
            Route::put('/update/{id}', [FolderController::class, 'update']); // Memperbarui folder
            Route::delete('/delete/{id}', [FolderController::class, 'delete']); // Menghapus folder
            Route::put('/move', [FolderController::class, 'move']); // Memindahkan folder ke folder lain menggunakan metode parent_id yang baru
            Route::get('/path/{id}', [FolderController::class, 'getFullPath']); // Mendapatkan full path dari folder
        });

        Route::prefix('file')->controller(FileController::class)->group(function () {
            Route::get('/{id}',  [FileController::class, 'info']); // Mendapatkan informasi file
            Route::post('/create', [FileController::class, 'create']); // Membuat file baru
            Route::post('/upload', [FileController::class, 'upload']); // Mengunggah file
            Route::put('/change_name/{id}', [FileController::class, 'updateFileName']); // Memperbarui nama file
            Route::delete('/delete/{id}', [FileController::class, 'delete']); // Menghapus file
            Route::put('/move/{id}', [FileController::class, 'move']); // Memindahkan file ke folder lain atau ke root
        });
    });
});
