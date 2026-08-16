<?php

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Analytics\Services\AnalyticsService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Render the Inertia Analytics Dashboard View with date range filters
     */
    public function index(Request $request): Response
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $timezone = $request->query('timezone', 'UTC');

        $metrics = $this->analyticsService->getOverviewMetrics($dateFrom, $dateTo, $timezone);
        $tasks = \App\Models\CustomerTask::with('contact')->orderBy('due_at', 'asc')->get();

        return Inertia::render('Modules/Analytics/Index', [
            'metrics'        => $metrics,
            'recentMessages' => $metrics['recent_messages'] ?? [],
            'tasks'          => $tasks,
            'filters'        => [
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'timezone'  => $timezone,
            ]
        ]);
    }

    /**
     * API: Get JSON metrics breakdown filtered by date range & timezone
     */
    public function overview(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $timezone = $request->query('timezone', 'UTC');

        return response()->json([
            'success' => true,
            'data'    => $this->analyticsService->getOverviewMetrics($dateFrom, $dateTo, $timezone)
        ]);
    }
}
