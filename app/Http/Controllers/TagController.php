<?php

namespace App\Http\Controllers;

use App\Models\Tags;
use App\Services\CheckAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TagController extends Controller
{

    protected $checkAdminService;

    // Inject RoleService ke dalam constructor
    private function __construct(CheckAdminService $checkAdminService)
    {
        $this->checkAdminService = $checkAdminService;
    }

    public function index(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk melihat semua tag.'
            ], 403);
        }

        try {
            if ($request->query('name')) {
                $keywordName = $request->query('name');
                $allTag = Tags::where('name', 'like', '%' . $keywordName . '%')->paginate(10);
                if ($allTag->isEmpty()) {
                    return response()->json([
                        'errors' => 'Data tag tidak ditemukan.'
                    ], 404);
                }
                return response()->json($allTag, 200);  // Kembalikan isi pagination tanpa membungkus lagi
            } else {
                $allTag = Tags::paginate(10);
                return response()->json($allTag, 200);
            }
        } catch (\Exception $e) {
            Log::error('Terjadi kesalahan saat mendapatkan data tag: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan saat mendapatkan data tag.'
            ], 500);
        }
    }

    public function getTagsId(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk melihat semua tag.'
            ], 403);
        }

        try {

            $keywordName = $request->query('name');

            // Ambil hanya kolom 'id' tanpa pagination
            $tagId = Tags::where('name', 'like', '%' . $keywordName . '%')
                ->get(['id', 'name']);

            if ($tagId->isEmpty()) {
                return response()->json([
                    'errors' => 'Data tag tidak ditemukan.'
                ], 404);
            }

            // Kembalikan daftar ID
            return response()->json([
                'data' => $tagId
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Terjadi kesalahan saat mendapatkan data tag: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan saat mendapatkan data tag.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk menambahkan tag.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Validator with unique rule (case-insensitive check) and regex to prevent unclear letter/number mixes
            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    // Custom uniqueness validation query to make it case-insensitive
                    Rule::unique('tags')->where(function ($query) {
                        return $query->whereRaw('LOWER(name) = ?', [strtolower(request('name'))]);
                    }),
                    'regex:/^[a-z]+$/', // Prevent mixed letters/numbers (can adjust if needed)
                ],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $tag = Tags::create([
                'name' => $request->name
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Tag berhasil dibuat',
                'data' => $tag
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Terjadi kesalahan saat membuat tag: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat membuat tag.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk mengubah tag.'
            ], 403);
        }

        DB::beginTransaction();
        try {

            $tag = Tags::find($id);

            if (!$tag) {
                return response()->json([
                    'errors' => 'Tag tidak ditemukan.'
                ], 404);
            }

            // Validator with unique rule (case-insensitive check) and regex to prevent unclear letter/number mixes EXCLUDE the current tag ID
            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    // Ignore the current tag's ID during the uniqueness check
                    Rule::unique('tags')->where(function ($query) use ($request, $id) {
                        return $query->whereRaw('LOWER(name) = ?', [strtolower($request->name)])
                            ->where('id', '!=', $id); // Exclude the current tag ID
                    }),
                    'regex:/^[a-z]+$/', // Prevent mixed letters/numbers (can adjust if needed)
                ],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Update the tag
            $tag->name = $request->name;
            $tag->save();
            DB::commit();

            return response()->json([
                'message' => 'Tag berhasil diperbarui',
                'data' => $tag
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Terjadi kesalahan saat memperbarui tag: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat memperbarui tag.'
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk menghapus tag.'
            ], 403);
        }

        // Jika tidak ada $id, validasi bahwa tag_ids dikirim dalam request
        $validator = Validator::make($request->all(), [
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ambil daftar tag_ids dari request
        $tagIds = $request->tag_ids;

        DB::beginTransaction();

        try {
            foreach ($tagIds as $tag_id) {

                $tag = Tags::find($tag_id);

                $tag->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Tag berhasil dihapus',
                'data' => $tag
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Terjadi kesalahan saat menghapus tag: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat menghapus tag.'
            ], 500);
        }
    }
}
