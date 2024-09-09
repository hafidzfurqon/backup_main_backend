<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\UserFolderPermission;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PermissionFolderController extends Controller
{

    /**
     * Check the user permission
     */
    private function checkPermission($folderId)
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

        return false;
    }

    public function getAllPermissionOnFolder($folderId)
    {
        // Periksa apakah pengguna yang meminta memiliki izin untuk melihat perizinan folder ini
        $permission = $this->checkPermission($folderId);
        if (!$permission) {
            return response()->json([
                'errors' => 'You do not have the authority to view permissions on this Folder.'
            ], 403);
        }

        try {
            // Ambil semua pengguna dengan izin yang terkait dengan folder yang diberikan
            $userFolderPermissions = UserFolderPermission::with('users')
                ->where('folder_id', $folderId)
                ->get();

            if ($userFolderPermissions->isEmpty()) {
                return response()->json([
                    'message' => 'No user has permission on this folder.'
                ], 404);
            }

            // Siapkan data untuk response
            $responseData = [];
            foreach ($userFolderPermissions as $permission) {
                $responseData[] = [
                    'user_id' => $permission->user->id,
                    'user_name' => $permission->user->name,
                    'permissions' => $permission->permissions
                ];
            }

            return response()->json([
                'message' => 'List of users with permissions on folder successfully retrieved.',
                'data' => $responseData
            ], 200);
        } catch (Exception $e) {
            Log::error('Error occurred while retrieving users with permissions for folder: ' . $e->getMessage(), [
                'folder_id' => $folderId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'errors' => 'An error occurred while retrieving the list of users with permissions for this folder.'
            ], 500);
        }
    }

    public function getPermission(Request $request)
    {
        // Validasi input request
        $validator = Validator::make(
            request()->all(),
            [
                'user_id' => 'required|integer|exists:users,id',
                'folder_id' => 'required|integer|exists:folders,id',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah user adalah pemilik folder
        $folder = Folder::find($request->folder_id);

        if (!$folder) {
            return response()->json([
                'errors' => 'Folder not found.'
            ], 404);
        }

        // Asumsi folder memiliki kolom 'owner_id' yang menyimpan ID pemilik folder
        if ($folder->user_id == $request->user_id) {
            return response()->json([
                'message' => 'Anda adalah pemilik folder.'
            ]);
        }

        // Cek permission user pada folder
        $permission = $this->checkPermission($request->folder_id);
        if (!$permission) {
            return response()->json([
                'errors' => 'You do not have the authority to view permissions on this Folder.'
            ], 403);
        }

        try {
            // Cek apakah userFolderPermission ada
            $userFolderPermission = UserFolderPermission::where('user_id', $request->user_id)
                ->where('folder_id', $request->folder_id)
                ->first();

            if (!$userFolderPermission) {
                return response()->json([
                    'errors' => 'User has no permissions for the specified folder.'
                ], 404);
            }

            return response()->json([
                'message' => 'User ' . $userFolderPermission->user->name . ' has permission for folder: ' . $userFolderPermission->folder->name,
                'data' => $userFolderPermission
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'errors' => 'An error occurred while retrieving user permission.'
            ], 500);
        }
    }

    public function grantFolderPermission(Request $request)
    {
        $validator = Validator::make(
            request()->all(),
            [
                'user_id' => 'required|integer|exists:users,id',
                'folder_id' => 'required|integer|exists:folders,id',
                'permissions' => 'required|in:read,write',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah user yang dimaksud adalah pemilik folder
        $folder = Folder::find($request->folder_id);
        if ($folder->user_id == $request->user_id) {
            return response()->json([
                'errors' => 'You cannot modify permissions for the owner of the folder.'
            ], 403);
        }

        // check if the user who owns the folder will grant permissions.
        $permission = $this->checkPermission($request->folder_id);
        if (!$permission) {
            return response()->json([
                'errors' => 'You do not have the authority to grant permissions on this Folder.'
            ], 403);
        }

        DB::beginTransaction();

        try {
            $userFolderPermission = UserFolderPermission::where('user_id', $request->user_id)->where('folder_id', $request->folder_id)->first();

            if ($userFolderPermission) {
                return response()->json([
                    'errors' => 'The user already has one of the permissions on the folder.'
                ], 409); // HTTP response konflik karena data perizinan user sudah ada sebelumnya.
            }

            $userFolderPermission = UserFolderPermission::create([
                'user_id' => $request->user_id,
                'folder_id' => $request->folder_id,
                'permissions' => $request->permissions
            ]);
            DB::commit();

            return response()->json([
                'message' => 'User ' . $userFolderPermission->user->name . ' has been granted permission ' . $userFolderPermission->permissions . ' to folder: ' . $userFolderPermission->folder->name,
                'data' => $userFolderPermission
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('An error occured while granting user permission: ' . $e->getMessage());
            return response()->json([
                'errors' => 'An error occured while granting user permission.'
            ], 500);
        }
    }

    public function changeFolderPermission(Request $request)
    {
        $validator = Validator::make(
            request()->all(),
            [
                'user_id' => 'required|integer|exists:users,id',
                'folder_id' => 'required|integer|exists:folders,id',
                'permissions' => 'required|in:read,write',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah user yang dimaksud adalah pemilik folder
        $folder = Folder::find($request->folder_id);
        if ($folder->user_id == $request->user_id) {
            return response()->json([
                'errors' => 'You cannot modify permissions for the owner of the folder.'
            ], 403);
        }

        // check if the user who owns the folder will revoke permissions.
        $permission = $this->checkPermission($request->folder_id);
        if (!$permission) {
            return response()->json([
                'errors' => 'You do not have the authority to change permissions on this Folder.'
            ], 403);
        }

        DB::beginTransaction();

        try {
            $userFolderPermission = UserFolderPermission::where('user_id', $request->user_id)->where('folder_id', $request->folder_id)->first();

            if (!$userFolderPermission) {
                return response()->json([
                    'errors' => 'The user whose permissions you want to change is not registered in the permissions data.'
                ], 404);
            }

            // custom what permission to be revoked
            $userFolderPermission->permissions = $request->permissions;
            $userFolderPermission->save();
            DB::commit();

            return response()->json([
                'message' => 'Successfully change permission for user ' . $userFolderPermission->user->name . ' to ' . $userFolderPermission->permissions . ' on folder: ' . $userFolderPermission->folder->name,
                'data' => $userFolderPermission
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('An error occured while changing user permission: ' . $e->getMessage());
            return response()->json([
                'errors' => 'An error occured while changing user permission.'
            ], 500);
        }
    }

    public function revokeFolderPermission(Request $request)
    {
        $validator = Validator::make(
            request()->all(),
            [
                'user_id' => 'required|integer|exists:users,id',
                'folder_id' => 'required|integer|exists:folders,id',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah user yang dimaksud adalah pemilik folder
        $folder = Folder::find($request->folder_id);
        if ($folder->user_id == $request->user_id) {
            return response()->json([
                'errors' => 'You cannot modify permissions for the owner of the folder.'
            ], 403);
        }

        // check if the user who owns the folder will revoke permissions.
        $permission = $this->checkPermission($request->folder_id);
        if (!$permission) {
            return response()->json([
                'errors' => 'You do not have the authority to revoke permissions on this Folder.'
            ], 403);
        }

        DB::beginTransaction();

        try {
            $userFolderPermission = UserFolderPermission::where('user_id', $request->user_id)->where('folder_id', $request->folder_id)->first();

            if (!$userFolderPermission) {
                return response()->json([
                    'errors' => 'The user whose permissions you want to revoke is not registered in the permissions data.'
                ], 404);
            }

            $userFolderPermission->delete();
            DB::commit();

            return response()->json([
                'message' => 'All permission for user ' . $userFolderPermission->user->name . ' on folder ' . $userFolderPermission->folder->name . ' has been revoked.'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('An error occured while revoking user permission: ' . $e->getMessage());
            return response()->json([
                'errors' => 'An error occured while revoking user permission.'
            ], 500);
        }
    }
}
