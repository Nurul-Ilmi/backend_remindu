<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\GroupService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    protected GroupService $groupService;

    public function __construct(GroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    /**
     * Display a listing of the user's groups.
     * Eager loads members with their completed tasks count for this group.
     */
    public function index(Request $request): JsonResponse
    {
        $groups = $this->groupService->getUserGroups($request->user());
        return response()->json($groups);
    }

    /**
     * Store a newly created group.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'subject'     => 'nullable|string|max:100',
        ]);

        $group = $this->groupService->createGroup($validated, $request->user());

        return response()->json($group, 201);
    }

    /**
     * Update a group's details (only Ketua can update).
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'subject'     => 'sometimes|string|max:100|nullable',
        ]);

        try {
            $updatedGroup = $this->groupService->updateGroup($group, $validated, $request->user());
            return response()->json($updatedGroup);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Delete a group (only Ketua can delete).
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        try {
            $this->groupService->deleteGroup($group, $request->user());
            return response()->json(null, 204);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Leave a group. Ketua cannot leave — must delete group or transfer ownership.
     */
    public function leave(Request $request, Group $group): JsonResponse
    {
        try {
            $this->groupService->leaveGroup($group, $request->user());
            return response()->json(['message' => 'Berhasil keluar dari grup.']);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 422;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Join a group via invite code.
     */
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'invite_code' => 'required|string'
        ]);

        $group = $this->groupService->joinGroup($request->invite_code, $request->user());

        return response()->json($group);
    }

    /**
     * Remove a member from a group (only Ketua can remove).
     */
    public function removeMember(Request $request, Group $group, \App\Models\User $user): JsonResponse
    {
        try {
            $this->groupService->removeMember($group, $user, $request->user());
            return response()->json(['message' => 'Anggota berhasil dikeluarkan.']);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }
}
