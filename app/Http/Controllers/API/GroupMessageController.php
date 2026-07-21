<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\GroupMessageService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupMessageController extends Controller
{
    protected GroupMessageService $messageService;

    public function __construct(GroupMessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    /**
     * Get all messages for a specific group.
     */
    public function index(Request $request, Group $group): JsonResponse
    {
        try {
            $messages = $this->messageService->getMessages($group, $request->user());
            return response()->json($messages);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Send a new message to a group.
     */
    public function store(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        try {
            $message = $this->messageService->sendMessage($group, $validated, $request->user());
            return response()->json($message, 201);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }
}
