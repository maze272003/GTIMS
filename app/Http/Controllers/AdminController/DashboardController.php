<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Patientrecords;
use App\Models\ProductMovement;
use App\Models\Branch; // <--- Imported Branch Model
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard with analytics or return AJAX data.
     */
// Add | RedirectResponse to the end
public function showdashboard(Request $request): View | JsonResponse | RedirectResponse    {
        if (\Illuminate\Support\Facades\Auth::user()->user_level_id == 6) {
        return redirect()->route('admin.orders.index');
            
    }
        // === 0. GET FILTERS WITH DEFAULTS ===
        $inputs = $request->validate([
            'filter_timespan' => 'nullable|string|in:7d,30d,90d,1y,all,custom',
            'filter_start' => 'nullable|date|required_if:filter_timespan,custom',
            'filter_end' => 'nullable|date|required_if:filter_timespan,custom|after_or_equal:filter_start',
            'filter_barangay' => 'nullable|string|max:255',
            'filter_branch' => 'nullable|integer|exists:branches,id', // <--- ADDED BRANCH FILTER VALIDATION
            'filter_product_id' => 'nullable|integer|exists:products,id',
            'forecast_days' => 'nullable|integer|in:30,60,90,180',
            'grouping' => 'nullable|string|in:day,week,month',
            'drilldown_product_id' => 'nullable|integer|exists:products,id',
            'seasonal_product_id' => 'nullable|integer|exists:products,id',
            'compare_product_id' => 'nullable|integer|exists:products,id',
            'ajax_update' => 'nullable|string|in:forecast,seasonal,main_charts'
        ]);

        $timespan = $inputs['filter_timespan'] ?? '30d';
        $filter_barangay = $inputs['filter_barangay'] ?? null;
        $filter_branch = $inputs['filter_branch'] ?? null; // <--- ASSIGN VARIABLE
        $filter_product_id = $inputs['filter_product_id'] ?? null;
        $forecast_days = $inputs['forecast_days'] ?? 90;
        $grouping = $inputs['grouping'] ?? 'day';
        
        // Prioritize drilldown, but allow filter_product_id to be set
        $active_product_id = $inputs['drilldown_product_id'] ?? $filter_product_id;
        
        $drilldown_product_id = $inputs['drilldown_product_id'] ?? null;
        $drilldownProduct = $active_product_id ? Product::find($active_product_id) : null;
        $drilldown_product_name = $drilldownProduct->generic_name ?? null;

        $seasonal_product_id = $inputs['seasonal_product_id'] ?? Product::where('is_archived', 0)->value('id');
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
            $branchName = $filter_branch ? Branch::find($filter_branch)->name : 'All Branches';

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
                ]
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
        $filter_branches = Branch::all(); 

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
            $selectedSeasonalProduct = Product::find($seasonal_product_id);
            if ($selectedSeasonalProduct) {
                [$seasonalLabels, $seasonalData] = $this->getProductTrend($seasonal_product_id);
            }
        }
        if ($compare_product_id) {
            $compareSeasonalProduct = Product::find($compare_product_id);
            if ($compareSeasonalProduct) {
                [$seasonalLabels, $compareData] = $this->getProductTrend($compare_product_id, $seasonalLabels);
            }
        }

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
            'patientVisitData'
        ) + [ 
            'filterTimespanLabel' => $this->getTimespanLabel($timespan, $dateRange),
            'filterBarangayLabel' => $filter_barangay ?? 'All Barangays',
            'filterProductLabel' => $drilldownProduct->generic_name ?? 'All Products',
            'filterBranchLabel' => $filter_branch ? Branch::find($filter_branch)->name : 'All Branches',
        ]);
    }

    // --- Helper functions ---

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
            $selectedSeasonalProduct = Product::find($seasonal_product_id);
            if ($selectedSeasonalProduct) {
                [$seasonalLabels, $seasonalData] = $this->getProductTrend($seasonal_product_id);
            }
        }
        if ($compare_product_id) {
            $compareSeasonalProduct = Product::find($compare_product_id);
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

        // === CONFIGURATION ===
        $baseUrl = "https://ai-api.hostcluster.site/api/chat";
        $model = 'glm-4.7:cloud'; 
        
        $productName = $validated['product_name'];

        // === PREPARE DATA ===
        $dataString = collect($validated['seasonal_data'])->map(function ($item) {
            return "- {$item['label']}: {$item['data']}";
        })->join("\n");

        // === CSS STYLES FOR THE PROMPT ===
        // We define the design system here so the AI knows exactly how to style the result
        $css = "
            body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #334155; line-height: 1.6; }
            .card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
            .section-title { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
            .highlight { background-color: #eff6ff; color: #1e40af; padding: 2px 6px; border-radius: 4px; font-weight: 600; }
            .alert-box { padding: 16px; border-radius: 8px; margin-top: 12px; border-left: 4px solid; }
            .alert-warning { background-color: #fff7ed; border-color: #f97316; color: #9a3412; }
            .alert-success { background-color: #f0fdf4; border-color: #22c55e; color: #166534; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 0.95rem; }
            th { text-align: left; padding: 12px; background-color: #f8fafc; color: #475569; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
            td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
            tr:last-child td { border-bottom: none; }
            ul { list-style-type: none; padding-left: 0; }
            li { margin-bottom: 8px; padding-left: 24px; position: relative; }
            li::before { content: '•'; color: #3b82f6; font-weight: bold; position: absolute; left: 0; font-size: 1.2em; line-height: 1; }
        ";

        // System Prompt: Persona + Design Constraints
        $systemInstruction = "You are an expert Inventory Manager and Data Analyst for a Philippine public health clinic. 
        Your goal is to analyze medical dispensation data and provide actionable insights.
        
        **CRITICAL OUTPUT RULES:**
        1. You MUST output **RAW HTML only**. No Markdown (no # or **), no conversational text outside the HTML tags.
        2. Embed the following CSS styles in a <style> block at the very top of your response to ensure a beautiful design.
        3. Be concise, professional, and data-driven.
        4. Use emojis in headings for visual appeal.
        5. Focus on trends, seasonality (linking to PH seasons like Rainy/Flu/Christmas), and inventory risks.";

        // User Prompt Construction
        $userQuery = "<style>{$css}</style>";
        
        $userQuery .= "<div class='card'>
            <h1 class='section-title'>📊 Strategic Analysis: <span class='highlight'>{$productName}</span></h1>
            <p><strong>Data Provided:</strong></p>
            <pre style='background:#f8fafc; padding:10px; border-radius:6px; overflow-x:auto; font-size:0.85rem;'>{$dataString}</pre>
        </div>";

        if (!empty($validated['compare_product_name'])) {
            // === COMPARISON MODE ===
            $compareName = $validated['compare_product_name'];
            $compareString = collect($validated['compare_data'])->map(function ($item) {
                return "- {$item['label']}: {$item['data']}";
            })->join("\n");

            $userQuery .= "
            <div class='card'>
                <h2 class='section-title'>🤝 Competitive Comparison: {$productName} vs {$compareName}</h2>
                <p>Compare the dispensation trends, peak usage periods, and volatility of these two products.</p>
                
                <h3 style='font-size:1.1rem; margin-top:20px; color:#334155;'>Performance Metrics</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>{$productName}</th>
                            <th>{$compareName}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Overall Trend</strong></td>
                            <td>[Describe rising, falling, or stable based on data]</td>
                            <td>[Describe rising, falling, or stable based on data]</td>
                        </tr>
                        <tr>
                            <td><strong>Peak Month(s)</strong></td>
                            <td>[Identify highest usage month and value]</td>
                            <td>[Identify highest usage month and value]</td>
                        </tr>
                        <tr>
                            <td><strong>Lowest Month(s)</strong></td>
                            <td>[Identify lowest usage month and value]</td>
                            <td>[Identify lowest usage month and value]</td>
                        </tr>
                        <tr>
                            <td><strong>Volatility</strong></td>
                            <td>[High/Medium/Low - based on variance]</td>
                            <td>[High/Medium/Low - based on variance]</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class='card'>
                <h2 class='section-title'>💡 Strategic Insights</h2>
                <ul>
                    <li><strong>Correlation:</strong> Do these products move together? Is one seasonal while the other is consistent?</li>
                    <li><strong>Winner:</strong> Which product has higher demand stability or growth potential?</li>
                    <li><strong>Context:</strong> Are peaks related to specific events (e.g., flu season for one, chronic maintenance for the other)?</li>
                </ul>
            </div>

            <div class='card'>
                <h2 class='section-title'>📈 Predictive Recommendations</h2>
                <div class='alert-box alert-success'>
                    <strong>For {$productName}:</strong>
                    <p>[Provide specific stock advice, e.g., 'Increase buffer stock by 20% in December']</p>
                </div>
                <div class='alert-box alert-warning'>
                    <strong>For {$compareName}:</strong>
                    <p>[Provide specific stock advice, e.g., 'Maintain lean inventory due to low volatility']</p>
                </div>
            </div>
            ";

        } else {
            // === SINGLE PRODUCT MODE ===
            $userQuery .= "
            <div class='card'>
                <h2 class='section-title'>📉 Trend Analysis</h2>
                <p>Provide a 2-3 sentence summary of the demand pattern.</p>
                
                <h3 style='font-size:1.1rem; margin-top:20px; color:#334155;'>Key Data Points</h3>
                <ul>
                    <li><strong>Peak Demand:</strong> Identify the month(s) with highest usage. Why might this be happening in the Philippines context?</li>
                    <li><strong>Troughs/Zeros:</strong> Identify months with low or zero usage. Is this seasonal or a supply issue?</li>
                    <li><strong>Consistency:</strong> Is the usage steady throughout the year or sporadic?</li>
                </ul>
            </div>

            <div class='card'>
                <h2 class='section-title'>🌏 Contextual Insights (Philippines)</h2>
                <p>Analyze the external factors:</p>
                <ul>
                    <li><strong>Weather Patterns:</strong> Do peaks align with the rainy season (June-Nov) or dry season?</li>
                    <li><strong>Holidays:</strong> Is there a surge or drop during December/Christmas?</li>
                    <li><strong>Public Health Events:</strong> Potential correlation with Dengue or Flu outbreaks?</li>
                </ul>
            </div>

            <div class='card'>
                <h2 class='section-title'>📦 Inventory Action Plan</h2>
                <div class='alert-box alert-warning'>
                    <strong>Immediate Action:</strong>
                    <p>Based on current trend, should we order more or less right now?</p>
                </div>
                <div class='alert-box alert-success'>
                    <strong>Forecasting:</strong>
                    <p>Predict the needs for the next 3 months. Suggest a reorder point or safety stock level logic.</p>
                </div>
            </div>
            ";
        }

        // Ollama Payload
        $payload = [
            'model'  => $model,
            'stream' => false,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemInstruction
                ],
                [
                    'role' => 'user',
                    'content' => $userQuery
                ]
            ],
            'options' => [
                'temperature' => 0.6, // Slightly lower for more factual table data
                'num_ctx'     => 8192 // Larger context for the long HTML prompt
            ]
        ];

        try {
            $response = Http::timeout(120) 
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($baseUrl, $payload);

            if (!$response->successful()) {
                Log::error('Ollama API request failed', [
                    'status' => $response->status(), 
                    'body' => $response->body()
                ]);
                return response()->json(['error' => 'The AI service failed to respond.'], 500);
            }

            $jsonResponse = $response->json();
            $text = data_get($jsonResponse, 'message.content');

            if ($text) {
                // Remove any markdown bolding artifacts that might have slipped through
                $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
                // Remove markdown headers
                $text = preg_replace('/^#(.*?)$/m', '<h3>$1</h3>', $text);
                
                return response()->json(['analysis' => trim($text)]);
            } else {
                Log::error('Ollama API gave no content', ['response' => $jsonResponse]);
                return response()->json(['error' => 'No valid response received from the AI analysis service.'], 500);
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection Error calling Ollama: ' . $e->getMessage());
            return response()->json(['error' => 'Could not connect to the AI analysis service. Please check the network connection.'], 503);
        } catch (\Exception $e) {
            Log::error('Error calling Ollama: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred while contacting the AI analysis service.'], 500);
        }
    }
}