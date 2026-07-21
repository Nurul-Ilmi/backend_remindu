<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    protected $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Search tasks and groups for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = $request->query('q', '');
        $result = $this->searchService->search($user, $query);
        return response()->json($result);
    }
}
