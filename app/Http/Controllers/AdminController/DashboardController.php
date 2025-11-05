<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Patientrecords;
use App\Models\ProductMovement;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder; // Import Builder for type hinting
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View; // Import View
use Illuminate\Http\JsonResponse; // Import JsonResponse

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard with analytics or return AJAX data.
     * Fixed: All queries referencing non-existent 'barangay' column now join with 'barangays' table
     * to use 'barangays.barangay_name as barangay' for selection, grouping, ordering, and filtering.
     */
    public function showdashboard(Request $request): View | JsonResponse
    {
        // === 0. GET FILTERS WITH DEFAULTS ===
        $inputs = $request->validate([
            'filter_timespan' => 'nullable|string|in:7d,30d,90d,1y,all,custom',
            'filter_start' => 'nullable|date|required_if:filter_timespan,custom',
            'filter_end' => 'nullable|date|required_if:filter_timespan,custom|after_or_equal:filter_start',
            'filter_barangay' => 'nullable|string|max:255',
            'forecast_days' => 'nullable|integer|in:30,60,90,180',
            'grouping' => 'nullable|string|in:day,week,month',
            'drilldown_product_id' => 'nullable|integer|exists:products,id',
            'seasonal_product_id' => 'nullable|integer|exists:products,id',
            'compare_product_id' => 'nullable|integer|exists:products,id',
            'ajax_update' => 'nullable|string|in:forecast,seasonal,main_charts' // Add ajax_update
        ]);

        $timespan = $inputs['filter_timespan'] ?? '30d';
        $filter_barangay = $inputs['filter_barangay'] ?? null;
        $forecast_days = $inputs['forecast_days'] ?? 90;
        $grouping = $inputs['grouping'] ?? 'day';
        $drilldown_product_id = $inputs['drilldown_product_id'] ?? null;
        $drilldownProduct = $drilldown_product_id ? Product::find($drilldown_product_id) : null;
        $drilldown_product_name = $drilldownProduct->generic_name ?? null;
        $seasonal_product_id = $inputs['seasonal_product_id'] ?? Product::where('is_archived', 2)->value('id');
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
                    } else {
                        $dateRange->start = $newStartDate;
                    }
                } else {
                    $dateRange->start = $newStartDate;
                }
            }
        }

        // === 1. AJAX: Forecast Table Update ===
        if ($request->ajax() && $request->input('ajax_update') == 'forecast') {
            $forecast = $this->calculateStockForecast($forecast_days);
            // Ensure you have this partial view created
            $forecastHtml = view('admin.partials._forecast_table_body', compact('forecast'))->render();
            return response()->json(['forecastHtml' => $forecastHtml]);
        }

        // === 2. AJAX: Seasonal Chart Update ===
        if ($request->ajax() && $request->input('ajax_update') == 'seasonal') {
            $seasonalData = $this->getSeasonalDataForAjax($seasonal_product_id, $compare_product_id);
            return response()->json(['seasonal' => $seasonalData]);
        }

        // === 3. AJAX: Main Charts / Drilldown Update ===
        // This catches 'ajax_update' == 'main_charts' OR the original drilldown click (which is just request->ajax())
        if ($request->ajax() || $request->wantsJson()) {
            // --- Calculate data needed for this JSON response ---

            // Consumption Trend Data
            [$consumptionLabels, $consumptionData] = $this->getConsumptionTrend(
                $dateRange, $drilldown_product_id, $filter_barangay, $grouping
            );

            // Patient Visit Trend Data
            [$patientVisitLabels, $patientVisitData] = $this->getPatientVisitTrend(
                $dateRange, $filter_barangay, $drilldownProduct, $grouping
            );

            // Barangay Data for Stacked Chart
            // Fixed: Added join with 'barangays' to access 'barangay_name as barangay'
            $barangayCategoryData = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
                ->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                ->when($filter_barangay, function ($q) use ($filter_barangay) {
                    return $q->where('barangays.barangay_name', $filter_barangay);
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
            // Fixed: Added join with 'barangays' to access 'barangay_name as barangay'
            $patientHotspots = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
                ->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                ->when($filter_barangay, function ($q) use ($filter_barangay) {
                    return $q->where('barangays.barangay_name', $filter_barangay);
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
                ->when($filter_barangay, function ($query) use ($filter_barangay, $dateRange) {
                    // Fixed: Added join with 'barangays' for barangay filtering
                    $patientRecordIds = Patientrecords::join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                                        ->where('barangays.barangay_name', $filter_barangay)
                                        ->whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
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

            // --- Render partials ---
            $hotspotsHtml = view('admin.partials._hotspots_table_body', compact('patientHotspots'))->render();

            // --- Return the comprehensive JSON response ---
            return response()->json([
                'consumptionLabels' => $consumptionLabels,
                'consumptionData' => $consumptionData,
                'hotspotsHtml' => $hotspotsHtml,
                'drilldownProductName' => $drilldown_product_name,
                'filterTimespanLabel' => $this->getTimespanLabel($timespan, $dateRange),
                'filterBarangayLabel' => $filter_barangay ?? 'All Barangays',

                // Data for Top Products Chart
                'topProducts' => [
                    'labels'    => $topProducts->keys(),
                    'data'      => $topProducts->values(),
                    'drilldown' => $topProductsData->map(function($item) {
                        return ['label' => $item->generic_name, 'id' => $item->product_id];
                    }),
                ],
                // Data for Barangay Chart
                'barangay' => [
                    'labels' => $barangays,
                    'stackedData' => $barangayStackedData,
                ],
                // Data for Patient Visit Chart
                'patientVisit' => [
                    'labels' => $patientVisitLabels,
                    'data' => $patientVisitData,
                ]
            ]);
        }

        // === 4. FULL PAGE LOAD DATA (Only calculate if not an AJAX request) ===
        // These are the calculations needed for the *initial* page load.

        // Consumption, Patient Visit, Barangay, Hotspots
        [$consumptionLabels, $consumptionData] = $this->getConsumptionTrend(
            $dateRange, $drilldown_product_id, $filter_barangay, $grouping
        );
        [$patientVisitLabels, $patientVisitData] = $this->getPatientVisitTrend(
            $dateRange, $filter_barangay, $drilldownProduct, $grouping
        );
        // Fixed: Added join with 'barangays' to access 'barangay_name as barangay'
        $barangayCategoryData = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
            ->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
            ->when($filter_barangay, function ($q) use ($filter_barangay) {
                return $q->where('barangays.barangay_name', $filter_barangay);
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

        // Fixed: Added join with 'barangays' to access 'barangay_name as barangay'
        $patientHotspots = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
            ->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
            ->when($filter_barangay, function ($q) use ($filter_barangay) {
                return $q->where('barangays.barangay_name', $filter_barangay);
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

        // KPI Cards, Urgent Alerts, Forecast
        $totalStockItems = Inventory::where('is_archived', 2)->sum('quantity');
        $lowStockProducts = Inventory::where('is_archived', 2)->where('quantity', '>', 0)->where('quantity', '<=', 100)->distinct('product_id')->count();
        $patientsToday = Patientrecords::whereDate('date_dispensed', Carbon::today())->count();
        $expiringIn30Days = Inventory::where('is_archived', 2)
            ->where('expiry_date', '>', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->count();
        $kpiCards = [
            'totalStockItems' => $totalStockItems,
            'lowStockProducts' => $lowStockProducts,
            'patientsToday' => $patientsToday,
            'expiringIn30Days' => $expiringIn30Days,
        ];
        $urgent_low_stock = Inventory::with('product')
            ->where('is_archived', 2)
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 100)
            ->orderBy('quantity', 'asc')
            ->take(5)
            ->get();
        $urgent_expiring_soon = Inventory::with('product')
            ->where('is_archived', 2)
            ->where('expiry_date', '>', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->orderBy('expiry_date', 'asc')
            ->take(5)
            ->get();
        $forecast = $this->calculateStockForecast($forecast_days);

        // Top Products Chart
        $topProductsQuery = ProductMovement::where('product_movements.type', 'OUT')
            ->whereBetween('product_movements.created_at', [$dateRange->start, $dateRange->end])
            ->when($filter_barangay, function ($query) use ($filter_barangay, $dateRange) {
                // Fixed: Added join with 'barangays' for barangay filtering
                $patientRecordIds = Patientrecords::join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                                    ->where('barangays.barangay_name', $filter_barangay)
                                    ->whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
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
        // Fixed: Added join with 'barangays' to access 'barangay_name as barangay'
        $filter_products = Product::where('is_archived', 2)->orderBy('generic_name')->get(['id', 'generic_name', 'brand_name']);
        $filter_barangays = Patientrecords::join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
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
            'barangays', 'barangayStackedData', // Use new stacked data
            'filter_products', 'filter_barangays',
            'drilldown_product_name', 'inputs',
            'seasonalLabels', 'seasonalData', 'selectedSeasonalProduct',
            'compareData', 'compareSeasonalProduct',
            'patientHotspots',
            'patientVisitLabels', // Added
            'patientVisitData'  // Added
        ) + [ // <-- Corrected array merge
            'filterTimespanLabel' => $this->getTimespanLabel($timespan, $dateRange),
            'filterBarangayLabel' => $filter_barangay ?? 'All Barangays',
        ]);
    }

    /**
     * Handle the secure, server-side request for AI trend analysis using OpenAI.
     * Fixed: No database queries affected; only OpenAI API handling.
     */
    public function getAiAnalysis(Request $request)
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

        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            Log::error('OPENAI_API_KEY is not set in .env file.');
            return response()->json(['error' => 'AI analysis is not configured on the server.'], 500);
        }

        $productName = $validated['product_name'];

        // --- FIX: Use traditional function syntax for map ---
        $dataString = collect($validated['seasonal_data'])->map(function ($item) {
            return "- {$item['label']}: {$item['data']}";
        })->join("\n");

        $systemPrompt = "You are a helpful data analyst for a public health clinic in the Philippines. Your tone is professional, insightful, and concise. Do not use markdown (like *, #, or lists). Respond in plain paragraphs.";

        $userQuery = "Analyze the following monthly dispensation data (items dispensed per month) for the product '$productName':\n\n$dataString\n\n";

        if (!empty($validated['compare_product_name'])) {
            $compareName = $validated['compare_product_name'];
            // --- FIX: Use traditional function syntax for map ---
            $compareString = collect($validated['compare_data'])->map(function ($item) {
                return "- {$item['label']}: {$item['data']}";
            })->join("\n");
            $userQuery .= "For comparison, here is the data for '$compareName':\n\n$compareString\n\n";
            $userQuery .= "Please analyze both products. Compare their trends, note any seasonal peaks or similarities (e.g., 'both peak around August'), explain potential reasons for the trends (e.g., related to seasons), and provide a simple 1-sentence predictive recommendation for managing stock for *each* product based on this comparison.";
        } else {
            $userQuery .= "Based ONLY on the data provided, please provide the following in plain paragraphs:\n1. A brief summary of any seasonal trends or significant peaks/troughs you observe.\n2. An insight into potential reasons *why* these trends might be happening (e.g., linking peaks to rainy season, flu season, etc.).\n3. A simple 1-sentence predictive recommendation for managing stock (e.g., 'Prepare for higher demand around August-October.').";
        }

        $apiUrl = "https://api.openai.com/v1/chat/completions";

        $payload = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userQuery]
            ],
            'temperature' => 0.6,
            'max_tokens' => 500,
        ];

        try {
            $response = Http::withToken($apiKey)
                            ->timeout(60)
                            ->post($apiUrl, $payload);

            if (!$response->successful()) {
                Log::error('OpenAI API request failed', ['status' => $response->status(), 'body' => $response->json()]);
                $errorBody = $response->json('error.message', 'The AI service failed to respond.');
                return response()->json(['error' => $errorBody], $response->status());
            }

            $text = data_get($response->json(), 'choices.0.message.content');

            if ($text) {
                $cleanedText = preg_replace('/^\s*[\*\-\d]+\.?\s*/m', '', $text);
                return response()->json(['analysis' => nl2br(trim($cleanedText))]);
            } else {
                $finishReason = data_get($response->json(), 'choices.0.finish_reason');
                Log::error('OpenAI API gave no content', ['reason' => $finishReason, 'response' => $response->json()]);

                if ($finishReason === 'content_filter') {
                    return response()->json(['error' => 'The AI analysis was blocked due to content filters.'], 400);
                }

                return response()->json(['error' => 'No valid response received from the AI analysis service.'], 500);
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection Error calling OpenAI API: ' . $e->getMessage());
            return response()->json(['error' => 'Could not connect to the AI analysis service. Please check the network connection.'], 503);
        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred while contacting the AI analysis service.'], 500);
        }
    }

    // --- Helper functions ---

    /**
     * Get human-readable label for timespan filter.
     */
    private function getTimespanLabel($timespan, $dateRange)
    {
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

    /**
     * Helper to calculate the date range based on filter input.
     */
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

        // Ensure start is never after end
        if ($dateRange->start->gt($dateRange->end)) {
            $dateRange->start = $dateRange->end->copy()->startOfDay();
        }

        return $dateRange;
    }

    /**
     * Helper to get consumption trend data (OUT only) for the line chart.
     * Fixed: Added join with 'barangays' for barangay filtering in patient record ID retrieval.
     */
    private function getConsumptionTrend($dateRange, $product_id, $barangay, $grouping) // Added $barangay
    {
        // Start query on ProductMovement
        $query = ProductMovement::where('product_movements.type', 'OUT')
            ->whereBetween('product_movements.created_at', [$dateRange->start, $dateRange->end])
            // --- FIX: Use traditional function syntax ---
            ->when($product_id, function ($query) use ($product_id) {
                return $query->where('product_movements.product_id', $product_id);
            });

        // Filter by Barangay if provided
        if ($barangay) {
            // Get patient record IDs for the specified barangay within the date range
            // Fixed: Added join with 'barangays' to filter by 'barangay_name'
            $patientRecordIds = Patientrecords::join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                                      ->where('barangays.barangay_name', $barangay)
                                      ->whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
                                      ->pluck('patientrecords.id');

            // Filter movements associated with these patient records based on description
            // This relies on the description format "Record: #[ID])"
            $query->where(function($q) use ($patientRecordIds) {
                if ($patientRecordIds->isEmpty()) {
                    $q->whereRaw('1 = 0'); // No matching patients, so no matching movements
                } else {
                    // Build OR conditions for each matching patient record ID
                    foreach ($patientRecordIds as $id) {
                        // Use parameter binding for safety? No, LIKE needs the value directly
                        $q->orWhere('description', 'LIKE', "%Record: #{$id})%");
                    }
                }
            });
        }

        // Period and Grouping Logic (remains the same)
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

        // Fill in missing (remains the same)
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

    /**
     * --- NEW HELPER ---
     * Helper to get Patient Visit trend data for the new line chart.
     * Fixed: Added join with 'barangays' for barangay filtering.
     */
    private function getPatientVisitTrend($dateRange, $barangay, $drilldownProduct, $grouping)
    {
        // 1. Define Period and Grouping (copied from getConsumptionTrend)
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

        // 2. Get Patient Visits
        $patientVisitsQuery = Patientrecords::whereBetween('date_dispensed', [$dateRange->start, $dateRange->end])
            ->when($barangay, function ($q) use ($barangay) {
                // Fixed: Added join with 'barangays' to filter by 'barangay_name'
                $q->join('barangays', 'patientrecords.barangay_id', '=', 'barangays.id')
                  ->where('barangays.barangay_name', $barangay);
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

        // If barangay filter is applied, ensure join is present for the whole query
        if ($barangay) {
            $patientVisitsQuery->select('patientrecords.*'); // Ensure patientrecords fields are available
        }

        $patientVisits = $patientVisitsQuery->get()
            ->pluck('total_patients', $orderByColumn);

        // 3. Combine data using the generated period
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

    // --- getProductTrend, calculateStockForecast remain unchanged ---
    /**
     * Gets all-time seasonal trend data, can optionally align to existing labels.
     */
    private function getProductTrend($product_id, $alignLabels = null)
    {
        // Ensure start date is at least 3 years ago or the first movement, whichever is later
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
        // Ensure start date is not in the future
        if ($startDate->gt(Carbon::now())) {
            $startDate = Carbon::now()->startOfMonth();
        }

        $query = ProductMovement::where('type', 'OUT')
            ->where('product_id', $product_id)
            ->where('created_at', '>=', $startDate) // Use calculated start date
            ->groupBy('date_group') // Use alias for grouping
            ->orderBy('date_group', 'asc') // Use alias for ordering
            ->select( // Use select() here
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date_group"),
                DB::raw('SUM(quantity) as total_quantity')
            )
            ->get() // Fetch selected columns
            ->pluck('total_quantity', 'date_group'); // Pluck using alias

        if ($query->isEmpty() && !$alignLabels) { // If no data AND not aligning, return empty
            return [[], []];
        }

        $labels = [];
        $data = [];
        $endDate = Carbon::now()->startOfMonth(); // Ensure period ends at the beginning of the current month

        // Determine the period based on alignment or query results
        if ($alignLabels) {
            $period = collect($alignLabels)->map(function($l) {
                // Try parsing different formats just in case
                try {
                    return Carbon::parse($l)->startOfMonth();
                } catch (\Exception $e) {
                    return null;
                }
            })->filter()->unique(); // Filter out nulls and ensure uniqueness

            // Fallback if alignment labels are invalid or empty
            if ($period->isEmpty()) {
                if ($query->isEmpty()) return [[],[]]; // No data, no alignment possible
                $periodStartDate = Carbon::parse($query->keys()->first() . '-01');
                if ($periodStartDate->gt($endDate)) $periodStartDate = $endDate->copy(); // Prevent start > end
                $period = CarbonPeriod::create($periodStartDate, '1 month', $endDate);
                $alignLabels = null; // Disable alignment
            } else {
                // Use the min/max of parsed labels for the period if aligning
                $period = CarbonPeriod::create($period->min(), '1 month', $period->max());
            }

        } else { // Not aligning
            if ($query->isEmpty()) return [[],[]]; // No data, return empty
            $periodStartDate = Carbon::parse($query->keys()->first() . '-01');
            // Ensure start date respects the 3-year limit
            if ($periodStartDate->lt($threeYearsAgo)) {
                $periodStartDate = $threeYearsAgo;
            }
            // Ensure start date is not after end date
            if ($periodStartDate->gt($endDate)) {
                $periodStartDate = $endDate->copy();
            }
            $period = CarbonPeriod::create($periodStartDate, '1 month', $endDate);
        }

        // Populate labels and data
        if ($period) { // Add null check for period
            foreach ($period as $date) {
                $key = $date->format('Y-m');
                if (!$alignLabels) { // Only generate new labels if not aligning
                    $labels[] = $date->format('M Y');
                }
                $data[] = $query[$key] ?? 0;
            }
        }

        // If aligning, labels are just the input alignLabels (already prepared)
        if ($alignLabels && $period) { // Add null check for period
            // Re-generate labels from the potentially adjusted period for consistency
            $labels = [];
            foreach($period as $date) {
                $labels[] = $date->format('M Y');
            }
        } elseif ($alignLabels) { // If aligning but period failed, return original labels
            $labels = $alignLabels;
        }

        return [$labels, $data];
    }

    /**
     * Calculates the "Days of Stock Remaining".
     */
    private function calculateStockForecast($daysOfHistory = 90)
    {
        if ($daysOfHistory <= 0) $daysOfHistory = 90; // Ensure positive days

        // 1. Get total dispensation (OUT)
        $consumption = ProductMovement::where('type', 'OUT')
            ->where('created_at', '>=', Carbon::now()->subDays($daysOfHistory))
            ->groupBy('product_id')
            ->select('product_id', DB::raw("SUM(quantity) as total_consumed"))
            ->pluck('total_consumed', 'product_id');

        // 2. Get the current stock level
        $currentStock = Inventory::where('is_archived', 2)
            ->groupBy('product_id')
            ->select('product_id', DB::raw("SUM(quantity) as current_quantity"))
            ->pluck('current_quantity', 'product_id');

        // 3. Get product details
        $products = Product::whereIn('id', $currentStock->keys())->get()->keyBy('id');

        $forecast = [];

        // 4. Calculate forecast
        foreach ($currentStock as $product_id => $stock) {

            if (!isset($products[$product_id])) continue;

            $totalConsumed = $consumption[$product_id] ?? 0;
            $avgDailyUsage = ($daysOfHistory > 0) ? $totalConsumed / $daysOfHistory : 0; // Avoid division by zero

            if ($avgDailyUsage > 0) {
                // Use max(0.01, ...) to avoid division by zero errors with tiny usage rates
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

        // Sort by most urgent
        usort($forecast, function ($a, $b) {
            // Treat INF as very large number for sorting
            $aDays = ($a['days_remaining'] === INF) ? PHP_INT_MAX : $a['days_remaining'];
            $bDays = ($b['days_remaining'] === INF) ? PHP_INT_MAX : $b['days_remaining'];
            return $aDays <=> $bDays;
        });

        return $forecast;
    }

    /**
     * --- NEW HELPER ---
     * Get seasonal data formatted for AJAX response.
     */
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
}