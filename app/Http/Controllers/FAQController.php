<?php

namespace App\Http\Controllers;

use App\Models\FAQ;
use App\Services\CheckAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FAQController extends Controller
{
    protected $checkAdminService;

    // Inject RoleService ke dalam constructor
    private function __construct(CheckAdminService $checkAdminService)
    {
        $this->checkAdminService = $checkAdminService;
    }

    public function index()
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if(!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk melihat FAQ.'
            ], 403);
        }

        try {
            $faqs = FAQ::all();

            if ($faqs->isEmpty()) {
                return response()->json([
                    'message' => 'FAQ is empty'
                ], 404);
            }

            return response()->json([
                'data' => $faqs
            ], 200);

        } catch (\Exception $e) {
            Log::error('Terjadi kesalahan saat mendapatkan data FAQ: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan saat mendapatkan data FAQ.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if(!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk membuat FAQ.'
            ], 403);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'question' => 'required|string',
                'answer' => 'required|string',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {

            $faq = FAQ::create([
                'question' => $request->question,
                'answer' => $request->answer
            ]);

            DB::commit();

            return response()->json([
                'message' => 'FAQ berhasil dibuat',
                'data' => $faq
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Terjadi kesalahan saat membuat data FAQ: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat membuat data FAQ.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if(!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk mengupdate FAQ.'
            ], 403);
        }

        $faq = FAQ::find($id);

        if(!$faq) {
            return response()->json([
                'errors' => 'FAQ tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'question' => 'required|string',
                'answer' => 'required|string',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $faq->question = $request->question;    
            $faq->answer = $request->answer;
            $faq->save();
            DB::commit();

            return response()->json([
                'message' => 'FAQ berhasil diperbarui',
                'data' => $faq
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Terjadi kesalahan saat mengupdate data FAQ: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat mengupdate data FAQ.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if(!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk menghapus FAQ.'
            ], 403);
        }

        $faq = FAQ::find($id);

        if(!$faq) {
            return response()->json([
                'errors' => 'FAQ tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $faq->delete();
            DB::commit();

            return response()->json([
                'message' => 'FAQ berhasil dihapus',
                'data' => $faq
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Terjadi kesalahan saat menghapus data FAQ: ' . $e->getMessage());
            
            return response()->json([
                'errors' => 'Terjadi kesalahan saat menghapus data FAQ.'
            ], 500);
        }
    }
}
