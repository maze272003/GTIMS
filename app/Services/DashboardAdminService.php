<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\AuditEvent;
use App\Models\HistoryLog;
use App\Models\Inventory;
use App\Models\IncomingRequest;
use App\Models\Product;
use App\Models\Patientrecords;
use App\Models\ProductMovement;
use App\Models\Branch; // <--- Imported Branch Model
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Repositories\Interfaces\DashboardRepositoryInterface;
use Illuminate\Validation\Rule;

class DashboardAdminService
{
    public function __construct(
        protected DashboardRepositoryInterface $dashboardRepository,
        protected SystemAnalyticsService $systemAnalyticsService,
        protected AnalyticsService $analyticsService,
        protected BranchAccessService $branchAccessService
    ) {
    }
    /**
     * Show the admin dashboard with analytics or return AJAX data.
     */
// Add | RedirectResponse to the end
public function showdashboard(Request $request): View | JsonResponse | RedirectResponse    {
        $user = \Illuminate\Support\Facades\Auth::user();

        if (!$user->hasPermission('dashboard.view')) {
        return redirect()->route('admin.orders.index');

    }
        // === 0. GET FILTERS WITH DEFAULTS ===
        $inputs = $request->validate([
            'filter_timespan' => 'nullable|string|in:7d,30d,90d,1y,all,custom',
            'filter_start' => 'nullable|date|required_if:filter_timespan,custom',
            'filter_end' => 'nullable|date|required_if:filter_timespan,custom|after_or_equal:filter_start',
            'filter_barangay' => 'nullable|string|max:255',
            'filter_branch' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'filter_product_id' => 'nullable|integer|exists:products,id',
            'forecast_days' => 'nullable|integer|in:30,60,90,180',
            'grouping' => 'nullable|string|in:day,week,month',
            'drilldown_product_id' => 'nullable|integer|exists:products,id',
            'seasonal_product_id' => 'nullable|integer|exists:products,id',
            'compare_product_id' => 'nullable|integer|exists:products,id',
            'ajax_update' => 'nullable|string|in:forecast,seasonal,main_charts,observability'
        ]);

        $timespan = $inputs['filter_timespan'] ?? '30d';
        $filter_barangay = $inputs['filter_barangay'] ?? null;
        $filter_branch = $this->branchAccessService->resolveBranchFilter(
            $user,
            $inputs['filter_branch'] ?? null,
            defaultToUserBranch: true
        );
        $inputs['filter_branch'] = $filter_branch;
        $filter_product_id = $inputs['filter_product_id'] ?? null;
        $forecast_days = $inputs['forecast_days'] ?? 90;
        $grouping = $inputs['grouping'] ?? 'day';

        // Prioritize drilldown, but allow filter_product_id to be set
        $active_product_id = $inputs['drilldown_product_id'] ?? $filter_product_id;

        $drilldown_product_id = $inputs['drilldown_product_id'] ?? null;
        $drilldownProduct = $this->dashboardRepository->findProduct($active_product_id);
        $drilldown_product_name = $drilldownProduct->generic_name ?? null;

        $seasonal_product_id = $inputs['seasonal_product_id'] ?? $this->dashboardRepository->getFirstActiveProductId();
        $compare_product_id = $inputs['compare_product_id'] ?? null;

        $dateRange = $this->calculateDateRange(
            $timespan,
            $inputs['filter_start'] ?? null,
            $inputs['filter_end'] ?? null
        );

        // Adjust date range based on grouping if needed
        if (in_array($grouping, ['week', 'month'])) {
             $minDays = ($grouping == 'week') ? 14 : 60;
             if ($dateRange->start->diffInDays($dateRange->end) < $minDays) {
                 $newStartDate = Carbon::now()->subDays(max($minDays, 89))->startOfDay();
                 if ($timespan == 'all') {
                     $allTimeStart = ProductMovement::min('created_at');
                     if ($allTimeStart && Carbon::parse($allTimeStart)->lt($newStartDate)) {
                         $dateRange->start = Carbon::parse($allTimeStart)->startOfDay();
                     } else { $dateRange->start = $newStartDate; }
                 } else { $dateRange->start = $newStartDate; }
             }
        }

        // === 1. AJAX: Forecast Table Update ===
        if ($request->ajax() && $request->input('ajax_update') == 'forecast') {
            // Pass branch filter to forecast
            $forecast = $this->calculateStockForecast($forecast_days, $filter_branch);
            $forecastHtml = view('admin.partials._forecast_table_body', compact('forecast'))->render();
            return response()->json(['forecastHtml' => $forecastHtml]);
        }

        // === 2. AJAX: Seasonal Chart Update ===
        if ($request->ajax() && $request->input('ajax_update') == 'seasonal') {
            $seasonalData = $this->getSeasonalDataForAjax($seasonal_product_id, $compare_product_id);
            return response()->json(['seasonal' => $seasonalData]);
        }

        // === 3. AJAX: Observability Update ===
        if ($request->ajax() && $request->input('ajax_update') === 'observability') {
            return response()->json([
                'observability' => $this->buildObservabilityPayload($dateRange, $filter_branch, $grouping),
            ]);
        }

        // === 3. AJAX: Main Charts / Drilldown Update ===
        if ($request->ajax() || $request->wantsJson()) {
            // Consumption Trend Data (Pass Branch)
            [$consumptionLabels, $consumptionData] = $this->getConsumptionTrend(
                $dateRange, $active_product_id, $filter_barangay, $grouping, $filter_branch
            );

            // Patient Visit Trend Data (Pass Branch)
            [$patientVisitLabels, $patientVisitData] = $this->getPatientVisitTrend(
                $dateRange, $filter_barangay, $drilldownProduct, $grouping, $filter_branch
            );

            // Barangay Data for Stacked Chart
            $barangayCategoryData = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
                ->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                ->when($filter_barangay, function ($q) use ($filter_barangay) {
                    return $q->where('barangays.barangay_name', $filter_barangay);
                })
                ->when($filter_branch, function ($q) use ($filter_branch) { // <--- BRANCH FILTER
                    return $q->where('patientrecords.branch_id', $filter_branch);
                })
                ->when($drilldownProduct, function ($query) use ($drilldownProduct) {
                    return $query->whereHas('dispensedMedications', function ($q) use ($drilldownProduct) {
                        $q->where('generic_name', $drilldownProduct->generic_name)
                            ->where('brand_name', $drilldownProduct->brand_name)
                            ->where('strength', $drilldownProduct->strength)
                            ->where('form', $drilldownProduct->form);
                    });
                })
                ->groupBy('barangays.barangay_name', 'patientrecords.category')
                ->select('barangays.barangay_name as barangay', 'patientrecords.category', DB::raw('COUNT(DISTINCT patientrecords.id) as total'))
                ->orderBy('barangays.barangay_name')
                ->get();

            $barangays = $barangayCategoryData->pluck('barangay')->unique()->values()->toArray();
            $categories = ['Adult', 'Child', 'Senior'];
            $barangayStackedData = [];
            foreach ($categories as $category) {
                $data = [];
                foreach ($barangays as $barangay) {
                    $count = $barangayCategoryData
                        ->where('barangay', $barangay)
                        ->where('category', $category)
                        ->first()->total ?? 0;
                    $data[] = $count;
                }
                $barangayStackedData[$category] = $data;
            }

            // Hotspots Data
            $patientHotspots = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
                ->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                ->when($filter_barangay, function ($q) use ($filter_barangay) {
                    return $q->where('barangays.barangay_name', $filter_barangay);
                })
                ->when($filter_branch, function ($q) use ($filter_branch) { // <--- BRANCH FILTER
                    return $q->where('patientrecords.branch_id', $filter_branch);
                })
                ->when($drilldownProduct, function ($query) use ($drilldownProduct) {
                    return $query->whereHas('dispensedMedications', function ($q) use ($drilldownProduct) {
                        $q->where('generic_name', $drilldownProduct->generic_name)
                            ->where('brand_name', $drilldownProduct->brand_name)
                            ->where('strength', $drilldownProduct->strength)
                            ->where('form', $drilldownProduct->form);
                    });
                })
                ->join('dispensedmedications', 'patientrecords.id', '=', 'dispensedmedications.patientrecord_id')
                ->groupBy('barangays.barangay_name', 'patientrecords.category')
                ->select(
                    'barangays.barangay_name as barangay',
                    'patientrecords.category',
                    DB::raw('COUNT(DISTINCT patientrecords.id) as total_patients'),
                    DB::raw('SUM(dispensedmedications.quantity) as total_items')
                )
                ->orderBy('total_items', 'desc')
                ->take(10)
                ->get();

            // Top Products Data (Needed for main_charts update)
            $topProductsQuery = ProductMovement::where('product_movements.type', 'OUT')
                ->whereBetween('product_movements.created_at', [$dateRange->start, $dateRange->end])
                ->when($filter_product_id, function ($query) use ($filter_product_id) {
                    return $query->where('product_movements.product_id', $filter_product_id);
                })
                ->when($filter_branch, function($q) use ($filter_branch) { // <--- BRANCH FILTER LOGIC FOR MOVEMENTS
                     // Logic: Find patient records for this branch, then find movements linked to those records via description
                     $validRecordIds = Patientrecords::where('branch_id', $filter_branch)->pluck('id');
                     $q->where(function($sub) use ($validRecordIds) {
                        if($validRecordIds->isEmpty()) {
                            $sub->whereRaw('1=0');
                        } else {
                            foreach($validRecordIds as $id) {
                                $sub->orWhere('product_movements.description', 'LIKE', "%Record: #{$id})%");
                            }
                        }
                     });
                })
                ->when($filter_barangay, function ($query) use ($filter_barangay, $dateRange) {
                    $patientRecordIds = Patientrecords::join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                                                      ->where('barangays.barangay_name', $filter_barangay)
                                                      ->whereBetween('patientrecords.date_dispensed', [$dateRange->start, $dateRange->end])
                                                      ->pluck('patientrecords.id');
                    return $query->where(function($q) use ($patientRecordIds) {
                        if ($patientRecordIds->isEmpty()) {
                            $q->whereRaw('1 = 0');
                        } else {
                            foreach ($patientRecordIds as $id) {
                                $q->orWhere('product_movements.description', 'LIKE', "%Record: #{$id})%");
                            }
                        }
                    });
                })
                ->join('products', 'product_movements.product_id', '=', 'products.id')
                ->groupBy('product_movements.product_id', 'products.generic_name')
                ->select('product_movements.product_id', 'products.generic_name', DB::raw('SUM(product_movements.quantity) as total_dispensed'))
                ->orderBy('total_dispensed', 'desc')
                ->take(10);

            $topProductsData = $topProductsQuery->get();
            $topProducts = $topProductsData->pluck('total_dispensed', 'generic_name');

            $hotspotsHtml = view('admin.partials._hotspots_table_body', compact('patientHotspots'))->render();

            // Determine Branch Label
            $branchName = $filter_branch ? ($this->dashboardRepository->findBranchName((int) $filter_branch) ?? 'Unknown') : 'All Branches';

            return response()->json([
                'consumptionLabels' => $consumptionLabels,
                'consumptionData' => $consumptionData,
                'hotspotsHtml' => $hotspotsHtml,
                'drilldownProductName' => $drilldown_product_name,
                'filterTimespanLabel' => $this->getTimespanLabel($timespan, $dateRange),
                'filterBarangayLabel' => $filter_barangay ?? 'All Barangays',
                'filterProductLabel' => $drilldownProduct->generic_name ?? 'All Products',
                'filterBranchLabel' => $branchName, // <--- PASSED TO JS
                'topProducts' => [
                    'labels'    => $topProducts->keys(),
                    'data'      => $topProducts->values(),
                    'drilldown' => $topProductsData->map(function($item) {
                                     return ['label' => $item->generic_name, 'id' => $item->product_id];
                                 }),
                ],
                'barangay' => [
                    'labels' => $barangays,
                    'stackedData' => $barangayStackedData,
                ],
                'patientVisit' => [
                    'labels' => $patientVisitLabels,
                    'data' => $patientVisitData,
                ],
                'observability' => $this->buildObservabilityPayload($dateRange, $filter_branch, $grouping),
            ]);
        }

        // === 4. FULL PAGE LOAD DATA ===

        // Consumption & Patient Visit
        [$consumptionLabels, $consumptionData] = $this->getConsumptionTrend(
            $dateRange, $active_product_id, $filter_barangay, $grouping, $filter_branch
        );
        [$patientVisitLabels, $patientVisitData] = $this->getPatientVisitTrend(
            $dateRange, $filter_barangay, $drilldownProduct, $grouping, $filter_branch
        );

        // Barangay Data for Stacked Chart
        $barangayCategoryData = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
            ->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
            ->when($filter_barangay, function ($q) use ($filter_barangay) {
                return $q->where('barangays.barangay_name', $filter_barangay);
            })
            ->when($filter_branch, function ($q) use ($filter_branch) { // <--- BRANCH FILTER
                return $q->where('patientrecords.branch_id', $filter_branch);
            })
            ->when($drilldownProduct, function ($query) use ($drilldownProduct) {
                return $query->whereHas('dispensedMedications', function ($q) use ($drilldownProduct) {
                    $q->where('generic_name', $drilldownProduct->generic_name)
                        ->where('brand_name', $drilldownProduct->brand_name)
                        ->where('strength', $drilldownProduct->strength)
                        ->where('form', $drilldownProduct->form);
                });
            })
            ->groupBy('barangays.barangay_name', 'patientrecords.category')
            ->select('barangays.barangay_name as barangay', 'patientrecords.category', DB::raw('COUNT(DISTINCT patientrecords.id) as total'))
            ->orderBy('barangays.barangay_name')
            ->get();

        $barangays = $barangayCategoryData->pluck('barangay')->unique()->values()->toArray();
        $categories = ['Adult', 'Child', 'Senior'];
        $barangayStackedData = [];
        foreach ($categories as $category) {
            $data = [];
            foreach ($barangays as $barangay) {
                $count = $barangayCategoryData
                    ->where('barangay', $barangay)
                    ->where('category', $category)
                    ->first()->total ?? 0;
                $data[] = $count;
            }
            $barangayStackedData[$category] = $data;
        }

        // Hotspots Data
        $patientHotspots = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
            ->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
            ->when($filter_barangay, function ($q) use ($filter_barangay) {
                return $q->where('barangays.barangay_name', $filter_barangay);
            })
            ->when($filter_branch, function ($q) use ($filter_branch) { // <--- BRANCH FILTER
                return $q->where('patientrecords.branch_id', $filter_branch);
            })
            ->when($drilldownProduct, function ($query) use ($drilldownProduct) {
                return $query->whereHas('dispensedMedications', function ($q) use ($drilldownProduct) {
                    $q->where('generic_name', $drilldownProduct->generic_name)
                        ->where('brand_name', $drilldownProduct->brand_name)
                        ->where('strength', $drilldownProduct->strength)
                        ->where('form', $drilldownProduct->form);
                });
            })
            ->join('dispensedmedications', 'patientrecords.id', '=', 'dispensedmedications.patientrecord_id')
            ->groupBy('barangays.barangay_name', 'patientrecords.category')
            ->select(
                'barangays.barangay_name as barangay',
                'patientrecords.category',
                DB::raw('COUNT(DISTINCT patientrecords.id) as total_patients'),
                DB::raw('SUM(dispensedmedications.quantity) as total_items')
            )
            ->orderBy('total_items', 'desc')
            ->take(10)
            ->get();

        // KPI Cards (Apply Branch Filter to Inventory queries)
        $invQuery = Inventory::where('is_archived', 0);
        if($filter_branch) {
            $invQuery->where('branch_id', $filter_branch);
        }

        $totalStockItems = (clone $invQuery)->sum('quantity');
        $lowStockProducts = (clone $invQuery)->where('quantity', '>', 0)->where('quantity', '<=', 100)->distinct('product_id')->count();

        $patientsTodayQuery = Patientrecords::whereDate('date_dispensed', Carbon::today());
        if($filter_branch) {
            $patientsTodayQuery->where('branch_id', $filter_branch);
        }
        $patientsToday = $patientsTodayQuery->count();

        $expiringIn30Days = (clone $invQuery)
            ->where('expiry_date', '>', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->count();

        $kpiCards = [
            'totalStockItems' => $totalStockItems,
            'lowStockProducts' => $lowStockProducts,
            'patientsToday' => $patientsToday,
            'expiringIn30Days' => $expiringIn30Days,
        ];

        $urgent_low_stock = (clone $invQuery)->with('product')
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 100)
            ->orderBy('quantity', 'asc')
            ->take(5)
            ->get();

        $urgent_expiring_soon = (clone $invQuery)->with('product')
            ->where('expiry_date', '>', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->orderBy('expiry_date', 'asc')
            ->take(5)
            ->get();

        // Forecast
        $forecast = $this->calculateStockForecast($forecast_days, $filter_branch);

        // Top Products Chart
        $topProductsQuery = ProductMovement::where('product_movements.type', 'OUT')
            ->whereBetween('product_movements.created_at', [$dateRange->start, $dateRange->end])
            ->when($filter_product_id, function ($query) use ($filter_product_id) {
                return $query->where('product_movements.product_id', $filter_product_id);
            })
            ->when($filter_branch, function($q) use ($filter_branch) { // <--- BRANCH FILTER
                     $validRecordIds = Patientrecords::where('branch_id', $filter_branch)->pluck('id');
                     $q->where(function($sub) use ($validRecordIds) {
                        if($validRecordIds->isEmpty()) {
                            $sub->whereRaw('1=0');
                        } else {
                            foreach($validRecordIds as $id) {
                                $sub->orWhere('product_movements.description', 'LIKE', "%Record: #{$id})%");
                            }
                        }
                     });
            })
            ->when($filter_barangay, function ($query) use ($filter_barangay, $dateRange) {
                $patientRecordIds = Patientrecords::join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                                                  ->where('barangays.barangay_name', $filter_barangay)
                                                  ->whereBetween('patientrecords.date_dispensed', [$dateRange->start, $dateRange->end])
                                                  ->pluck('patientrecords.id');
                return $query->where(function($q) use ($patientRecordIds) {
                    if ($patientRecordIds->isEmpty()) {
                        $q->whereRaw('1 = 0');
                    } else {
                        foreach ($patientRecordIds as $id) {
                            $q->orWhere('product_movements.description', 'LIKE', "%Record: #{$id})%");
                        }
                    }
                });
            })
            ->join('products', 'product_movements.product_id', '=', 'products.id')
            ->groupBy('product_movements.product_id', 'products.generic_name')
            ->select('product_movements.product_id', 'products.generic_name', DB::raw('SUM(product_movements.quantity) as total_dispensed'))
            ->orderBy('total_dispensed', 'desc')
            ->take(10);

        $topProductsData = $topProductsQuery->get();
        $topProducts = $topProductsData->pluck('total_dispensed', 'generic_name');

        // Data for Filters
        $filter_products = Product::where('is_archived', 0)->orderBy('generic_name')->get(['id', 'generic_name', 'brand_name']);

        // Load all branches for the dropdown
        $filter_branches = Branch::query()
            ->whereIn('id', $this->branchAccessService->accessibleBranchIds($user))
            ->active()
            ->orderBy('name')
            ->get();

        $filter_barangays = Patientrecords::join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
            ->when($filter_branch, fn($q) => $q->where('patientrecords.branch_id', $filter_branch)) // <--- Limit barangays to selected branch
            ->select('barangays.barangay_name as barangay')
            ->distinct()
            ->orderBy('barangays.barangay_name')
            ->pluck('barangay');

        // Seasonal Data
        $selectedSeasonalProduct = null;
        $compareSeasonalProduct = null;
        $seasonalLabels = [];
        $seasonalData = [];
        $compareData = [];
        if ($seasonal_product_id) {
            $selectedSeasonalProduct = $this->dashboardRepository->findProduct((int) $seasonal_product_id);
            if ($selectedSeasonalProduct) {
                [$seasonalLabels, $seasonalData] = $this->getProductTrend($seasonal_product_id);
            }
        }
        if ($compare_product_id) {
            $compareSeasonalProduct = $this->dashboardRepository->findProduct((int) $compare_product_id);
            if ($compareSeasonalProduct) {
                [$seasonalLabels, $compareData] = $this->getProductTrend($compare_product_id, $seasonalLabels);
            }
        }

        $observability = $this->buildObservabilityPayload($dateRange, $filter_branch, $grouping);

        // === RENDER FULL VIEW ===
        return view('admin.dashboard', compact(
            'kpiCards', 'urgent_low_stock', 'urgent_expiring_soon', 'forecast',
            'consumptionLabels', 'consumptionData',
            'topProducts', 'topProductsData',
            'barangays', 'barangayStackedData',
            'filter_products', 'filter_barangays', 'filter_branches', // <--- PASS BRANCHES
            'drilldown_product_name', 'inputs',
            'seasonalLabels', 'seasonalData', 'selectedSeasonalProduct',
            'compareData', 'compareSeasonalProduct',
            'patientHotspots',
            'patientVisitLabels',
            'patientVisitData',
            'observability'
        ) + [
            'filterTimespanLabel' => $this->getTimespanLabel($timespan, $dateRange),
            'filterBarangayLabel' => $filter_barangay ?? 'All Barangays',
            'filterProductLabel' => $drilldownProduct->generic_name ?? 'All Products',
            'filterBranchLabel' => $filter_branch ? ($this->dashboardRepository->findBranchName((int) $filter_branch) ?? 'Unknown') : 'All Branches',
        ]);
    }

    // --- Helper functions ---

    private function buildObservabilityPayload(object $dateRange, ?int $branchId, string $grouping): array
    {
        $normalizedGrouping = in_array($grouping, ['day', 'week', 'month'], true) ? $grouping : 'day';

        $overview = $this->systemAnalyticsService->getSystemOverview($branchId);
        $movementTrends = $this->systemAnalyticsService->getInventoryMovementTrends(
            $dateRange->start,
            $dateRange->end,
            $branchId,
            $normalizedGrouping
        );
        $requestTrends = $this->systemAnalyticsService->getRequestVolumeTrends(
            $dateRange->start,
            $dateRange->end,
            $branchId,
            $normalizedGrouping
        );
        $activityTrends = $this->systemAnalyticsService->getUserActivityTrends(
            $dateRange->start,
            $dateRange->end,
            $normalizedGrouping
        );
        $slaMetrics = $this->analyticsService->getRequestSLAMetrics($dateRange->start, $dateRange->end);

        $throughput = $this->buildThroughputSeries(
            $movementTrends['data'] ?? [],
            $requestTrends['data'] ?? [],
            $activityTrends['data'] ?? []
        );
        $latency = $this->buildRequestLatencySeries($dateRange, $branchId, $normalizedGrouping);
        $errors = $this->buildErrorTrackingSeries($dateRange, $branchId, $normalizedGrouping);
        $bottlenecks = $this->buildBottleneckSummary($branchId);

        $operationsTotal = array_sum($throughput['combined']);
        $errorEvents = array_sum($errors['combined']);
        $windowHours = max(1, (int) $dateRange->start->diffInHours($dateRange->end));
        $operationsPerHour = round($operationsTotal / $windowHours, 2);
        $errorRate = round(($errorEvents / max(1, $operationsTotal)) * 100, 2);

        return [
            'generated_at' => now()->toDateTimeString(),
            'summary' => [
                'operations_total' => (int) $operationsTotal,
                'operations_per_hour' => $operationsPerHour,
                'error_events' => (int) $errorEvents,
                'error_rate' => $errorRate,
                'avg_cycle_time_hours' => (float) ($slaMetrics['avg_cycle_time_hours'] ?? 0),
                'avg_approval_time_hours' => (float) ($slaMetrics['avg_approval_time_hours'] ?? 0),
                'avg_fulfillment_time_hours' => (float) ($slaMetrics['avg_fulfillment_time_hours'] ?? 0),
                'stale_open_requests' => (int) ($bottlenecks['stale_open_requests'] ?? 0),
                'oldest_open_request_hours' => (int) ($bottlenecks['oldest_open_request_hours'] ?? 0),
                'pending_requests' => (int) ($overview['pending_requests'] ?? 0),
                'today_movements' => (int) ($overview['today_movements'] ?? 0),
            ],
            'throughput' => $throughput,
            'latency' => $latency,
            'errors' => $errors,
            'bottlenecks' => $bottlenecks,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $movementRows
     * @param array<int, array<string, mixed>> $requestRows
     * @param array<int, array<string, mixed>> $activityRows
     * @return array<string, array<int, int|string>>
     */
    private function buildThroughputSeries(array $movementRows, array $requestRows, array $activityRows): array
    {
        $movementMap = $this->toPeriodMap($movementRows, 'period', 'movement_count');
        $requestMap = $this->toPeriodMap($requestRows, 'period', 'total_requests');
        $activityMap = $this->toPeriodMap($activityRows, 'period', 'total_events');

        $labels = $this->mergePeriodLabels($movementMap, $requestMap, $activityMap);
        $movements = $this->buildSeriesFromMap($labels, $movementMap);
        $requests = $this->buildSeriesFromMap($labels, $requestMap);
        $auditEvents = $this->buildSeriesFromMap($labels, $activityMap);
        $combined = [];

        foreach ($labels as $index => $label) {
            $combined[] = (int) $movements[$index] + (int) $requests[$index] + (int) $auditEvents[$index];
        }

        return [
            'labels' => $labels,
            'movements' => $movements,
            'requests' => $requests,
            'audit_events' => $auditEvents,
            'combined' => $combined,
        ];
    }

    /**
     * @return array<string, array<int, int|string|float>>
     */
    private function buildRequestLatencySeries(object $dateRange, ?int $branchId, string $grouping): array
    {
        $requests = IncomingRequest::query()
            ->with('statusHistory')
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        $bucket = [];

        foreach ($requests as $request) {
            $history = $request->statusHistory->sortBy('created_at');
            $requestedAt = optional($history->firstWhere('new_status', 'requested'))->created_at ?: $request->created_at;
            $approvedAt = optional($history->firstWhere('new_status', 'approved'))->created_at;
            $fulfilledAt = optional($history->firstWhere('new_status', 'fulfilled'))->created_at;

            $period = $this->formatPeriodLabel($requestedAt instanceof Carbon ? $requestedAt : Carbon::parse((string) $requestedAt), $grouping);

            if (!isset($bucket[$period])) {
                $bucket[$period] = [
                    'approval_sum' => 0.0,
                    'approval_count' => 0,
                    'fulfillment_sum' => 0.0,
                    'fulfillment_count' => 0,
                    'cycle_sum' => 0.0,
                    'cycle_count' => 0,
                ];
            }

            if ($approvedAt && $requestedAt) {
                $bucket[$period]['approval_sum'] += max(0, $requestedAt->floatDiffInHours($approvedAt, false));
                $bucket[$period]['approval_count']++;
            }

            if ($fulfilledAt && $approvedAt) {
                $bucket[$period]['fulfillment_sum'] += max(0, $approvedAt->floatDiffInHours($fulfilledAt, false));
                $bucket[$period]['fulfillment_count']++;
            }

            if ($fulfilledAt && $requestedAt) {
                $bucket[$period]['cycle_sum'] += max(0, $requestedAt->floatDiffInHours($fulfilledAt, false));
                $bucket[$period]['cycle_count']++;
            }
        }

        $labels = array_keys($bucket);
        sort($labels);

        $approvalHours = [];
        $fulfillmentHours = [];
        $cycleHours = [];

        foreach ($labels as $label) {
            $row = $bucket[$label];
            $approvalHours[] = round($row['approval_sum'] / max(1, $row['approval_count']), 2);
            $fulfillmentHours[] = round($row['fulfillment_sum'] / max(1, $row['fulfillment_count']), 2);
            $cycleHours[] = round($row['cycle_sum'] / max(1, $row['cycle_count']), 2);
        }

        return [
            'labels' => $labels,
            'approval_hours' => $approvalHours,
            'fulfillment_hours' => $fulfillmentHours,
            'cycle_hours' => $cycleHours,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildErrorTrackingSeries(object $dateRange, ?int $branchId, string $grouping): array
    {
        $auditPeriodExpr = $this->getDateGroupExpression('audit_events.created_at', $grouping);
        $requestPeriodExpr = $this->getDateGroupExpression('incoming_requests.created_at', $grouping);
        $historyPeriodExpr = $this->getDateGroupExpression('history_logs.created_at', $grouping);

        $auditErrors = AuditEvent::query()
            ->whereBetween('audit_events.created_at', [$dateRange->start, $dateRange->end])
            ->where(function ($query): void {
                $query->where('action', 'like', '%fail%')
                    ->orWhere('action', 'like', '%error%')
                    ->orWhere('action', 'like', '%denied%')
                    ->orWhere('action', 'like', '%rollback%');
            })
            ->select(DB::raw("{$auditPeriodExpr} as period"), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();

        $requestDenied = IncomingRequest::query()
            ->whereBetween('incoming_requests.created_at', [$dateRange->start, $dateRange->end])
            ->where('status', 'denied')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->select(DB::raw("{$requestPeriodExpr} as period"), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();

        $historyErrors = HistoryLog::query()
            ->whereBetween('history_logs.created_at', [$dateRange->start, $dateRange->end])
            ->where(function ($query): void {
                $query->where('action', 'like', '%FAIL%')
                    ->orWhere('action', 'like', '%ERROR%')
                    ->orWhere('action', 'like', '%DENIED%')
                    ->orWhere('action', 'like', '%ROLLBACK%')
                    ->orWhere('description', 'like', '%fail%')
                    ->orWhere('description', 'like', '%error%')
                    ->orWhere('description', 'like', '%denied%')
                    ->orWhere('description', 'like', '%rollback%');
            })
            ->select(DB::raw("{$historyPeriodExpr} as period"), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();

        $auditMap = $this->toPeriodMap($auditErrors, 'period', 'count');
        $requestMap = $this->toPeriodMap($requestDenied, 'period', 'count');
        $historyMap = $this->toPeriodMap($historyErrors, 'period', 'count');

        $labels = $this->mergePeriodLabels($auditMap, $requestMap, $historyMap);
        $auditSeries = $this->buildSeriesFromMap($labels, $auditMap);
        $requestSeries = $this->buildSeriesFromMap($labels, $requestMap);
        $historySeries = $this->buildSeriesFromMap($labels, $historyMap);
        $combined = [];

        foreach ($labels as $index => $label) {
            $combined[] = (int) $auditSeries[$index] + (int) $requestSeries[$index] + (int) $historySeries[$index];
        }

        $topAuditActions = AuditEvent::query()
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end])
            ->where(function ($query): void {
                $query->where('action', 'like', '%fail%')
                    ->orWhere('action', 'like', '%error%')
                    ->orWhere('action', 'like', '%denied%')
                    ->orWhere('action', 'like', '%rollback%');
            })
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->map(fn ($item) => ['label' => 'audit: '.$item->action, 'count' => (int) $item->count])
            ->all();

        $topHistoryActions = HistoryLog::query()
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end])
            ->where(function ($query): void {
                $query->where('action', 'like', '%FAIL%')
                    ->orWhere('action', 'like', '%ERROR%')
                    ->orWhere('action', 'like', '%DENIED%')
                    ->orWhere('action', 'like', '%ROLLBACK%');
            })
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->map(fn ($item) => ['label' => 'history: '.$item->action, 'count' => (int) $item->count])
            ->all();

        $requestDeniedCount = IncomingRequest::query()
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end])
            ->where('status', 'denied')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->count();

        $topCategories = array_merge($topAuditActions, $topHistoryActions);
        if ($requestDeniedCount > 0) {
            $topCategories[] = ['label' => 'requests: denied', 'count' => (int) $requestDeniedCount];
        }

        usort($topCategories, fn ($a, $b) => ((int) $b['count']) <=> ((int) $a['count']));
        $topCategories = array_slice($topCategories, 0, 8);

        return [
            'labels' => $labels,
            'audit_failed' => $auditSeries,
            'request_denied' => $requestSeries,
            'history_failed' => $historySeries,
            'combined' => $combined,
            'top_categories' => $topCategories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBottleneckSummary(?int $branchId): array
    {
        $openStatuses = ['draft', 'requested', 'review', 'approved', 'fulfilling'];

        $openBaseQuery = IncomingRequest::query()
            ->whereIn('status', $openStatuses)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        $statusCounts = (clone $openBaseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        $statusLabels = $statusCounts->pluck('status')->values()->all();
        $statusSeries = $statusCounts->pluck('count')->map(fn ($value) => (int) $value)->values()->all();

        $staleOpenRequests = (clone $openBaseQuery)
            ->where('created_at', '<=', now()->subHours(48))
            ->count();

        $oldestOpenRequest = (clone $openBaseQuery)
            ->orderBy('created_at', 'asc')
            ->first();

        $oldestOpenRequestHours = $oldestOpenRequest
            ? (int) round($oldestOpenRequest->created_at->floatDiffInHours(now(), false))
            : 0;

        $topAgingRequests = (clone $openBaseQuery)
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get(['id', 'status', 'priority', 'department', 'created_at'])
            ->map(function ($request): array {
                return [
                    'id' => $request->id,
                    'status' => $request->status,
                    'priority' => $request->priority,
                    'department' => $request->department,
                    'age_hours' => (int) round($request->created_at->floatDiffInHours(now(), false)),
                ];
            })
            ->values()
            ->all();

        return [
            'status_labels' => $statusLabels,
            'status_counts' => $statusSeries,
            'stale_open_requests' => (int) $staleOpenRequests,
            'oldest_open_request_hours' => max(0, $oldestOpenRequestHours),
            'top_aging_requests' => $topAgingRequests,
        ];
    }

    private function getDateGroupExpression(string $column, string $groupBy): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return match ($groupBy) {
                'week' => "strftime('%Y-W%W', {$column})",
                'month' => "strftime('%Y-%m', {$column})",
                default => "date({$column})",
            };
        }

        return match ($groupBy) {
            'week' => "DATE_FORMAT({$column}, '%x-W%v')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE({$column})",
        };
    }

    private function formatPeriodLabel(Carbon $date, string $grouping): string
    {
        return match ($grouping) {
            'week' => $date->format('o').'-W'.str_pad((string) $date->weekOfYear, 2, '0', STR_PAD_LEFT),
            'month' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function toPeriodMap(array $rows, string $periodKey, string $valueKey): array
    {
        $map = [];
        foreach ($rows as $row) {
            $period = (string) data_get($row, $periodKey, '');
            if ($period === '') {
                continue;
            }
            $map[$period] = (int) data_get($row, $valueKey, 0);
        }
        ksort($map);

        return $map;
    }

    /**
     * @param array<string, int> ...$maps
     * @return array<int, string>
     */
    private function mergePeriodLabels(array ...$maps): array
    {
        $labels = [];
        foreach ($maps as $map) {
            foreach (array_keys($map) as $label) {
                $labels[$label] = true;
            }
        }

        $periodLabels = array_keys($labels);
        sort($periodLabels);

        return $periodLabels;
    }

    /**
     * @param array<int, string> $labels
     * @param array<string, int> $map
     * @return array<int, int>
     */
    private function buildSeriesFromMap(array $labels, array $map): array
    {
        $series = [];
        foreach ($labels as $label) {
            $series[] = (int) ($map[$label] ?? 0);
        }

        return $series;
    }

    private function getTimespanLabel($timespan, $dateRange) {
         switch($timespan) {
             case '7d': return 'Last 7 Days';
             case '30d': return 'Last 30 Days';
             case '90d': return 'Last 90 Days';
             case '1y': return 'Last 1 Year';
             case 'all': return 'All Time';
             case 'custom': return $dateRange->start->format('M d, Y') . ' - ' . $dateRange->end->format('M d, Y');
             default: return 'Last 30 Days';
         }
     }

    private function calculateDateRange($timespan, $start, $end)
    {
        $dateRange = new \stdClass();
        $dateRange->end = Carbon::now()->endOfDay();

        if ($timespan == 'custom' && $start && $end) {
            $dateRange->start = Carbon::parse($start)->startOfDay();
            $dateRange->end = Carbon::parse($end)->endOfDay();
        } elseif ($timespan == '7d') {
            $dateRange->start = Carbon::now()->subDays(6)->startOfDay();
        } elseif ($timespan == '90d') {
            $dateRange->start = Carbon::now()->subDays(89)->startOfDay();
        } elseif ($timespan == '1y') {
            $dateRange->start = Carbon::now()->subYear()->addDay()->startOfDay();
        } elseif ($timespan == 'all') {
            $minDate = ProductMovement::min('created_at');
            if ($minDate) {
                $dateRange->start = Carbon::parse($minDate)->startOfDay();
            } else {
                $dateRange->start = Carbon::now()->startOfDay();
            }
        } else { // Default to 30d
            $dateRange->start = Carbon::now()->subDays(29)->startOfDay();
        }

        if ($dateRange->start->gt($dateRange->end)) {
            $dateRange->start = $dateRange->end->copy()->startOfDay();
        }

        return $dateRange;
    }

    // Added $branch_id parameter
    private function getConsumptionTrend($dateRange, $product_id, $barangay, $grouping, $branch_id = null)
    {
        $query = ProductMovement::where('product_movements.type', 'OUT')
            ->whereBetween('product_movements.created_at', [$dateRange->start, $dateRange->end])
            ->when($product_id, function ($query) use ($product_id) {
                return $query->where('product_movements.product_id', $product_id);
            });

        // Filter by Branch via PatientRecord linkage in description
        if ($branch_id) {
            $validRecordIds = Patientrecords::where('branch_id', $branch_id)->pluck('id');
            $query->where(function($sub) use ($validRecordIds) {
               if($validRecordIds->isEmpty()) {
                   $sub->whereRaw('1=0');
               } else {
                   foreach($validRecordIds as $id) {
                       $sub->orWhere('description', 'LIKE', "%Record: #{$id})%");
                   }
               }
            });
        }

        if ($barangay) {
            $patientRecordIds = Patientrecords::join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                ->where('barangays.barangay_name', $barangay)
                ->whereBetween('patientrecords.date_dispensed', [$dateRange->start, $dateRange->end])
                ->pluck('patientrecords.id');

            $query->where(function($q) use ($patientRecordIds) {
                if ($patientRecordIds->isEmpty()) {
                    $q->whereRaw('1 = 0');
                } else {
                    foreach ($patientRecordIds as $id) {
                        $q->orWhere('description', 'LIKE', "%Record: #{$id})%");
                    }
                }
            });
        }

        $periodStartDate = $dateRange->start->copy();
        if ($grouping == 'week') $periodStartDate->startOfWeek(Carbon::MONDAY);
        if ($grouping == 'month') $periodStartDate->startOfMonth();
        if ($periodStartDate->gt($dateRange->end)) {
            $periodStartDate = $dateRange->end->copy();
            if ($grouping == 'week') $periodStartDate->startOfWeek(Carbon::MONDAY);
            if ($grouping == 'month') $periodStartDate->startOfMonth();
        }

        $period = null;
        if ($grouping == 'week') {
            $period = CarbonPeriod::create($periodStartDate, '1 week', $dateRange->end->copy()->endOfWeek(Carbon::SUNDAY));
        } elseif ($grouping == 'month') {
            $period = CarbonPeriod::create($periodStartDate, '1 month', $dateRange->end->copy()->startOfMonth());
        } else {
            $period = CarbonPeriod::create($periodStartDate, '1 day', $dateRange->end);
        }

        $dbFormat = 'Y-m-d';
        $labelFormat = 'M d';
        $orderByColumn = 'date_group';
        $groupByColumn = 'date_group';
        switch ($grouping) {
            case 'week':
                $dbFormat = 'o-W';
                $labelFormat = '\WW Y (M d)';
                $selectRaw = "DATE_FORMAT(product_movements.created_at, '%x-%v') as date_group";
                break;
            case 'month':
                $dbFormat = 'Y-m';
                $labelFormat = 'M Y';
                $selectRaw = "DATE_FORMAT(product_movements.created_at, '%Y-%m') as date_group";
                break;
            default:
                $selectRaw = "DATE(product_movements.created_at) as date_group";
                break;
        }

        $dispensationTrend = $query
            ->select(DB::raw($selectRaw), DB::raw('SUM(product_movements.quantity) as total_quantity'))
            ->groupBy($groupByColumn)
            ->orderBy($orderByColumn, 'asc')
            ->get()
            ->pluck('total_quantity', $orderByColumn);

        $labels = [];
        $data = [];
        if ($period) {
            foreach ($period as $date) {
                $key = $date->format($dbFormat);
                $label = $date->format($labelFormat);
                $labels[] = $label;
                $data[] = $dispensationTrend[$key] ?? 0;
            }
        }
        return [$labels, $data];
    }

    // Added $branch_id parameter
    private function getPatientVisitTrend($dateRange, $barangay, $drilldownProduct, $grouping, $branch_id = null)
    {
        $periodStartDate = $dateRange->start->copy();
        if ($grouping == 'week') $periodStartDate->startOfWeek(Carbon::MONDAY);
        if ($grouping == 'month') $periodStartDate->startOfMonth();
        if ($periodStartDate->gt($dateRange->end)) {
            $periodStartDate = $dateRange->end->copy();
            if ($grouping == 'week') $periodStartDate->startOfWeek(Carbon::MONDAY);
            if ($grouping == 'month') $periodStartDate->startOfMonth();
        }

        $period = null;
        if ($grouping == 'week') {
            $period = CarbonPeriod::create($periodStartDate, '1 week', $dateRange->end->copy()->endOfWeek(Carbon::SUNDAY));
        } elseif ($grouping == 'month') {
            $period = CarbonPeriod::create($periodStartDate, '1 month', $dateRange->end->copy()->startOfMonth());
        } else {
            $period = CarbonPeriod::create($periodStartDate, '1 day', $dateRange->end);
        }

        $dbFormat = 'Y-m-d';
        $labelFormat = 'M d';
        $orderByColumn = 'date_group';
        $groupByColumn = 'date_group';
        switch ($grouping) {
            case 'week':
                $dbFormat = 'o-W';
                $labelFormat = '\WW Y (M d)';
                $selectRaw = "DATE_FORMAT(date_dispensed, '%x-%v') as date_group";
                break;
            case 'month':
                $dbFormat = 'Y-m';
                $labelFormat = 'M Y';
                $selectRaw = "DATE_FORMAT(date_dispensed, '%Y-%m') as date_group";
                break;
            default:
                $selectRaw = "DATE(date_dispensed) as date_group";
                break;
        }

        $patientVisitsQuery = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
            ->when($barangay, function ($q) use ($barangay) {
                $q->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                    ->where('barangays.barangay_name', $barangay);
            })
            ->when($branch_id, function ($q) use ($branch_id) { // <--- BRANCH FILTER
                $q->where('branch_id', $branch_id);
            })
            ->when($drilldownProduct, function ($query) use ($drilldownProduct) {
                return $query->whereHas('dispensedMedications', function ($q) use ($drilldownProduct) {
                    $q->where('generic_name', $drilldownProduct->generic_name)
                        ->where('brand_name', $drilldownProduct->brand_name)
                        ->where('strength', $drilldownProduct->strength)
                        ->where('form', $drilldownProduct->form);
                });
            })
            ->select(DB::raw($selectRaw), DB::raw('COUNT(DISTINCT patientrecords.id) as total_patients'))
            ->groupBy($groupByColumn)
            ->orderBy($orderByColumn, 'asc');

        $patientVisits = $patientVisitsQuery->get()
            ->pluck('total_patients', $orderByColumn);

        $labels = [];
        $data = [];
        if ($period) {
            foreach ($period as $date) {
                $key = $date->format($dbFormat);
                $label = $date->format($labelFormat);
                $labels[] = $label;
                $data[] = $patientVisits[$key] ?? 0;
            }
        }
        return [$labels, $data];
    }

    private function getProductTrend($product_id, $alignLabels = null)
    {
        $threeYearsAgo = Carbon::now()->subYears(3)->startOfMonth();
        $firstMovementDate = ProductMovement::where('product_id', $product_id)
                                            ->where('type', 'OUT')
                                            ->min('created_at');

        $startDate = $threeYearsAgo;
        if ($firstMovementDate) {
            $firstMovementMonthStart = Carbon::parse($firstMovementDate)->startOfMonth();
            if ($firstMovementMonthStart->gt($startDate)) {
                $startDate = $firstMovementMonthStart;
            }
        }
        if ($startDate->gt(Carbon::now())) {
            $startDate = Carbon::now()->startOfMonth();
        }

         $query = ProductMovement::where('type', 'OUT')
             ->where('product_id', $product_id)
             ->where('created_at', '>=', $startDate)
             ->groupBy('date_group')
             ->orderBy('date_group', 'asc')
             ->select(
                 DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date_group"),
                 DB::raw('SUM(quantity) as total_quantity')
             )
             ->get()
             ->pluck('total_quantity', 'date_group');

        if ($query->isEmpty() && !$alignLabels) {
            return [[], []];
        }

        $labels = [];
        $data = [];
        $endDate = Carbon::now()->startOfMonth();

        if ($alignLabels) {
            $period = collect($alignLabels)->map(function($l) {
                try {
                    return Carbon::parse($l)->startOfMonth();
                } catch (\Exception $e) {
                    return null;
                }
            })->filter()->unique();

            if ($period->isEmpty()) {
                if ($query->isEmpty()) return [[],[]];
                $periodStartDate = Carbon::parse($query->keys()->first() . '-01');
                if ($periodStartDate->gt($endDate)) $periodStartDate = $endDate->copy();
                $period = CarbonPeriod::create($periodStartDate, '1 month', $endDate);
                $alignLabels = null;
            } else {
                $period = CarbonPeriod::create($period->min(), '1 month', $period->max());
            }

        } else {
            if ($query->isEmpty()) return [[],[]];
            $periodStartDate = Carbon::parse($query->keys()->first() . '-01');
            if ($periodStartDate->lt($threeYearsAgo)) {
                $periodStartDate = $threeYearsAgo;
            }
            if ($periodStartDate->gt($endDate)) {
                $periodStartDate = $endDate->copy();
            }
            $period = CarbonPeriod::create($periodStartDate, '1 month', $endDate);
        }

        if ($period) {
            foreach ($period as $date) {
                $key = $date->format('Y-m');
                if (!$alignLabels) {
                    $labels[] = $date->format('M Y');
                }
                $data[] = $query[$key] ?? 0;
            }
        }

        if ($alignLabels && $period) {
            $labels = [];
            foreach($period as $date) {
                $labels[] = $date->format('M Y');
            }
        } elseif ($alignLabels) {
            $labels = $alignLabels;
        }

        return [$labels, $data];
    }

    // Added $branch_id parameter
    private function calculateStockForecast($daysOfHistory = 90, $branch_id = null)
    {
        if ($daysOfHistory <= 0) $daysOfHistory = 90;

        // 1. Consumption (Filtered by Branch)
        $consumptionQuery = ProductMovement::where('type', 'OUT')
            ->where('created_at', '>=', Carbon::now()->subDays($daysOfHistory));

        if ($branch_id) {
            $validRecordIds = Patientrecords::where('branch_id', $branch_id)->pluck('id');
            $consumptionQuery->where(function($sub) use ($validRecordIds) {
               if($validRecordIds->isEmpty()) {
                   $sub->whereRaw('1=0');
               } else {
                   foreach($validRecordIds as $id) {
                       $sub->orWhere('description', 'LIKE', "%Record: #{$id})%");
                   }
               }
            });
        }

        $consumption = $consumptionQuery->groupBy('product_id')
            ->select('product_id', DB::raw("SUM(quantity) as total_consumed"))
            ->pluck('total_consumed', 'product_id');

        // 2. Current Stock (Filtered by Branch)
        $currentStockQuery = Inventory::where('is_archived', 0);
        if ($branch_id) {
            $currentStockQuery->where('branch_id', $branch_id);
        }

        $currentStock = $currentStockQuery
            ->groupBy('product_id')
            ->select('product_id', DB::raw("SUM(quantity) as current_quantity"))
            ->pluck('current_quantity', 'product_id');

        $products = Product::whereIn('id', $currentStock->keys())->get()->keyBy('id');

        $forecast = [];

        foreach ($currentStock as $product_id => $stock) {

            if (!isset($products[$product_id])) continue;

            $totalConsumed = $consumption[$product_id] ?? 0;
            $avgDailyUsage = ($daysOfHistory > 0) ? $totalConsumed / $daysOfHistory : 0;

            if ($avgDailyUsage > 0) {
                $daysRemaining = floor($stock / max(0.01, $avgDailyUsage));
            } else {
                $daysRemaining = INF;
            }

            $forecast[] = [
                'product_name' => $products[$product_id]->generic_name,
                'brand_name' => $products[$product_id]->brand_name,
                'current_stock' => $stock,
                'avg_daily_usage' => round($avgDailyUsage, 2),
                'days_remaining' => $daysRemaining,
            ];
        }

        usort($forecast, function ($a, $b) {
            $aDays = ($a['days_remaining'] === INF) ? PHP_INT_MAX : $a['days_remaining'];
            $bDays = ($b['days_remaining'] === INF) ? PHP_INT_MAX : $b['days_remaining'];
            return $aDays <=> $bDays;
        });

        return $forecast;
    }

    private function getSeasonalDataForAjax($seasonal_product_id, $compare_product_id)
    {
        $selectedSeasonalProduct = null;
        $compareSeasonalProduct = null;
        $seasonalLabels = [];
        $seasonalData = [];
        $compareData = [];

        if ($seasonal_product_id) {
            $selectedSeasonalProduct = $this->dashboardRepository->findProduct((int) $seasonal_product_id);
            if ($selectedSeasonalProduct) {
                [$seasonalLabels, $seasonalData] = $this->getProductTrend($seasonal_product_id);
            }
        }
        if ($compare_product_id) {
            $compareSeasonalProduct = $this->dashboardRepository->findProduct((int) $compare_product_id);
            if ($compareSeasonalProduct) {
                [$seasonalLabels, $compareData] = $this->getProductTrend($compare_product_id, $seasonalLabels);
            }
        }

        return [
            'labels'       => $seasonalLabels,
            'data'         => $seasonalData,
            'productName'  => $selectedSeasonalProduct->generic_name ?? null,
            'compareData'  => $compareData,
            'compareName'  => $compareSeasonalProduct->generic_name ?? null,
        ];
    }

//     public function getAiAnalysis(Request $request): JsonResponse
//     {
//         $validated = $request->validate([
//             'product_name' => 'required|string',
//             'seasonal_data' => 'required|array',
//             'seasonal_data.*.label' => 'required|string',
//             'seasonal_data.*.data' => 'required|numeric',
//             'compare_product_name' => 'nullable|string',
//             'compare_data' => 'nullable|array',
//             'compare_data.*.label' => 'required_with:compare_product_name|string',
//             'compare_data.*.data' => 'required_with:compare_product_name|numeric',
//         ]);

//         $apiKey = env("GEMINI_API_KEY"); // Best practice: use env
//         if (!$apiKey) {
//             // Fallback hardcoded key if env not set (matches your previous code)
//             $apiKey = "AIzaSyCr5K_DdA0RRvRLo7_sDfG-gB1ToVd51L8";
//         }

//         if (!$apiKey) {
//             Log::error('GEMINI_API_KEY is not set.');
//             return response()->json(['error' => 'AI analysis is not configured on the server.'], 500);
//         }

//         $productName = $validated['product_name'];

//         $dataString = collect($validated['seasonal_data'])->map(function ($item) {
//             return "- {$item['label']}: {$item['data']}";
//         })->join("\n");

//         $systemInstruction = "You are a helpful and concise data analyst for a public health clinic in the Philippines. **Crucially, output MUST be generated as raw HTML (e.g., <h2>, <table>, <tr>, <td>) with CSS classes and inline styles ONLY for structure (e.g., border, padding).** Use **<strong>** tags for bolding product names (e.g., <strong>{$productName}</strong>). Respond in clear HTML paragraphs or tables, DO NOT use Markdown, DO NOT use lists/bullet points.";

//         $userQuery = "{$systemInstruction}\n\nAnalyze the following monthly dispensation data (items dispensed per month) for the product '{$productName}':\n\n{$dataString}\n\n";

//         $tableStyle = 'width: 100%; border-collapse: collapse; margin-top: 15px;';
//         $headerStyle = 'background-color: #f3f4f6; padding: 10px; border: 1px solid #e5e7eb; text-align: left; font-weight: bold;';
//         $cellStyle = 'padding: 10px; border: 1px solid #e5e7eb; vertical-align: top;';

//         if (!empty($validated['compare_product_name'])) {
//             $compareName = $validated['compare_product_name'];
//             $compareString = collect($validated['compare_data'])->map(function ($item) {
//                 return "- {$item['label']}: {$item['data']}";
//             })->join("\n");

//             $userQuery .= "For comparison, here is the data for '{$compareName}':\n\n{$compareString}\n\n";

//             $userQuery .= "Please follow this exact structure, using raw HTML:
// <h2>🤝 Product Comparison</h2>
// Generate a single HTML table (style='{$tableStyle}') with header cells (style='{$headerStyle}') and data cells (style='{$cellStyle}'). The table must have columns for 'Product', 'Overall Trend', 'Peak Months', and 'Trough/Zero Months'.

// <h2>💡 Insights & Drivers</h2>
// Provide a <div> block with HTML paragraphs summarizing the **primary differences** and **similarities** between the products' demand drivers, linking to environmental or public health factors.

// <h2>📈 Predictive Recommendations</h2>
// Provide a <div> block with HTML paragraphs containing a separate, clear, predictive recommendation for managing stock for *each* product: **<strong>{$productName}</strong>** and **<strong>{$compareName}</strong>**.";

//         } else {
//             $userQuery .= "Based ONLY on the data provided, structure your response using raw HTML with the following sections:
// <h2>📊 Key Observations & Trends</h2>
// <p>Summarize the overall demand pattern and list the notable <strong>peaks</strong> (highest demand months/data points) and <strong>troughs</strong> (lowest or zero demand months/data points).</p>
// <h2>💡 Contextual Insights</h2>
// <p>Suggest potential reasons *why* these trends might be happening in the Philippines context (e.g., linking to rainy season, flu season, general health campaigns). Analyze the impact of 'zero dispensation' events on inventory management vs. patient need.</p>
// <h2>📈 Predictive Recommendation</h2>
// <p>Provide a single, clear, predictive recommendation for managing stock for **<strong>{$productName}</strong>** (e.g., 'Proactively increase stock levels by 20% from December to February to prepare for the annual peak.').</p>";
//         }

//         $model = 'gemini-2.5-flash';
//         $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

//         $payload = [
//             'contents' => [
//                 [
//                     'role' => 'user',
//                     'parts' => [
//                         ['text' => $userQuery]
//                     ]
//                 ]
//             ],
//         ];

//         try {
//             $response = Http::timeout(60)
//                 ->post($apiUrl . '?key=' . $apiKey, $payload);

//             if (!$response->successful()) {
//                 Log::error('Gemini API request failed', ['status' => $response->status(), 'body' => $response->json()]);
//                 $errorBody = data_get($response->json(), 'error.message', 'The AI service failed to respond.');
//                 return response()->json(['error' => $errorBody], $response->status());
//             }

//             $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

//             if ($text) {
//                  $text = str_replace(['**', '*'], '', $text);
//                 return response()->json(['analysis' => trim($text)]);
//             } else {
//                 $finishReason = data_get($response->json(), 'candidates.0.finishReason');
//                 Log::error('Gemini API gave no content', ['reason' => $finishReason, 'response' => $response->json()]);
//                 if ($finishReason === 'SAFETY') {
//                     return response()->json(['error' => 'The AI analysis was blocked due to safety settings.'], 400);
//                 }
//                 return response()->json(['error' => 'No valid response received from the AI analysis service.'], 500);
//             }

//         } catch (\Illuminate\Http\Client\ConnectionException $e) {
//             Log::error('Connection Error calling Gemini API: ' . $e->getMessage());
//             return response()->json(['error' => 'Could not connect to the AI analysis service. Please check the network connection.'], 503);
//         } catch (\Exception $e) {
//             Log::error('Error calling Gemini API: ' . $e->getMessage());
//             return response()->json(['error' => 'An unexpected error occurred while contacting the AI analysis service.'], 500);
//         }
//     }

    public function getAiAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string',
            'seasonal_data' => 'required|array',
            'seasonal_data.*.label' => 'required|string',
            'seasonal_data.*.data' => 'required|numeric',
            'compare_product_name' => 'nullable|string',
            'compare_data' => 'nullable|array',
            'compare_data.*.label' => 'required_with:compare_product_name|string',
            'compare_data.*.data' => 'required_with:compare_product_name|numeric',
        ]);

        $productName = $validated['product_name'];

        $dataString = collect($validated['seasonal_data'])
            ->map(fn ($item) => "- {$item['label']}: {$item['data']}")
            ->join("\n");

        $tableStyle  = 'width: 100%; border-collapse: collapse; margin-top: 15px;';
        $headerStyle = 'background-color: #f3f4f6; padding: 10px; border: 1px solid #e5e7eb; text-align: left; font-weight: bold;';
        $cellStyle   = 'padding: 10px; border: 1px solid #e5e7eb; vertical-align: top;';

        // Strong HTML-only instruction (use as SYSTEM prompt)
        $systemInstruction =
            "You are a helpful and concise data analyst for a public health clinic in the Philippines. " .
            "OUTPUT MUST BE RAW HTML ONLY. " .
            "Do NOT output Markdown. Do NOT use bullet lists. Do NOT wrap in ``` fences. " .
            "Use only these tags: <h2>, <div>, <p>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <br>. " .
            "Use inline styles ONLY for table borders/padding/spacing. " .
            "Bold product names using <strong> (e.g., <strong>{$productName}</strong>).";

        // Build user prompt WITHOUT injecting system text (Ollama supports `system`)
        $userQuery =
            "Analyze the following monthly dispensation data (items dispensed per month) for the product '{$productName}':\n\n" .
            "{$dataString}\n\n";

        if (!empty($validated['compare_product_name'])) {
            $compareName = $validated['compare_product_name'];

            $compareString = collect($validated['compare_data'] ?? [])
                ->map(fn ($item) => "- {$item['label']}: {$item['data']}")
                ->join("\n");

            $userQuery .= "For comparison, here is the data for '{$compareName}':\n\n{$compareString}\n\n";

            $userQuery .=
                "Please follow this exact structure, using raw HTML:\n" .
                "<h2>🤝 Product Comparison</h2>\n" .
                "Generate a single HTML table (style='{$tableStyle}') with header cells (style='{$headerStyle}') and data cells (style='{$cellStyle}'). " .
                "The table must have columns for 'Product', 'Overall Trend', 'Peak Months', and 'Trough/Zero Months'.\n\n" .
                "<h2>💡 Insights & Drivers</h2>\n" .
                "Provide a <div> block with HTML paragraphs summarizing the primary differences and similarities.\n\n" .
                "<h2>📈 Predictive Recommendations</h2>\n" .
                "Provide a <div> block with separate predictive recommendations for <strong>{$productName}</strong> and <strong>{$compareName}</strong>.";
        } else {
            $userQuery .=
                "Based ONLY on the data provided, structure your response using raw HTML with the following sections:\n" .
                "<h2>📊 Key Observations & Trends</h2>\n" .
                "<p>Summarize the overall demand pattern and identify notable <strong>peaks</strong> and <strong>troughs</strong>.</p>\n" .
                "<h2>💡 Contextual Insights</h2>\n" .
                "<p>Suggest potential reasons in the Philippines context. Explain impact of zero dispensation on inventory vs patient need.</p>\n" .
                "<h2>📈 Predictive Recommendation</h2>\n" .
                "<p>Provide one clear predictive recommendation for managing stock for <strong>{$productName}</strong>.</p>";
        }

        $apiKey = config('services.gemini.api_key');
        $baseUrl = rtrim(config('services.gemini.base_url', '[https://generativelanguage.googleapis.com/v1beta](https://generativelanguage.googleapis.com/v1beta)'), '/');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (!$apiKey) {
            Log::error('GEMINI_API_KEY is not configured for seasonal trend analysis.');
            return response()->json(['error' => 'AI analysis is not configured on the server.'], 500);
        }

        $endpoint = "{$baseUrl}/models/{$model}:generateContent";

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userQuery],
                    ],
                ],
            ]
            // Removed generationConfig to uncap temperature and maxOutputTokens
        ];

        try {
            $response = Http::retry(2, 800)          // retry twice with 800ms delay
                ->connectTimeout(10)                 // fail fast if cannot connect
                ->timeout(180)                       // total request timeout
                ->acceptJson()
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                Log::error('Gemini API request failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                $raw = data_get($response->json(), 'error.message', $response->body());
                return response()->json([
                    'error' => 'AI service failed: ' . ($response->body() ?: 'Unknown error'),
                ], $response->status());
            }

            $parts = data_get($response->json(), 'candidates.0.content.parts', []);
            $text = collect($parts)
                ->pluck('text')
                ->filter()
                ->implode("\n");

            if (!$text) {
                Log::error('Gemini returned no response text', ['body' => $response->json()]);
                return response()->json(['error' => 'No valid response received from AI service.'], 500);
            }

            $text = preg_replace('/^```(?:html)?\s*/i', '', trim($text));
            $text = preg_replace('/\s*```$/', '', $text);
            $text = trim(str_replace(['**', '*'], '', $text));

            return response()->json(['analysis' => $text]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error calling Gemini API: ' . $e->getMessage());
            return response()->json(['error' => 'Could not connect to AI analysis service.'], 503);
        } catch (\Exception $e) {
            Log::error('Error calling Gemini API: ' . $e->getMessage());
            return response()->json(['error' => 'Unexpected error while contacting AI analysis service.'], 500);
        }
    }
}
