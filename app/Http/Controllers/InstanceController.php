<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Services\CheckAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InstanceController extends Controller
{
    protected $checkAdminService;

    // Inject RoleService ke dalam constructor
    public function __construct(CheckAdminService $checkAdminService)
    {
        $this->checkAdminService = $checkAdminService;
    }

    /**
     * Mendapatkan data instansi berdasarkan query parameter `name`.
     * Jika query parameter `name` tidak diberikan, maka akan mengembalikan semua data instansi.
     * 
     * @param Request $request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk melihat semua instance.'
            ], 403);
        }

        try {
            if ($request->query('name')) {

                $keywordName = $request->query('name');

                $allInstance = Instance::where('name', 'like', '%' . $keywordName . '%')->paginate(10);

                if ($allInstance->isEmpty()) {
                    return response()->json([
                        'errors' => 'Data instance tidak ditemukan.'
                    ], 404);
                }

                return response()->json($allInstance, 200);  // Kembalikan isi pagination tanpa membungkus lagi
            } else {

                $allInstance = Instance::paginate(10);

                return response()->json($allInstance, 200);
            }
        } catch (\Exception $e) {

            Log::error('Terjadi kesalahan saat mendapatkan data instance: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat mendapatkan data instance.'
            ], 500);
        }
    }

    /**
     * Mendapatkan daftar ID instansi berdasarkan nama.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInstanceWithName(Request $request)
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
            $instanceId = Instance::where('name', 'like', '%' . $keywordName . '%')
                ->get(['id', 'name']);

            if ($instanceId->isEmpty()) {
                return response()->json([
                    'errors' => 'Data instansi tidak ditemukan.'
                ], 404);
            }

            // Kembalikan daftar ID
            return response()->json([
                'data' => $instanceId
            ], 200);
        } catch (\Exception $e) {
            Log::error('Terjadi kesalahan saat mendapatkan data tag: ' . $e->getMessage());
            return response()->json([
                'errors' => 'Terjadi kesalahan saat mendapatkan data tag.'
            ], 500);
        }
    }

    /**
     * Membuat instansi baru.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk membuat instance.'
            ], 403);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255|unique:instances,name',
                'address' => 'required|string|max:255',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $instance = Instance::create([
                'name' => $request->name,
                'address' => $request->address,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Instansi Berhasil Dibuat',
                'data' => $instance
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Terjadi kesalahan saat membuat instance: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat membuat instance.'
            ], 500);
        }
    }

    /**
     * Update data spesifik instansi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk mengubah instance.'
            ], 403);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'address' => 'required|string|max:255',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $instance = Instance::find($id);

            if (!$instance) {
                return response()->json([
                    'errors' => 'Instansi tidak ditemukan.'
                ], 404);
            }

            $instance->update([
                'name' => $request->name,
                'address' => $request->address,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Instansi Berhasil Diperbarui',
                'data' => $instance
            ], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Terjadi kesalahan saat memperbarui instance: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat memperbarui instance.'
            ], 500);
        }
    }

    /**
     * Menghapus satu atau beberapa instansi berdasarkan array ID yang diberikan.
     * 
     * PERINGATAN: FUNCTION INI DAPAT MENGHAPUS SATU ATAU LEBIH DATA SECARA 
     * PERMANEN. GUNAKAN DENGAN HATI-HATI
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if (!$checkAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki izin untuk menghapus instance.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'instance_ids' => 'required|array',
            'instance_ids.*' => 'integer|exists:instances,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ambil daftar tag_ids dari request
        $instanceIds = $request->instance_ids;

        DB::beginTransaction();

        try {

            foreach ($instanceIds as $instance_id) {

                $instance = Instance::find($instance_id);

                $instance->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Instansi Berhasil Dihapus',
            ], 200);
        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Terjadi kesalahan saat menghapus instance: ' . $e->getMessage());

            return response()->json([
                'errors' => 'Terjadi kesalahan saat menghapus instance.'
            ], 500);
        }
    }
}
