<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\TaskService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    protected TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * GET /api/tasks
     * List authenticated user's tasks with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tasks = $this->taskService->getTasks($request->user(), [], $request->integer('per_page', 20));
            return response()->json($tasks);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * POST /api/tasks
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'output_type'      => ['required', Rule::in(['Tugas','Kuis / Ujian','Organisasi','Pribadi'])],
            'load_type'        => ['required', Rule::in(['Ringan','Sedang','Berat'])],
            'involvement_type' => ['required', Rule::in(['Pribadi','Kelompok'])],
            'deadline'         => ['required', 'date', 'after:yesterday'],
            'group_id'         => ['nullable', 'exists:groups,id'],
            'xp_reward'        => ['nullable', 'integer', 'min:5', 'max:500'],
            'wa_notif_enabled' => ['boolean'],
            'assigned_to'      => ['nullable', 'exists:users,id'],
            'assign_to_all'    => ['boolean', 'nullable'],
        ]);

        try {
            $taskData = $validated;
            unset($taskData['assign_to_all']);
            unset($taskData['assigned_to']);

            $result = $this->taskService->createTask(
                $request->user(),
                $taskData,
                $request->boolean('assign_to_all'),
                $request->assigned_to
            );

            if ($request->boolean('assign_to_all')) {
                return response()->json([
                    'message' => 'Tugas berhasil dibuat untuk semua anggota',
                    'data' => $result->first()
                ], 201);
            }

            return response()->json($result, 201);

        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * GET /api/tasks/{task}
     */
    public function show(Request $request, Task $task): JsonResponse
    {
        try {
            $task = $this->taskService->getTask($task, $request->user());
            return response()->json($task);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * PATCH /api/tasks/{task}
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title'            => ['sometimes', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'output_type'      => ['sometimes', Rule::in(['Tugas','Kuis / Ujian','Organisasi','Pribadi'])],
            'load_type'        => ['sometimes', Rule::in(['Ringan','Sedang','Berat'])],
            'involvement_type' => ['sometimes', Rule::in(['Pribadi','Kelompok'])],
            'status'           => ['sometimes', Rule::in(['todo','in_progress','done','overdue'])],
            'kanban_column'    => ['sometimes', Rule::in(['todo','in_progress','done'])],
            'deadline'         => ['sometimes', 'date'],
            'wa_notif_enabled' => ['boolean'],
        ]);

        try {
            $updatedTask = $this->taskService->updateTask($task, $request->user(), $validated);
            return response()->json($updatedTask);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * DELETE /api/tasks/{task}
     */
    public function destroy(Request $request, Task $task): JsonResponse
    {
        try {
            $this->taskService->deleteTask($task, $request->user());
            return response()->json(null, 204);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * POST /api/tasks/{task}/done
     * Convenience endpoint to mark a task complete & award XP.
     */
    public function markDone(Request $request, Task $task): JsonResponse
    {
        try {
            $updatedTask = $this->taskService->markTaskDone($task, $request->user());
            return response()->json(['message' => 'Task marked as done.', 'xp_awarded' => $updatedTask->xp_reward]);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * POST /api/tasks/{task}/delay
     * Delay task deadline by 15 mins and deduct 5 XP
     */
    public function delay(Request $request, Task $task): JsonResponse
    {
        try {
            $this->taskService->delayTask($task, $request->user());
            return response()->json(['message' => 'Tugas ditunda 15 menit', 'xp_deducted' => -5]);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }
}
