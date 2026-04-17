<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ControlCenterService;

class ControlCenterController extends Controller
{
    public function __construct(private ControlCenterService $controlCenterService) {}

    /**
     * Return the list of known ATC positions from Control Center.
     * Results are served from cache to avoid hammering the upstream API.
     *
     * GET /api/positions
     */
    public function positions()
    {
        $positions = $this->controlCenterService->getPositions();

        return response()->json($positions);
    }
}
