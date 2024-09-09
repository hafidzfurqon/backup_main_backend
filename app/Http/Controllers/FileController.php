<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Folder;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use AMWScan\Scanner;
use App\Models\Tags;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FileController extends Controller
{
    /**
     * Get information about a file (READ).
     */
    public function info($id)
    {
        try {
            $file = File::with(['tags', 'instances'])->find($id);

            if (!$file) {
                return response()->json([
                    'errors' => 'File not found',
                ], 404);
            }

            // Sembunyikan kolom 'path' dan 'nanoid'
            $file->makeHidden(['path', 'nanoid']);

            // Generate file URL if it exists in public disk
            // $fileUrl = Storage::url($file->path);

            return response()->json([
                'data' => [
                    'file' => $file,
                    // 'fileUrl' => $fileUrl, // Add file URL to the response
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Error encountered while fetching file info: ', [
                'fileId' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'errors' => 'An error occurred while fetching the file info.',
            ], 500);
        }
    }

    // dapatkan semua file dan total filenya
    public function getAllFilesAndTotalSize()
    {
        $user = Auth::user();
        try {
            // Ambil semua file dari database
            $files = File::where('user_id', $user->id)->with(['tags', 'instances'])->get();

            // Hitung total ukuran semua file
            $totalSize = $files->sum('size');

            $files->makeHidden(['path', 'nanoid']);

            // Return daftar file dan total ukuran
            return response()->json([
                'data' => [
                    'total_size' => $totalSize,
                    'files' => $files,
                ],
            ], 200);
        } catch (\Exception $e) {

            Log::error('An error occurred while fetching all files and total size: ' . $e->getMessage());

            return response()->json([
                'error' => 'An error occurred while fetching all files and total size.'
            ], 500);
        }
    }


    /**
     * Create a new text file (CREATE).
     */
    public function create(Request $request)
    {
        $userId = Auth::id();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',  // Nama file yang akan dibuat
            'content' => 'nullable|string', // Konten file, bisa kosong
            'folder_id' => 'nullable|integer|exists:folders,id',
            'tags' => 'nullable|array',
            'tags.*' => ['string', 'regex:/^[a-zA-Z\s]+$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Generate file path berdasarkan folder_id (jika ada)
            $path = $this->generateFilePath($request->folder_id, $request->name);

            // Simpan file teks ke storage dengan konten yang diberikan (jika ada)
            Storage::put($path, $request->input('content', ''));

            // Dapatkan ukuran file yang baru disimpan
            $size = Storage::size($path);

            // Simpan informasi file ke database
            $file = File::create([
                'name' => $request->name,
                'path' => $path,
                'size' => $size, // Catatan: ukuran dalam satuan byte!
                'mime_type' => 'text/plain', // Set tipe MIME untuk file teks
                'user_id' => $request->user()->id,
                'folder_id' => $request->folder_id,
            ]);

            $userData = User::where('id', $userId)->first();

            $userInstance = $userData->instances->pluck('id')->toArray();  // Mengambil instance user
            $file->instances()->sync($userInstance);  // Sinkronisasi instance ke folder baru

            if ($request->tags) {
                // Proses tags
                $tagIds = [];

                foreach ($request->tags as $tagName) {
                    // Periksa apakah tag sudah ada di database (case-insensitive)
                    $tag = Tags::whereRaw('LOWER(name) = ?', [strtolower($tagName)])->first();

                    if (!$tag) {
                        // Jika tag belum ada, buat tag baru
                        $tag = Tags::create(['name' => ucfirst($tagName)]);
                    }

                    // Ambil id dari tag (baik yang sudah ada atau baru dibuat)
                    $tagIds[] = $tag->id;

                    // Masukkan id dan name dari tag ke dalam array untuk response
                    $tagsData[] = [
                        'id' => $tag->id,
                        'name' => $tag->name
                    ];
                }

                $file->tags()->sync($tagIds);
            }

            DB::commit();

            $file->makeHidden(['path', 'nanoid']);

            $file['tags'] = $tagsData;

            $file->load('instances');

            return response()->json([
                'message' => 'File created and saved to storage successfully.',
                'data' => $file,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error occurred on creating a file: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred while creating the file.',
            ], 500);
        }
    }

    /**
     * Upload a file.
     */
    public function upload(Request $request)
    {
        $user = Auth::user();

        // Validasi input, mengubah 'file' menjadi array
        $validator = Validator::make($request->all(), [
            'file' => 'required|array',
            'file.*' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,xlsx,txt,mp3,ogg,wav,aac,opus,mp4,hevc,mkv,mov,h264,h265,php,js,html,css',
            'folder_id' => 'nullable|integer|exists:folders,id',
            'tags' => 'nullable|array',
            'tags.*' => ['string', 'regex:/^[a-zA-Z\s]+$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // START MYSQL TRANSACTION
        DB::beginTransaction();

        try {
            $filesData = []; // Array untuk menyimpan data file yang berhasil diunggah

            $userData = User::where('id', $user->id)->first();
            $userInstances = $userData->instances->pluck('id')->toArray();  // Mengambil instance user

            foreach ($request->file('file') as $uploadedFile) {
                $originalFileName = $uploadedFile->getClientOriginalName(); // Nama asli file
                $fileExtension = $uploadedFile->getClientOriginalExtension(); // Ekstensi file

                // Generate NanoID untuk nama file
                $nanoid = (new \Hidehalo\Nanoid\Client())->generateId();
                $storageFileName = $nanoid . '.' . $fileExtension;

                // Tentukan folder tujuan
                $folderId = $request->folder_id ?? Folder::where('user_id', $user->id)->whereNull('parent_id')->first()->id;

                // Path sementara
                $tempPath = storage_path('app/temp/' . $storageFileName);
                $uploadedFile->move(storage_path('app/temp'), $storageFileName);

                // Pemindaian file dengan PHP Antimalware Scanner
                $scanner = new Scanner();
                $scanResult = $scanner->setPathScan($tempPath)->run();

                if ($scanResult->detected >= 1) {
                    // Hapus file jika terdeteksi virus
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    DB::rollBack();
                    return response()->json(['errors' => 'File berisi konten berbahaya. File otomatis dihapus'], 422);
                }

                // Pindahkan file yang telah discan ke storage utama
                $path = $this->generateFilePath($folderId, $storageFileName);
                Storage::put($path, file_get_contents($tempPath));

                // Ambil ukuran file dari storage utama
                $fileSize = Storage::size($path);

                Log::info("File Temp: " . $tempPath);

                // Hapus file sementara setelah dipindahkan
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }

                // Buat catatan file di database
                $file = File::create([
                    'name' => $originalFileName,
                    'path' => $path,
                    'size' => $fileSize, // Catatan: ukuran dalam satuan byte!
                    'type' => $fileExtension,
                    'user_id' => $user->id,
                    'folder_id' => $folderId,
                    'nanoid' => $nanoid,
                ]);

                $file->instances()->sync($userInstances);

                if ($request->tags) {
                    // Proses tags
                    $tagIds = [];

                    foreach ($request->tags as $tagName) {
                        // Periksa apakah tag sudah ada di database (case-insensitive)
                        $tag = Tags::whereRaw('LOWER(name) = ?', [strtolower($tagName)])->first();

                        if (!$tag) {
                            // Jika tag belum ada, buat tag baru
                            $tag = Tags::create(['name' => ucfirst($tagName)]);
                        }

                        // Ambil id dari tag (baik yang sudah ada atau baru dibuat)
                        $tagIds[] = $tag->id;

                        // Masukkan id dan name dari tag ke dalam array untuk response
                        $tagsData[] = [
                            'id' => $tag->id,
                            'name' => $tag->name
                        ];
                    }

                    $file->tags()->sync($tagIds);

                    $file['tags'] = $tagsData;
                }

                $file->makeHidden(['path', 'nanoid']);

                $file->load('instances');

                // Tambahkan file ke dalam array yang akan dikembalikan
                $filesData[] = $file;
            }

            // COMMIT TRANSACTION JIKA TIDAK ADA ERROR
            DB::commit();

            Log::info('Files uploaded and scanned successfully.', [
                'userId' => $user->id,
                'folderId' => $folderId,
            ]);

            return response()->json([
                'message' => 'Files uploaded successfully.',
                'data' => [
                    'files' => $filesData,
                ],
            ], 201)->header('Access-Control-Allow-Origin', '*');
        } catch (Exception $e) {
            // ROLLBACK TRANSACTION JIKA ADA KESALAHAN
            DB::rollBack();

            // Hapus file sementara jika terjadi kesalahan
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }

            Log::error('Error occurred while uploading files: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'userId' => $user->id,
                'fileId' => $request->input('file_id', null),
            ]);

            return response()->json([
                'errors' => 'An error occurred while uploading the files.',
            ], 500);
        }
    }

    /**
     * Add a tag to a file.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addTagToFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|integer|exists:files,id',
            'tag_id' => 'required|integer|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Periksa perizinan
        // $permissionCheck = $this->checkPermissionFolder($request->folder_id, 'folder_edit');
        // if (!$permissionCheck) {
        //     return response()->json([
        //         'errors' => 'You do not have permission to add tag to this folder.',
        //     ], 403);
        // }

        DB::beginTransaction();

        try {
            $file = File::findOrFail($request->file_id);
            $tag = Tags::findOrFail($request->tag_id);

            // Memeriksa apakah tag sudah terkait dengan file
            if ($file->tags->contains($tag->id)) {
                return response()->json([
                    'errors' => 'Tag already exists in folder.'
                ], 409);
            }

            // Menambahkan tag ke file (tabel pivot file_has_tags)
            $file->tags()->attach($tag->id);

            DB::commit();

            return response()->json([
                'message' => 'Successfully added tag to file.',
                'data' => [
                    'file_id' => $file->id,
                    'file_name' => $file->name,
                    'tag_id' => $tag->id,
                    'tag_name' => $tag->name
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {

            DB::rollBack();

            return response()->json([
                'errors' => 'File or tag not found.'
            ], 404);
        } catch (Exception $e) {

            DB::rollBack();

            Log::error('Error occurred on adding tag to file: ' . $e->getMessage(), [
                'file_id' => $request->file_id,
                'tag_id' => $request->tag_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred on adding tag to file.'
            ], 500);
        }
    }

    /**
     * Remove a tag from a file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeTagFromFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|integer|exists:files,id',
            'tag_id' => 'required|integer|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $file = File::findOrFail($request->file_id);
            $tag = Tags::findOrFail($request->tag_id);

            // Memeriksa apakah tag terkait dengan file
            if (!$file->tags->contains($tag->id)) {
                return response()->json([
                    'errors' => 'Tag not found in file.'
                ], 404);
            }

            // Menghapus tag dari file (tabel pivot file_has_tags)
            $file->tags()->detach($tag->id);

            DB::commit();

            return response()->json([
                'message' => 'Successfully removed tag from file.',
                'data' => [
                    'file' => $file
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {

            DB::rollBack();

            return response()->json([
                'errors' => 'File or tag not found.'
            ], 404);
        } catch (Exception $e) {

            DB::rollBack();

            Log::error('Error occurred on removing tag from file: ' . $e->getMessage(), [
                'file_id' => $request->file_id,
                'tag_id' => $request->tag_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred on removing tag from file.'
            ], 500);
        }
    }

    /**
     * Update the name of a file.
     */
    public function updateFile(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $file = File::findOrFail($id);

            // Update file name in storage
            $oldFullPath = $file->path;
            $newPath = $this->generateFilePath($file->folder_id, $file->nanoid);

            if (Storage::exists($oldFullPath)) {
                Storage::move($oldFullPath, $newPath);
            }

            // Update file name in database
            $file->name = $request->name;
            $file->path = $newPath;
            $file->save();

            DB::commit();

            $file->makeHidden(['path', 'nanoid']);

            return response()->json([
                'message' => 'File name updated successfully.',
                'data' => $file,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'errors' => 'File not found.',
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error occurred while updating file name: ' . $e->getMessage(), [
                'fileId' => $id,
                'name' => $request->name,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'errors' => 'An error occurred while updating the file name.',
            ], 500);
        }
    }

    /**
     * Delete a file (DELETE).
     * DANGEROUS!
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'required|array',
            'file_ids.*' => 'integer|exists:files,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fileIds = $request->file_ids;

        DB::beginTransaction();

        try {
            foreach ($fileIds as $fileId) {

                $file = File::findOrFail($fileId);

                // Delete file from storage
                if (Storage::exists($file->path)) {
                    Storage::delete($file->path);
                }

                // Hapus data pivot yang terkait dengan file (tags dan instances)
                $file->tags()->detach(); // Menghapus semua data pivot pada tabel file_has_tags
                $file->instances()->detach(); // Menghapus semua data pivot pada tabel file_has_instances


                // Delete file from database
                $file->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'File(s) deleted successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'errors' => 'File not found.',
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error occurred while deleting file: ' . $e->getMessage(), [
                'fileId' => $fileIds,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'errors' => 'An error occurred while deleting the file.',
            ], 500);
        }
    }

    /**
     * Move a file to another folder.
     */
    public function move(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'new_folder_id' => 'required|integer|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $file = File::findOrFail($id);
            $oldPath = $file->path;

            // Generate new path
            $newPath = $this->generateFilePath($request->new_folder_id, $file->nanoid);

            // Check if old file path exists
            if (!Storage::exists($oldPath)) {
                return response()->json(['errors' => 'Old file path does not exist.'], 404);
            }

            // Move file in storage
            if (Storage::exists($oldPath)) {
                // Ensure the new directory exists
                $newDirectory = dirname($newPath);
                if (!Storage::exists($newDirectory)) {
                    Storage::makeDirectory($newDirectory);
                }

                Storage::move($oldPath, $newPath);
            }

            // Update file record in database
            $file->folder_id = $request->new_folder_id;
            $file->path = $newPath;
            $file->save();

            DB::commit();

            $file->makeHidden(['path', 'nanoid']);

            return response()->json([
                'message' => 'File moved successfully.',
                'data' => $file,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'errors' => 'File not found.',
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error occurred while moving file: ' . $e->getMessage(), [
                'fileId' => $id,
                'newFolderId' => $request->new_folder_id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'errors' => 'An error occurred while moving the file.',
            ], 500);
        }
    }

    /**
     * Generate the file path based on folder id and file name.
     */
    private function generateFilePath($folderId, $fileNanoid)
    {
        // Initialize an array to store the folder names
        $path = [];

        // If folderId is provided, build the path from the folder to the root
        while ($folderId) {
            // Find the folder by ID
            $folder = Folder::find($folderId);
            if ($folder) {
                // Prepend the folder name to the path array
                array_unshift($path, $folder->nanoid);
                // Set the folder ID to its parent folder's ID
                $folderId = $folder->parent_id;
            } else {
                // If the folder is not found, stop the loop
                break;
            }
        }

        // Add the file name to the end of the path
        $path[] = $fileNanoid;

        // Join the path array into a single string
        return implode('/', $path);
    }
}
