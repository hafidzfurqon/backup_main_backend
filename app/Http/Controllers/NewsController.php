<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Services\CheckAdminService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    protected $checkAdminService;

    // Inject RoleService ke dalam constructor
    public function __construct(CheckAdminService $checkAdminService)
    {
        $this->checkAdminService = $checkAdminService;
    }

    public function getAllNewsForAdmin(Request $request)
    {
        // Cek apakah user adalah admin
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk melihat semua berita khusus admin.'
            ], 403);
        }

        try {
            // Ambil semua data berita beserta data user pembuatnya, dengan pagination 10 item per halaman
            $news = News::with(['creator', 'newsTags'])->paginate(10);

            if($news->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada berita.'
                ], 404);
            }

            return response()->json([
                'message' => 'Berita berhasil diambil',
                'data' => $news
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'errors' => 'Terjadi kesalahan saat mengambil data berita: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllNewsForPublic()
    {
        try {
            // Ambil semua data berita beserta nama pembuat dan tag-nya, dengan pagination 10 item per halaman
            $news = News::with([
                'creator' => function ($query) {
                    // Hanya ambil field 'name' dari creator
                    $query->select('name');
                },
                'newsTags' => function ($query) {
                    // Hanya ambil field 'name' dari tag
                    $query->select('news_tags.name');
                }
            ])->where('status', 'published')->paginate(10);

            if($news->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada berita.'
                ], 404);
            }

            return response()->json([
                'message' => 'Berita berhasil diambil',
                'data' => $news
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => 'Terjadi kesalahan saat mengambil data berita: ' . $e->getMessage()
            ], 500);
        }
    }


}
