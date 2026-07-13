<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\AdminService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function dashboard(): JsonResponse
    {
        $data = $this->adminService->getDashboardStats();
        return response()->json($data);
    }

    public function users(): JsonResponse
    {
        $users = User::orderBy('created_at', 'desc')->paginate(15);
        return response()->json($users);
    }

    public function settings(): JsonResponse
    {
        $settings = AppSetting::all();
        return response()->json($settings);
    }

    public function updateSetting(Request $request, $id): JsonResponse
    {
        $setting = AppSetting::findOrFail($id);
        
        $request->validate([
            'value' => 'required',
        ]);

        $setting->update([
            'value' => $request->value,
        ]);

        return response()->json($setting);
    }

    public function deleteUser($id): JsonResponse
    {
        $user = User::findOrFail($id);

        try {
            $this->adminService->deleteUser($user);
            return response()->json(['message' => 'User deleted successfully']);
        } catch (Exception $e) {
            $status = $e->getMessage() === 'Cannot delete admin user' ? 403 : 500;
            return response()->json([
                'message' => $status === 403 ? $e->getMessage() : 'Failed to delete user.',
                'error' => $e->getMessage()
            ], $status);
        }
    }

    public function broadcast(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|min:5'
        ]);

        try {
            $result = $this->adminService->broadcastMessage($request->message);
            
            return response()->json([
                'message' => 'Pesan siaran berhasil dikirim ke ' . $result['count'] . ' pengguna.',
                'fonnte_response' => $result['fonnte_response']
            ]);
        } catch (Exception $e) {
            $status = in_array($e->getMessage(), [
                'Tidak ada pengguna dengan WA terhubung.',
                'Token Fonnte belum dikonfigurasi.'
            ]) ? 400 : 500;
            
            return response()->json([
                'message' => $status === 400 ? $e->getMessage() : 'Gagal mengirim pesan.',
                'error' => $e->getMessage()
            ], $status);
        }
    }

    public function updateUserXP(Request $request, $id): JsonResponse
    {
        $request->validate([
            'xp_amount' => 'required|integer'
        ]);

        $user = User::findOrFail($id);
        
        try {
            $this->adminService->updateUserXP($user, $request->xp_amount);
            return response()->json(['message' => 'XP berhasil diupdate.']);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function toggleSuspendUser($id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        try {
            $isSuspended = $this->adminService->toggleSuspendUser($user);
            $msg = $isSuspended ? 'Pengguna berhasil disuspend.' : 'Suspend pengguna berhasil dicabut.';
            return response()->json(['message' => $msg, 'is_suspended' => $isSuspended]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function groups(): JsonResponse
    {
        $groups = $this->adminService->getAllGroups();
        return response()->json($groups);
    }

    public function deleteGroup($id): JsonResponse
    {
        $group = \App\Models\Group::findOrFail($id);
        
        try {
            $this->adminService->deleteGroup($group);
            return response()->json(['message' => 'Grup berhasil dihapus.']);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function cleanupTasks(): JsonResponse
    {
        try {
            $count = $this->adminService->cleanupOldTasks();
            return response()->json(['message' => "$count tugas kedaluwarsa berhasil dibersihkan."]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
