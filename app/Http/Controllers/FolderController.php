<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Tags;
use App\Models\User;
use App\Models\UserFolderPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class FolderController extends Controller
{
    /**
     * Check the user permission
     */
    private function checkPermissionFolder($folderId, $actions)
    {
        $user = Auth::user();
        $folder = Folder::find($folderId);

        // If folder not found, return 404 error and stop the process
        if (!$folder) {
            return response()->json([
                'errors' => 'Folder with Folder ID you entered not found, Please check your Folder ID and try again.'
            ], 404); // Setting status code to 404 Not Found
        }

        // Step 1: Check if the folder belongs to the logged-in user
        if ($folder->user_id === $user->id) {
            return true; // The owner has all permissions
        }

        // step ???? cek apakah user yang login adalah admin dan memiliki privilege SUPERADMIN
        if ($user->hasRole('admin') && $user->is_superadmin == 1) {
            return true;
        }
        // jika hanya admin dan tidak ada privilege SUPERADMIN, kembalikan false (tidak diizinkan)
        else if ($user->hasRole('admin')) {
            return false;
        }

        // step 2 tentang group berisi user dengan permission setiap user pada group berbeda beda, masih coming soon...

        // Step 3: Check if user has granted permission with the folder
        $userFolderPermission = UserFolderPermission::where('user_id', $user->id)->where('folder_id', $folder->id)->first();
        if ($userFolderPermission) {
            $checkPermission = $userFolderPermission->permissions;

            // Jika $actions adalah string, ubah menjadi array agar lebih mudah diperiksa
            if (!is_array($actions)) {
                $actions = [$actions];
            }

            // Periksa apakah izin pengguna ada di array $actions
            if (in_array($checkPermission, $actions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Menghitung total ukuran folder, termasuk semua subfolder dan file di dalamnya.
     */
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
     * Fungsi untuk mengonversi ukuran byte ke dalam format yang lebih mudah dibaca (KB, MB, GB).
     *
     * @param int $bytes
     * @return string
     */
    private function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        } else {
            return '0 bytes';
        }
    }

    public function storageSizeUsage()
    {
        $user = Auth::user();

        try {
            // Dapatkan folder root milik user
            $rootFolder = Folder::where('user_id', $user->id)->whereNull('parent_id')->first();

            if (!$rootFolder) {
                return response()->json([
                    'errors' => 'An error was occured. Please contact our support.'
                ]);
            }

            // Hitung total penyimpanan yang digunakan user
            $totalUsedStorage = $this->calculateFolderSize($rootFolder);

            // Format ukuran penyimpanan ke dalam KB, MB, atau GB
            $formattedStorageSize = $this->formatSizeUnits($totalUsedStorage);

            return response()->json([
                'message' => 'Your storage usage: ' . $totalUsedStorage,
                'data' => [
                    'rawSize' => $totalUsedStorage,
                    'formattedSize' => $formattedStorageSize
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error occured while retrieving storage usage: ' . $e->getMessage());

            return response()->json([
                'errors' => 'An error occured while retrieving storage usage.'
            ], 500);
        }
    }

    public function index()
    {
        $user = Auth::user();

        try {
            // Mendapatkan folder root (parent) dari user
            $parentFolder = Folder::where('user_id', $user->id)->whereNull('parent_id')->first();

            /**
             * Jika folder root tidak ditemukan, kembalikan pesan error
             */
            if (!$parentFolder) {
                return response()->json([
                    'message' => 'An error was occured. Please contact our support.'
                ], 404);
            }

            // Mendapatkan subfolder dan file dari folder root
            $userFolders = $parentFolder->subfolders;
            $files = $parentFolder->files;

            // Jika tidak ada subfolder dan file, kembalikan pesan bahwa folder atau file tidak ditemukan
            if ($userFolders->isEmpty() && $files->isEmpty()) {
                return response()->json([
                    'message' => 'No folders or files found',
                    'data' => [
                        'folders' => $userFolders,
                        'files' => $files
                    ]
                ], 200);
            }

            // Iterasi setiap folder dalam subfolders untuk menyiapkan respons
            $respondFolders = $userFolders->map(function ($folder) {
                return [
                    'folder_id' => $folder->id,
                    'name' => $folder->name,
                    'total_size' => $this->calculateFolderSize($folder), // Hitung total ukuran folder
                    'type' => $folder->type,
                    'user' => [
                        'id' => $folder->user->id,
                        'name' =>  $folder->user->name,
                        'email' =>  $folder->user->email
                    ],
                    'instance' => $folder->instances->map(function ($instance) {
                        return [
                            'id' => $instance->id,
                            'name' => $instance->name
                        ];
                    }),
                    'tags' => $folder->tags->map(function ($tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name
                        ];
                    })
                ];
            });

            return response()->json([
                'data' => [
                    'folders' => $respondFolders, // Sekarang berisi array folder dan tags
                    'files' => $files
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Error occurred on getting folders and files: ' . $e->getMessage(), [
                'parent_id' => $parentFolder->id ?? null, // Pastikan null jika parentFolder tidak ditemukan
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred while getting folders and files.',
            ], 500);
        }
    }

    public function info($id)
    {
        // periksa apakah user memiliki izin antara read atau write
        $permission = $this->checkPermissionFolder($id, ['read', 'write']);

        if (!$permission) {
            return response()->json([
                'errors' => 'You do not have permission to access this folder.',
            ], 403);
        }

        try {
            // Cari folder dengan ID yang diberikan dan sertakan subfolder jika ada
            $folder = Folder::with(['user', 'subfolders', 'files', 'tags', 'instances'])->find($id);

            // Jika folder tidak ditemukan, kembalikan pesan kesalahan
            if (!$folder) {
                return response()->json([
                    'errors' => 'Folder not found.',
                ], 404);
            }

            // Persiapkan respon untuk folder
            $folderResponse = [
                'folder_id' => $folder->id,
                'name' => $folder->name,
                'total_size' => $this->calculateFolderSize($folder),
                'type' => $folder->type,
                'parent_id' => $folder->parent_id ? $folder->parentFolder->id : null,
                'user' => [
                    'id' => $folder->user->id,
                    'name' =>  $folder->user->name,
                    'email' =>  $folder->user->email
                ],
                'instances' => $folder->instances->map(function ($instance) {
                    return [
                        'id' => $instance->id,
                        'name' => $instance->name
                    ];
                }),
                'tags' => $folder->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name
                    ];
                })
            ];

            // Persiapkan respon untuk files
            $files = $folder->files;
            $fileResponse = [];

            if ($files->isEmpty()) {
                $fileResponse = []; // Jika tidak ada file, kembalikan array kosong
            } else {
                foreach ($files as $file) {
                    $fileResponse[] = $file;
                }
            }

            return response()->json([
                'data' => [
                    'folder_info' => $folderResponse,
                    'subfolders' => $folder->subfolders,
                    'files' => $fileResponse,
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Error occurred on getting folder info: ' . $e->getMessage(), [
                'folderId' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred while getting folder info.',
            ], 500);
        }
    }

    /**
     * Create a new folder.
     */
    public function create(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string',
                'parent_id' => 'nullable|integer|exists:folders,id',
                'tags' => 'nullable|array',
                'tags.*' => ['string', 'regex:/^[a-zA-Z\s]+$/'],
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // MEMULAI TRANSACTION MYSQL
        DB::beginTransaction();

        try {
            $userLogin = Auth::user();
            $userId = $userLogin->id;

            // Dapatkan folder root pengguna, jika tidak ada parent_id yang disediakan
            $folderRootUser = Folder::where('user_id', $userId)->whereNull('parent_id')->first();

            // Periksa apakah parent_id ada pada request? , jika tidak ada maka gunakan id dari folder root user default
            // Jika ada, gunakan parent_id dari request.
            if ($request->parent_id === null) {
                $parentId = $folderRootUser->id;
            } else if ($request->parent_id) {
                // check if parent_id is another user folder, then check if user login right now have the permission to edit folder on that folder. checked with checkPermission.
                $permission = $this->checkPermissionFolder($request->parent_id, 'write');
                if (!$permission) {
                    return response()->json([
                        'errors' => 'You do not have permission to create folder in this parent_id',
                    ], 403);
                } else {
                    $parentId = $request->parent_id;
                }
            }

            // Create folder in database
            $newFolder = Folder::create([
                'name' => $request->name,
                'user_id' => $userId,
                'parent_id' => $parentId,
            ]);

            $userData = User::where('id', $userId)->first();

            $userInstance = $userData->instances->pluck('id')->toArray();  // Mengambil instance user
            $newFolder->instances()->sync($userInstance);  // Sinkronisasi instance ke folder baru

            if ($request->has('tags')) {
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

                // Simpan tags ke tabel pivot folder_has_tags
                $newFolder->tags()->sync($tagIds);
            }

            // Generate public path setelah folder dibuat
            $publicPath = $this->getPublicPath($newFolder->id);

            // Simpan public path ke folder baru
            $newFolder->update(['public_path' => $publicPath]);

            // COMMIT JIKA TIDAK ADA ERROR
            DB::commit();

            // Get NanoID folder
            $folderNameWithNanoId = $newFolder->nanoid;

            // Create folder in storage
            $path = $this->getFolderPath($newFolder->parent_id);
            $fullPath = $path . '/' . $folderNameWithNanoId;
            Storage::makeDirectory($fullPath);

            $newFolder->makeHidden(['nanoid']);

            // inject data response tagsData ke response newFolder
            $newFolder['tags'] = $tagsData;

            // Load instances untuk dimasukkan ke dalam response
            $newFolder->load('instances:id,name');

            return response()->json([
                'message' => $newFolder->parent_id ? 'Subfolder created successfully' : 'Folder created successfully',
                'data' => [
                    'folder' => $newFolder
                ]
            ], 201);
        } catch (Exception $e) {
            // ROLLBACK JIKA ADA ERROR
            DB::rollBack();

            Log::error('Error occurred on creating folder: ' . $e->getMessage(), [
                'name' => $request->name,
                'parentId' => $request->parent_id,
                'userId' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred while creating folder.',
            ], 500);
        }
    }

    /**
     * Add a tag to a folder.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addTagToFolder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'folder_id' => 'required|integer|exists:folders,id',
            'tag_id' => 'required|integer|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Periksa perizinan
        $permissionCheck = $this->checkPermissionFolder($request->folder_id, 'write');
        if (!$permissionCheck) {
            return response()->json([
                'errors' => 'You do not have permission to add tag to this folder.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $folder = Folder::findOrFail($request->folder_id);
            $tag = Tags::findOrFail($request->tag_id);

            // Memeriksa apakah tag sudah terkait dengan folder
            if ($folder->tags->contains($tag->id)) {
                return response()->json([
                    'errors' => 'Tag already exists in folder.'
                ], 409);
            }

            // Menambahkan tag ke folder (tabel pivot folder_has_tags)
            $folder->tags()->attach($tag->id);

            DB::commit();

            return response()->json([
                'message' => 'Successfully added tag to folder.',
                'data' => [
                    'folder_id' => $folder->id,
                    'folder_name' => $folder->name,
                    'tag_id' => $tag->id,
                    'tag_name' => $tag->name
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {

            DB::rollBack();

            return response()->json([
                'errors' => 'Folder or tag not found.'
            ], 404);
        } catch (Exception $e) {

            DB::rollBack();

            Log::error('Error occurred on adding tag to folder: ' . $e->getMessage(), [
                'folder_id' => $request->folder_id,
                'tag_id' => $request->tag_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred on adding tag to folder.'
            ], 500);
        }
    }

    /**
     * Remove a tag from a folder.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeTagFromFolder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'folder_id' => 'required|integer|exists:folders,id',
            'tag_id' => 'required|integer|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Periksa perizinan
        $permissionCheck = $this->checkPermissionFolder($request->folder_id, 'write'); // misalnya folder_edit adalah action untuk edit atau modifikasi
        if (!$permissionCheck) {
            return response()->json([
                'errors' => 'You do not have permission to remove tag from this folder.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $folder = Folder::findOrFail($request->folder_id);
            $tag = Tags::findOrFail($request->tag_id);

            // Memeriksa apakah tag terkait dengan folder
            if (!$folder->tags->contains($tag->id)) {
                return response()->json([
                    'errors' => 'Tag not found in folder.'
                ], 404);
            }

            // Menghapus tag dari folder (tabel pivot folder_has_tags)
            $folder->tags()->detach($tag->id);

            DB::commit();

            return response()->json([
                'message' => 'Successfully removed tag from folder.',
                'data' => []
            ], 200);
        } catch (ModelNotFoundException $e) {

            DB::rollBack();

            return response()->json([
                'errors' => 'Folder or tag not found.'
            ], 404);
        } catch (Exception $e) {

            DB::rollBack();

            Log::error('Error occurred on removing tag from folder: ' . $e->getMessage(), [
                'folder_id' => $request->folder_id,
                'tag_id' => $request->tag_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred on removing tag from folder.'
            ], 500);
        }
    }

    /**
     * Update the name of a folder.
     */
    public function update(Request $request, $id)
    {
        $permissionCheck = $this->checkPermissionFolder($id, 'write');
        if (!$permissionCheck) {
            return response()->json([
                'errors' => 'You do not have permission to edit this folder.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'tags' => 'nullable|array',
            'tags.*' => ['string', 'regex:/^[a-zA-Z\s]+$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $folder = Folder::findOrFail($id);

            $oldNanoid = $folder->nanoid;

            $publicPath = $this->getPublicPath($folder->id);

            $folder->update([
                'name' => $request->name,
                'public_path' => $publicPath
            ]);

            if ($request->has('tags')) {
                // Process tags
                $tags = $request->tags;
                $tagIds = [];

                foreach ($tags as $tagName) {
                    // Check if tag exists, case-insensitive
                    $tag = Tags::whereRaw('LOWER(name) = ?', [strtolower($tagName)])->first();

                    if (!$tag) {
                        // If tag doesn't exist, create it
                        $tag = Tags::create(['name' => ucfirst($tagName)]);
                    }

                    $tagIds[] = $tag->id;
                }

                // Sync the tags with the folder (in the pivot table)
                $folder->tags()->sync($tagIds);
            }

            DB::commit();

            // Update folder name in storage
            $path = $this->getFolderPath($folder->parent_id);
            $oldFullPath = $path . '/' . $oldNanoid;
            $newFullPath = $path . '/' . $folder->nanoid;

            if (Storage::exists($oldFullPath)) {
                Storage::move($oldFullPath, $newFullPath);
            }

            $folder->load(['user:id,name,email', 'tags', 'instances:id,name']);

            return response()->json([
                'message' => 'Folder name updated successfully.',
                'data' => [
                    'folder' => $folder
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'errors' => 'Folder not found.',
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error occurred on updating folder name: ' . $e->getMessage(), [
                'folderId' => $id,
                'name' => $request->name,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred on updating folder.',
            ], 500);
        }
    }



    /**
     * Delete one or multiple folders.
     */
    public function delete(Request $request)
    {
        // Jika tidak ada $id, validasi bahwa folder_ids dikirim dalam request
        $validator = Validator::make($request->all(), [
            'folder_ids' => 'required|array',
            'folder_ids.*' => 'integer|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ambil daftar folder_ids dari request
        $folderIds = $request->folder_ids;

        DB::beginTransaction();

        try {
            foreach ($folderIds as $folderId) {
                // Cek izin untuk setiap folder
                $permissionCheck = $this->checkPermissionFolder($folderId, 'write');
                if (!$permissionCheck) {
                    Log::error('Some users was trying to delete one of the selected folders that not have permission: ' . $folderId);
                    return response()->json([
                        'errors' => 'You do not have permission to delete one of the selected folders.',
                    ], 403);
                }

                // Temukan folder berdasarkan ID
                $folder = Folder::findOrFail($folderId);

                // Hapus data pivot yang terkait dengan folder (tags dan instances)
                $folder->tags()->detach(); // Menghapus semua data pivot pada tabel folder_has_tags
                $folder->instances()->detach(); // Menghapus semua data pivot pada tabel folder_has_instances

                // Hapus folder dari database
                $folder->delete();

                // Hapus folder dari storage
                $path = $this->getFolderPath($folder->parent_id);
                $fullPath = $path . '/' . $folder->nanoid;

                if (Storage::exists($fullPath)) {
                    Storage::deleteDirectory($fullPath);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Folder(s) deleted successfully.',
                'data' => []
            ], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'errors' => 'Folder not found: ' . $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error occurred on deleting folder(s): ' . $e->getMessage(), [
                'folderIds' => $folderIds,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred on deleting folder(s).',
            ], 500);
        }
    }

    /**
     * Move a folder to another folder with parent_id folder technique.
     */
    public function move(Request $request)
    {
        $user = Auth::user();

        $permissionCheck = $this->checkPermissionFolder($request->folder_id, 'write');
        if (!$permissionCheck) {
            return response()->json([
                'errors' => 'You do not have permission to move this folder.',
            ], 403);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'folder_id' => 'required|integer|exists:folders,id',
                'new_parent_id' => 'required|integer|exists:folders,id',
            ],
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $folder = Folder::findOrFail($request->folder_id);

            $oldParentId = $folder->parent_id;

            //periksa apakah new_parent_id (folder tujuan yang dipilih) pada request adalah milik user sendiri atau milik user lain. jika dimiliki oleh user lain, periksa apakah user saat ini memiliki izin untuk memindahkan ke folder milik orang lain itu.
            $checkIfNewParentIdIsBelongsToUser = Folder::where('id', $request->new_parent_id)->where('user_id', $user->id)->exists();

            // jika tidak ada izin pada folder tujuan
            if (!$checkIfNewParentIdIsBelongsToUser) {
                $permissionCheck = $this->checkPermissionFolder($request->new_parent_id, 'write');
                if (!$permissionCheck) {
                    return response()->json([
                        'errors' => 'You do not have permission on the folder you are trying to move this folder to.',
                    ], 403);
                }
            }
            $folder->parent_id = $request->new_parent_id;
            $folder->save();
            DB::commit();

            // Move folder in storage
            $oldPath = $this->getFolderPath($oldParentId);
            $newPath = $this->getFolderPath($folder->parent_id);
            $oldFullPath = $oldPath . '/' . $folder->nanoid;
            $newFullPath = $newPath . '/' . $folder->nanoid;

            if (Storage::exists($oldFullPath)) {
                Storage::move($oldFullPath, $newFullPath);
            }

            $folder->load(['user:id,name,email', 'tags', 'instances:id,name']);

            return response()->json([
                'message' => 'Folder moved successfully.',
                'data' => [
                    'folder' => $folder
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'errors' => 'Folder not found.',
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error occurred on moving folder: ' . $e->getMessage(), [
                'folderId' => $request->folder_id,
                'newParentId' => $request->new_parent_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred on moving the folder.',
            ], 500);
        }
    }

    /**
     * Get folder path based on parent folder id.
     */
    private function getFolderPath($parentId)
    {
        if ($parentId === null) {
            return ''; // Root directory, no need for 'folders' base path
        }

        $parentFolder = Folder::findOrFail($parentId);
        $path = $this->getFolderPath($parentFolder->parent_id);

        // Use the folder's NanoID in the storage path
        $folderNameWithNanoId = $parentFolder->nanoid;

        return $path . '/' . $folderNameWithNanoId;
    }

    /**
     * Get full path of folder and subfolder.
     *
     */
    public function getPublicPath($id)
    {
        try {
            $folder = Folder::findOrFail($id);
            $path = [];

            while ($folder) {
                array_unshift($path, $folder->name);
                $folder = $folder->parentFolder;
            }

            return implode('/', $path);
        } catch (Exception $e) {
            Log::error('Error occurred on getting folder path: ' . $e->getMessage(), [
                'folder_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred on getting folder path.',
            ], 500);
        }
    }

    // Untuk API path
    public function getFullPath($id)
    {
        try {
            $folder = Folder::findOrFail($id);
            $path = [];

            while ($folder) {
                array_unshift($path, $folder->name);
                $folder = $folder->parentFolder;
            }

            $pathReady = implode('/', $path);

            return response()->json([
                'full_path' => $pathReady
            ], 200);
        } catch (Exception $e) {
            Log::error('Error occurred on getting folder path: ' . $e->getMessage(), [
                'folder_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred on getting folder path.',
            ], 500);
        }
    }
}
