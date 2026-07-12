<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get the dashboard summary.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getDashboardSummary($request->user());
        return response()->json($data);
    }
}
