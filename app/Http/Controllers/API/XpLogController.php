<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\XpLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class XpLogController extends Controller
{
    protected XpLogService ;

    public function __construct(XpLogService )
    {
        ->xpLogService = ;
    }

    /**
     * GET /api/xp-log
     * Return the authenticated user's XP transaction history.
     */
    public function index(Request ): JsonResponse
    {
         = Auth::user();
         = ->xpLogService->getUserLogs();
        return response()->json();
    }
}
