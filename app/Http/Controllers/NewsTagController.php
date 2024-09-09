<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\NewsTag;
use App\Services\CheckAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NewsTagController extends Controller
{
    protected $checkAdminService;

    // Inject RoleService ke dalam constructor
    public function __construct(CheckAdminService $checkAdminService)
    {
        $this->checkAdminService = $checkAdminService;
    }


    public function index(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk melihat semua tag khusus admin.'
            ], 403);
        }

        try {
            if($request->query('name')) {
                $keywordName = $request->query('name');

                $allTag = NewsTag::where('name', 'like', '%' . $keywordName . '%')->paginate(10);

                if ($allTag->isEmpty()) {
                    return response()->json([
                        'errors' => 'Data tag tidak ditemukan.'
                    ], 404);
                }

                return response()->json($allTag, 200);  // Kembalikan isi pagination tanpa membungkus lagi
            } else {

                $allTag = NewsTag::paginate(10);
                
                return response()->json($allTag, 200);
            }
        } catch (\Exception $e) {
            Log::error('Terjadi kesalahan saat mendapatkan data tag: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat mendapatkan data tag'
            ], 500);
        }
    }
}
