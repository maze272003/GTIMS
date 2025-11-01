@php
  use Carbon\Carbon;
  // Get validated inputs from controller, default to empty array if not passed
  $filterInputs = $inputs ?? []; 
@endphp
<x-app-layout>
<body class="bg-gray-50">

  {{-- Sidebar --}}
  <x-admin.sidebar/>
  
  {{-- CSRF TOKEN META TAG --}}
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
    {{-- Header --}}
    <x-admin.header/>

    <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
      {{-- Breadcrumb --}}
      <div class="mb-6 pt-16">
        <p class="text-3xl font-bold text-gray-900">Analytics Dashboard</p>
        <p class="text-sm text-gray-600">Welcome! Here's your clinic's predictive overview.</p>
      </div>

      {{-- 1. KPI CARDS --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Total Stock --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Total Stock (Items)</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">
                {{ number_format($kpiCards['totalStockItems']) }}
              </p>
            </div>
            <div class="bg-blue-100 p-4 rounded-full">
              <i class="fa-regular fa-boxes-stacked text-2xl text-blue-600"></i>
            </div>
          </div>
        </div>
        {{-- Low Stock --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Low Stock Products</p>
              <p class="text-3xl font-bold text-orange-600 mt-2">
                {{ $kpiCards['lowStockProducts'] }}
              </p>
            </div>
            <div class="bg-orange-100 p-4 rounded-full">
              <i class="fa-regular fa-exclamation text-2xl text-orange-600"></i>
            </div>
          </div>
        </div>
        {{-- Expiring Soon --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Batches Expiring Soon</p>
              <p class="text-3xl font-bold text-yellow-600 mt-2">
                {{ $kpiCards['expiringIn30Days'] }}
              </p>
            </div>
            <div class="bg-yellow-100 p-4 rounded-full">
              <i class="fa-regular fa-clock text-2xl text-yellow-600"></i>
            </div>
          </div>
        </div>
        {{-- Patients Today --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Patients Today</p>
              <p class="text-3xl font-bold text-green-600 mt-2">
                {{ $kpiCards['patientsToday'] }}
              </p>
            </div>
            <div class="bg-green-100 p-4 rounded-full">
              <i class="fa-regular fa-user-group text-2xl text-green-600"></i>
            </div>
          </div>
        </div>
      </div>
      {{-- End KPI Cards --}}


      {{-- 2. PREDICTIVE FORECAST & URGENT ACTIONS --}}
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        
        {{-- STOCK DEPLETION FORECAST --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200">
            {{-- Header with Filter --}}
          <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">
                <i class="fa-regular fa-chart-line-down text-red-600 mr-2"></i>Stock Depletion Forecast
              </h3>
              <p class="text-sm text-gray-500">Predicts run-out based on recent consumption.</p>
            </div>
              {{-- Forecast History Filter (Moved Here) --}}
            <div class="mt-2 sm:mt-0">
                {{-- Removed onchange, now handled by JS --}}
              <form action="{{ route('admin.dashboard') }}" method="GET" id="forecast-filter-form" class="flex items-center gap-x-2">
                  {{-- Persist other filters --}}
                  @foreach($filterInputs as $key => $value)
                    @if($key != 'forecast_days' && $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                  @endforeach
                  <label for="forecast_days_select" class="text-sm font-medium text-gray-700 whitespace-nowrap">History:</label>
                  <select name="forecast_days" id="forecast_days_select" class="pl-2 pr-8 py-1 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                    <option value="30" @selected( ($filterInputs['forecast_days'] ?? 90) == 30)>30 Days</option>
                    <option value="60" @selected( ($filterInputs['forecast_days'] ?? 90) == 60)>60 Days</option>
                    <option value="90" @selected( ($filterInputs['forecast_days'] ?? 90) == 90)>90 Days</option>
                    <option value="180" @selected( ($filterInputs['forecast_days'] ?? 90) == 180)>180 Days</option>
                  </select>
              </form>
            </div>
          </div>
          {{-- Table --}}
          <div class="overflow-y-auto h-96 relative"> {{-- Added relative for loader --}}
            <table class="w-full text-sm text-left text-gray-500">
              <thead class="text-xs text-gray-700 uppercase bg-gray-100 sticky top-0">
                <tr>
                  <th scope="col" class="px-6 py-3">Product</th>
                  <th scope="col" class="px-6 py-3 text-center">Days Remaining</th>
                  <th scope="col" class="px-6 py-3 text-center">Current Stock</th>
                  <th scope="col" class="px-6 py-3 text-center">Avg. Daily Usage</th>
                </tr>
              </thead>
              {{-- Added ID for AJAX update --}}
              <tbody id="forecast-table-body"> 
                {{-- Render partial for initial load and for AJAX updates --}}
                @include('admin.partials._forecast_table_body', ['forecast' => $forecast])
              </tbody>
            </table>
          </div>
        </div>

        {{-- URGENT ALERTS --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
          <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">
              <i class="fa-regular fa-triangle-exclamation text-orange-600 mr-2"></i>Actionable Alerts
            </h3>
            <p class="text-sm text-gray-500">Items needing immediate attention.</p>
          </div>
          <div class="p-4 overflow-y-auto h-96">
            {{-- Low Stock --}}
            <h4 class="font-semibold text-orange-600">Critical Low Stock (<= 100)</h4>
            <ul class="divide-y divide-gray-200 mt-2">
              @forelse($urgent_low_stock as $item)
                <li class="py-2 flex justify-between items-center">
                  <div>
                    <p class="font-medium text-sm text-gray-800">{{ $item->product->generic_name }}</p>
                    <p class="text-xs text-gray-500">{{ $item->batch_number }}</p>
                  </div>
                  <span class="font-bold text-sm text-orange-600">{{ $item->quantity }} left</span>
                </li>
              @empty
                <li class="py-2 text-sm text-gray-500">No products are critically low.</li>
              @endforelse
            </ul>

            {{-- Expiring Soon --}}
            <h4 class="font-semibold text-yellow-600 mt-4">Expiring in 30 Days</h4>
            <ul class="divide-y divide-gray-200 mt-2">
              @forelse($urgent_expiring_soon as $item)
                <li class="py-2 flex justify-between items-center">
                  <div>
                    <p class="font-medium text-sm text-gray-800">{{ $item->product->generic_name }}</p>
                    <p class="text-xs text-gray-500">{{ $item->batch_number }}</p>
                  </div>
                  <span class="font-bold text-sm text-yellow-600">
                    {{ Carbon::parse($item->expiry_date)->format('M d, Y') }}
                  </span>
                </li>
              @empty
                <li class="py-2 text-sm text-gray-500">No batches are expiring soon.</li>
              @endforelse
            </ul>
          </div>
        </div>

      </div>
      {{-- End Predictive & Urgent --}}

      {{-- 0. CHART FILTERS --}}
      <div class="my-6 bg-white rounded-xl shadow-sm border border-gray-200">
        {{-- Drill-down Indicator --}}
        <div class="drilldown-indicator p-4 bg-blue-50 border-b border-blue-200 flex items-center justify-between" style="display: {{ $drilldown_product_name ? 'flex' : 'none' }};">
          <div class="flex items-center">
            <i class="fa-regular fa-filter-list text-blue-600 mr-3"></i>
            <span class="text-sm font-medium text-blue-800">
              Drill-Down Active: Showing chart data for <strong id="drilldown-indicator-name">{{ $drilldown_product_name ?? '' }}</strong>.
            </span>
          </div>
            {{-- Updated link to use AJAX clear function --}}
          <a href="#" id="clear-drilldown-ajax" class="px-3 py-1 text-xs font-medium text-blue-700 bg-white border border-blue-600 rounded-full hover:bg-blue-100">Clear Drill-Down</a>
        </div>
        
        {{-- Main Filter Form --}}
        <form id="dashboard-filter-form" action="{{ route('admin.dashboard') }}" method="GET">
          {{-- Hidden field for drill-down --}}
          <input type="hidden" name="drilldown_product_id" id="drilldown_product_id" value="{{ $inputs['drilldown_product_id'] ?? '' }}">
          {{-- Hidden field for forecast days (to persist it) --}}
          <input type="hidden" name="forecast_days" value="{{ $inputs['forecast_days'] ?? 90 }}">

          
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4"> {{-- Adjusted to 3 columns --}}
              
              {{-- Timespan --}}
              <div>
                <label for="filter_timespan" class="block text-sm font-medium text-gray-700 mb-1">Time Period</label>
                <select name="filter_timespan" id="filter_timespan" class="w-full pl-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                  <option value="7d"   @selected( ($filterInputs['filter_timespan'] ?? '30d') == '7d')>Last 7 Days</option>
                  <option value="30d"  @selected( ($filterInputs['filter_timespan'] ?? '30d') == '30d')>Last 30 Days</option>
                  <option value="90d"  @selected( ($filterInputs['filter_timespan'] ?? '30d') == '90d')>Last 90 Days</option>
                  <option value="1y"   @selected( ($filterInputs['filter_timespan'] ?? '30d') == '1y')>Last 1 Year</option>
                  <option value="all"  @selected( ($filterInputs['filter_timespan'] ?? '30d') == 'all')>All Time</option>
                  <option value="custom" @selected( ($filterInputs['filter_timespan'] ?? '30d') == 'custom')>Custom Range</option>
                </select>
              </div>

              {{-- Barangay Filter --}}
              <div>
                <label for="filter_barangay" class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
                <select name="filter_barangay" id="filter_barangay" class="w-full pl-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                  <option value="">All Barangays</option>
                  @foreach($filter_barangays as $barangay)
                    <option value="{{ $barangay }}" @selected( ($filterInputs['filter_barangay'] ?? '') == $barangay)>
                      {{ $barangay }}
                    </option>
                  @endforeach
                </select>
              </div>
              
              {{-- Grouping --}}
              <div>
                <label for="grouping" class="block text-sm font-medium text-gray-700 mb-1">Group Trend By</label>
                <select name="grouping" id="grouping" class="w-full pl-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                  <option value="day" @selected( ($filterInputs['grouping'] ?? 'day') == 'day')>Day</option>
                  <option value="week" @selected( ($filterInputs['grouping'] ?? 'day') == 'week')>Week</option>
                  <option value="month" @selected( ($filterInputs['grouping'] ?? 'day') == 'month')>Month</option>
                </select>
              </div>

            </div>
          </div>
          
          {{-- Custom Date Range (hidden by default) --}}
          <div id="custom_dates_container" class="p-6 pt-0 {{ ($filterInputs['filter_timespan'] ?? '30d') == 'custom' ? '' : 'hidden' }}">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="filter_start" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" name="filter_start" id="filter_start" value="{{ $filterInputs['filter_start'] ?? '' }}" class="w-full pl-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
              </div>
              <div>
                <label for="filter_end" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" name="filter_end" id="filter_end" value="{{ $filterInputs['filter_end'] ?? '' }}" class="w-full pl-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
              </div>
            </div>
          </div>

          {{-- Form Actions --}}
          <div class="px-6 pb-4 flex justify-end items-center gap-x-3">
              {{-- This button reloads the page to clear ALL state, which is correct --}}
            <button type="button" onclick="clearAllFilters()" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Clear Filters</button>
            <button type="submit" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700">Apply Filters</button>
          </div>
        </form>
      </div>


      {{-- 3. CONSUMPTION CHARTS --}}
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Consumption Trend --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-1">
              {{-- Title updates dynamically via JS --}}
            <h3 id="consumptionChartTitle" class="text-lg font-semibold text-gray-900">Dispensation Trend (Items)</h3>
            <div id="consumptionChartToggle" class="mt-2 sm:mt-0 flex items-center p-1 bg-gray-100 rounded-lg">
              <button data-type="line" class="chart-toggle active-toggle">
                <i class="fa-regular fa-chart-line text-sm"></i>
              </button>
              <button data-type="bar" class="chart-toggle">
                <i class="fa-regular fa-chart-bar text-sm"></i>
              </button>
            </div>
          </div>
          {{-- Subtitle for filters --}}
          <p id="consumptionChartSubtitle" class="text-sm text-gray-500 mb-4"></p>
          <div class="relative chart-container"> {{-- Constrained Height --}}
            <canvas id="consumptionChart"></canvas>
          </div>
        </div>

        {{-- Top Products --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
           <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-1">
            <h3 class="text-lg font-semibold text-gray-900">Top 10 Most Dispensed</h3>
            <div id="topProductsChartToggle" class="mt-2 sm:mt-0 flex items-center p-1 bg-gray-100 rounded-lg">
              <button data-type="bar" class="chart-toggle active-toggle">
                <i class="fa-regular fa-chart-bar text-sm"></i>
              </button>
              <button data-type="pie" class="chart-toggle">
                <i class="fa-regular fa-chart-pie text-sm"></i>
              </button>
            </div>
          </div>
           {{-- Subtitle for filters --}}
          <p id="topProductsChartSubtitle" class="text-sm text-gray-500 mb-1"></p>
          <p class="text-sm text-gray-500 mb-3">Click on a product (bar chart only) to drill-down</p>
          <div class="relative chart-container"> {{-- Constrained Height --}}
            <canvas id="topProductsChart"></canvas>
          </div>
        </div>
      </div>
      {{-- End Consumption Charts --}}


      {{-- 4. PATIENT CHARTS --}}
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Patients by Barangay (Stacked Bar) --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900 mb-1">Patients by Barangay & Category</h3>
          <p id="barangayChartSubtitle" class="text-sm text-gray-500 mb-4"></p>
          <div class="relative chart-container"> {{-- Constrained Height --}}
            <canvas id="barangayChart"></canvas>
          </div>
        </div>

        {{-- === NEW: Patient Visit Trend Chart === --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          {{-- Title updates dynamically --}}
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-1">
            <h3 id="patientVisitChartTitle" class="text-lg font-semibold text-gray-900">Patient Visit Trend</h3>
            
            {{-- === ADDED TOGGLE === --}}
            <div id="patientVisitChartToggle" class="mt-2 sm:mt-0 flex items-center p-1 bg-gray-100 rounded-lg">
              <button data-type="line" class="chart-toggle active-toggle">
                <i class="fa-regular fa-chart-line text-sm"></i>
              </button>
              <button data-type="bar" class="chart-toggle">
                <i class="fa-regular fa-chart-bar text-sm"></i>
              </button>
            </div>
            {{-- === END TOGGLE === --}}

          </div>
          <p id="patientVisitChartSubtitle" class="text-sm text-gray-500 mb-4"></p>
          <div class="relative chart-container"> {{-- Constrained Height --}}
            <canvas id="patientVisitChart"></canvas>
          </div>
        </div>
        {{-- === END NEW CHART === --}}
        
      </div>
      {{-- End Patient Charts --}}

      {{-- 5. NEW: SEASONAL & HOTSPOT ANALYSIS --}}
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        
        {{-- Product Seasonal Trend --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            {{-- Added ID to form, removed action/method --}}
          <form id="seasonal-filter-form">
              {{-- Persist existing CHART filters --}}
             @foreach($filterInputs as $key => $value)
               @if(!in_array($key, ['seasonal_product_id', 'compare_product_id', 'forecast_days']) && $value)
                 <input type="hidden" name="{{ $key }}" value="{{ $value }}">
               @endif
             @endforeach
             {{-- Persist forecast filter too --}}
             <input type="hidden" name="forecast_days" value="{{ $inputs['forecast_days'] ?? 90 }}">
            
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Product Seasonal Trend</h3>
                <p class="text-sm text-gray-500">View & compare monthly dispensation (up to 3 yrs).</p>
              </div>
              <button type="submit" class="mt-2 sm:mt-0 px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700">Update Chart</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="seasonal_product_id" class="block text-sm font-medium text-gray-700 mb-1">Product 1</label>
                <select name="seasonal_product_id" id="seasonal_product_id" class="w-full pl-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                  @foreach($filter_products as $product)
                      {{-- Ensure $selectedSeasonalProduct is not null before accessing id --}}
                    <option value="{{ $product->id }}" @selected( ($filterInputs['seasonal_product_id'] ?? ($selectedSeasonalProduct->id ?? null)) == $product->id)>
                      {{ $product->generic_name }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div>
                <label for="compare_product_id" class="block text-sm font-medium text-gray-700 mb-1">Product 2 (Compare)</label>
                <select name="compare_product_id" id="compare_product_id" class="w-full pl-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                  <option value="">None</option>
                  @foreach($filter_products as $product)
                      {{-- Avoid comparing a product with itself --}}
                    @if( ($selectedSeasonalProduct->id ?? null) != $product->id) 
                      <option value="{{ $product->id }}" @selected( ($filterInputs['compare_product_id'] ?? '') == $product->id)>
                        {{ $product->generic_name }}
                      </option>
                    @endif
                  @endforeach
                </select>
              </div>
            </div>
          </form>
          
          <div id="seasonal-chart-anchor" class="relative chart-container mt-4"> {{-- Constrained Height --}}
            <canvas id="seasonalChart"></canvas>
          </div>
          
          <div class="mt-4 text-center">
            <button id="get-ai-analysis" class="inline-flex items-center px-4 py-2 text-sm text-white bg-purple-600 rounded-lg hover:bg-purple-700 disabled:opacity-50">
              <i class="fa-regular fa-stars mr-2"></i>
              <span id="ai-button-text">Get AI Analysis of this Trend</span>
            </button>
          </div>
        </div>

        {{-- Patient Dispensation Hotspots --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900 mb-1">Dispensation Hotspots</h3>
           <p id="hotspotsSubtitle" class="text-sm text-gray-500 mb-4"></p> {{-- Dynamic Subtitle --}}
           <div class="overflow-y-auto h-96 relative"> {{-- Added relative for loader --}}
            <table class="w-full text-sm text-left text-gray-500">
              <thead class="text-xs text-gray-700 uppercase bg-gray-100 sticky top-0">
                <tr>
                  <th scope="col" class="px-6 py-3">Barangay</th>
                  <th scope="col" class="px-6 py-3">Category</th>
                  <th scope="col" class="px-6 py-3 text-right">Total Items</th>
                  <th scope="col" class="px-6 py-3 text-right">Patients</th> {{-- NEW --}}
                </tr>
              </thead>
              <tbody id="hotspots-table-body"> {{-- Add ID for AJAX update --}}
                  {{-- Include the partial for initial load --}}
                  @include('admin.partials._hotspots_table_body', ['patientHotspots' => $patientHotspots])
              </tbody>
            </table>
          </div>
        </div>
      </div>
      {{-- End New Analytics --}}
    </main>
  </div>

  {{-- AI Response Modal --}}
  <div id="ai-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
      <div class="flex items-center justify-between p-4 border-b">
        <h3 class="text-lg font-semibold text-gray-900">AI Trend Analysis</h3>
        <button id="close-ai-modal" class="text-gray-400 hover:text-gray-600">
          <i class="fa-regular fa-xmark text-2xl"></i>
        </button>
      </div>
      <div class="p-6">
        <div id="ai-response-content" class="text-gray-700 space-y-3 prose prose-sm max-h-[70vh] overflow-y-auto"> {{-- Added scroll --}}
          {{-- AI Response will be injected here --}}
        </div>
      </div>
    </div>
  </div>


  {{-- Chart.js CDN --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-carbon/dist/chartjs-adapter-carbon.umd.min.js"></script>

  {{-- === ADDED: Zoom Plugin === --}}
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom/dist/chartjs-plugin-zoom.min.js"></script>
  
  {{-- === ADDED: Register Zoom Plugin === --}}
  <script>
    Chart.register(window.ChartZoom);
  </script>

  {{-- Add a new CSS style for the toggles --}}
  <style>
    .chart-toggle {
      padding: 0.25rem 0.75rem;
      border: none;
      background-color: transparent;
      color: #6b7280; /* gray-500 */
      border-radius: 0.5rem; /* rounded-lg */
      cursor: pointer;
      transition: all 0.2s;
    }
    .chart-toggle.active-toggle {
      background-color: #ffffff; /* white */
      color: #3b82f6; /* blue-500 */
      box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1); /* shadow-sm */
    }
     /* Fix for chart container height */
    .chart-container {
      position: relative;
      height: 20rem; /* h-80 */
      width: 100%;
    }
    @media (min-width: 1024px) { /* lg breakpoint */
      .chart-container {
          height: 24rem; /* h-96 */
      }
    }
    /* AI Modal max height */
    .prose-sm {
      max-width: none; /* Override prose max-width */
    }
     /* Styling for AJAX loader */
    .ajax-loader {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      border: 4px solid #f3f3f3; /* Light grey */
      border-top: 4px solid #3498db; /* Blue */
      border-radius: 50%;
      width: 30px;
      height: 30px;
      animation: spin 1s linear infinite;
      z-index: 10; /* Ensure it's above canvas */
      display: none; /* Hidden by default */
    }
    @keyframes spin {
      0% { transform: translate(-50%, -50%) rotate(0deg); }
      100% { transform: translate(-50%, -50%) rotate(360deg); }
    }

  </style>

  <script>
    // --- DATA FROM PHP ---
    const initialChartData = {
      consumption: {
        labels: @json($consumptionLabels),
        data: @json($consumptionData),
        productName: @json($drilldown_product_name) 
      },
      topProducts: {
        labels: @json($topProducts->keys()),
        data: @json($topProducts->values()),
        drilldown: @json($topProductsData->map(function($item) { return ['label' => $item->generic_name, 'id' => $item->product_id]; })),
      },
        // Initial Barangay Stacked Data
        barangay: {
          labels: @json($barangays), // All barangay names
          stackedData: @json($barangayStackedData) // Data grouped by category
        },
        // --- NEW: Patient Visit Trend Data ---
        patientVisit: {
          labels: @json($patientVisitLabels),
          data: @json($patientVisitData)
        },
      seasonal: {
        labels: @json($seasonalLabels),
        data: @json($seasonalData),
        productName: @json($selectedSeasonalProduct->generic_name ?? null),
        compareData: @json($compareData),
        compareName: @json($compareSeasonalProduct->generic_name ?? null),
      },
      // Initial Filter Labels
      filterLabels: {
          timespan: @json($filterTimespanLabel),
          barangay: @json($filterBarangayLabel),
          drilldownProduct: @json($drilldown_product_name)
      }
    };
    
    // Store chart instances
    window.myCharts = {};
    // Store original configurations
    window.originalChartConfigs = {};

    // --- CHART COLORS ---
    const categoryColors = { // Define colors for categories
          'Adult': 'rgb(249, 115, 22)', // orange-500
          'Child': 'rgb(14, 165, 233)', // sky-500
          'Senior': 'rgb(132, 204, 22)' // lime-500
    };
    const pieColors = Object.values(categoryColors); // Use consistent colors for pie chart
    
    const consumptionLineColor = 'rgb(34, 197, 94)'; // green-600
    const topProductsBarColor = 'rgba(59, 130, 246, 0.7)'; // blue-600 with alpha
    
    // --- NEW: Patient Visit Color ---
    const patientVisitColor = 'rgb(234, 179, 8)'; // yellow-500
    
    const seasonalColor1 = 'rgb(168, 85, 247)'; // purple-600
    const seasonalColor2 = 'rgb(234, 179, 8)'; // yellow-500 (same as patient visit, but used in different chart)
    
    // --- CSRF Token for AJAX ---
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');


    // --- HELPER FUNCTION: TOGGLE CHART TYPE ---
    function toggleChartType(chartId, newType) {
        const chart = window.myCharts[chartId];
        const originalConfig = window.originalChartConfigs[chartId];
        if (!chart || !originalConfig) { console.error('Chart or config not found for', chartId); return; }

        const newConfig = JSON.parse(JSON.stringify(originalConfig)); 
        newConfig.type = newType;
        newConfig.options.onClick = originalConfig.options.onClick; // Reset click handler

        if (newType === 'pie' || newType === 'doughnut') {
            newConfig.options.indexAxis = 'x'; 
            newConfig.options.scales = {}; 
            newConfig.options.plugins.legend.display = true;
            newConfig.options.onClick = null; // Disable click for pie

            if (newConfig.data.datasets && newConfig.data.datasets.length > 0) {
                 // Use consistent pie colors
                 const numLabels = newConfig.data.labels.length;
                newConfig.data.datasets[0].backgroundColor = Array.from({ length: numLabels }, (_, i) => pieColors[i % pieColors.length]);
                newConfig.data.datasets[0].borderColor = '#ffffff';
                newConfig.data.datasets[0].borderWidth = 1;
                newConfig.data.datasets[0].hoverOffset = 4;
                delete newConfig.data.datasets[0].tension; 
                delete newConfig.data.datasets[0].fill; 
            }
        } else { // Adjustments for bar/line
            newConfig.options.indexAxis = originalConfig.options.indexAxis || 'x'; 
            newConfig.options.scales = JSON.parse(JSON.stringify(originalConfig.options.scales)) || { x: { beginAtZero: true }, y: { beginAtZero: true } };
            newConfig.options.plugins.legend.display = false; 

            if (newConfig.data.datasets && newConfig.data.datasets.length > 0) {
                 const dataset = newConfig.data.datasets[0];
                 
                // === UPDATED: Handle colors for multiple charts ===
                 let lineColor, barColor, barBgColor;
                 if (chartId === 'consumptionChart') {
                     lineColor = consumptionLineColor;
                     barColor = 'rgba(34, 197, 94, 0.7)'; // green-600 alpha
                     barBgColor = 'rgba(34, 197, 94, 0.1)';
                 } else if (chartId === 'patientVisitChart') {
                     lineColor = patientVisitColor;
                     barColor = 'rgba(234, 179, 8, 0.7)'; // yellow-500 alpha
                     barBgColor = 'rgba(234, 179, 8, 0.1)';
                 } else { // Default for topProducts, etc.
                     lineColor = 'rgb(59, 130, 246)';
                     barColor = topProductsBarColor;
                     barBgColor = 'rgba(59, 130, 246, 0.1)';
                 }
                // === END UPDATE ===

                 dataset.borderWidth = 1;
                 if (newType === 'line') {
                     dataset.tension = 0.3;
                     dataset.fill = true;
                     dataset.borderColor = lineColor;
                     dataset.backgroundColor = barBgColor;
                 } else { // 'bar'
                     dataset.tension = 0;
                     dataset.fill = false;
                     dataset.borderColor = lineColor; // Use line color for border
                     dataset.backgroundColor = barColor; // Use bar color for fill
                 }
                 delete dataset.hoverOffset;
            }
        }

        chart.destroy();
        const ctx = document.getElementById(chartId).getContext('2d');
        window.myCharts[chartId] = new Chart(ctx, newConfig);
    }
    
    // --- DRILL-DOWN FUNCTION (NOW AJAX) ---
    // --- DRILL-DOWN FUNCTION (NOW AJAX) ---
  async function handleDrillDown(productId) {
      showLoader('consumptionChart');
      showLoader('barangayChart');
      showLoader('patientVisitChart'); 
      showLoader('hotspots-table-body'); 
      document.getElementById('drilldown_product_id').value = productId; 
      
      const form = document.getElementById('dashboard-filter-form');
      const formData = new FormData(form);
      if (productId) {
          formData.set('drilldown_product_id', productId); 
      } else {
          formData.delete('drilldown_product_id'); // Ensure it's not sent if null
      }
      const queryString = new URLSearchParams(formData).toString();
      const url = `${form.action}?${queryString}`;

    try {
          const response = await fetch(url, {
              method: 'GET',
              headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          });

          if (!response.ok) {
               const errorData = await response.json();
               throw new Error(errorData.message || `Server Error: ${response.status}`);
          }
          const data = await response.json();

          // Update Consumption Chart
          if (window.myCharts.consumptionChart) {
              window.myCharts.consumptionChart.data.labels = data.consumptionLabels;
              window.myCharts.consumptionChart.data.datasets[0].data = data.consumptionData;
              
              const title = data.drilldownProductName ? `Dispensation Trend for ${data.drilldownProductName} (Items)` : 'Dispensation Trend (Items)';
              document.getElementById('consumptionChartTitle').textContent = title; // Update h3 title
              
              updateChartSubtitle('consumptionChartSubtitle', data.filterTimespanLabel, data.filterBarangayLabel, data.drilldownProductName);
              window.myCharts.consumptionChart.update();
          }

          // Update Barangay Chart (Stacked)
          if (window.myCharts.barangayChart) {
               // --- FIX HERE ---
               window.myCharts.barangayChart.data.labels = data.barangay.labels; // Was data.barangayLabels
               const stackedData = data.barangay.stackedData; // Was data.barangayStackedData
               const categories = Object.keys(stackedData);
               
               // Rebuild datasets
               window.myCharts.barangayChart.data.datasets = categories.map(category => ({
                   label: category,
                   data: stackedData[category],
                   backgroundColor: categoryColors[category] || '#cccccc'
               }));
               // --- END FIX ---

               updateChartSubtitle('barangayChartSubtitle', data.filterTimespanLabel, data.filterBarangayLabel, data.drilldownProductName);
               window.myCharts.barangayChart.update();
          }

          // Update Patient Visit Trend Chart
          if (window.myCharts.patientVisitChart) {
              // --- FIX HERE ---
              window.myCharts.patientVisitChart.data.labels = data.patientVisit.labels; // Was data.patientVisitLabels
              window.myCharts.patientVisitChart.data.datasets[0].data = data.patientVisit.data; // Was data.patientVisitData
              // --- END FIX ---
              
              // Update title dynamically
              let title = 'Patient Visit Trend';
              if (data.filterBarangayLabel !== 'All Barangays') {
                  title += ` in ${data.filterBarangayLabel}`;
              }
              document.getElementById('patientVisitChartTitle').textContent = title; // Update h3 title
              
              updateChartSubtitle('patientVisitChartSubtitle', data.filterTimespanLabel, data.filterBarangayLabel, data.drilldownProductName);
              window.myCharts.patientVisitChart.update();
          }
          
          // Update Hotspots Table Body
          document.getElementById('hotspots-table-body').innerHTML = data.hotspotsHtml;
          updateChartSubtitle('hotspotsSubtitle', data.filterTimespanLabel, data.filterBarangayLabel, data.drilldownProductName);
          
          // Update Drilldown Indicator Bar
          updateDrilldownIndicator(data.drilldownProductName);
          
           // Update filter labels stored in JS
            initialChartData.filterLabels.timespan = data.filterTimespanLabel;
            initialChartData.filterLabels.barangay = data.filterBarangayLabel;
            initialChartData.filterLabels.drilldownProduct = data.drilldownProductName;

           // Update URL
           window.history.pushState({}, '', url);

    } catch (error) {
          console.error("Drilldown failed:", error);
          alert(`Could not update charts: ${error.message}`);
    } finally {
           hideLoader('consumptionChart');
           hideLoader('barangayChart');
           hideLoader('patientVisitChart'); 
           hideLoader('hotspots-table-body');
    }
  }
    
    // --- Helper to show/hide loader ---
    function showLoader(elementId) {
        let loader = document.getElementById(`${elementId}-loader`);
        let parentContainer = document.getElementById(elementId)?.parentNode;

        // Special case for table body
        if(elementId === 'hotspots-table-body' || elementId === 'forecast-table-body') {
            parentContainer = document.getElementById(elementId).closest('.overflow-y-auto');
        }

        if (!parentContainer) return;

        if (!loader) {
            loader = document.createElement('div');
            loader.id = `${elementId}-loader`;
            loader.className = 'ajax-loader';
            parentContainer.appendChild(loader);
        }
        loader.style.display = 'block';
        
        // Optionally hide the content while loading
        if (document.getElementById(elementId)?.tagName === 'CANVAS') {
            document.getElementById(elementId).style.opacity = '0.3';
        } else if (elementId.includes('-table-body')) {
             document.getElementById(elementId).style.opacity = '0.3';
        }
    }

    function hideLoader(elementId) {
        const loader = document.getElementById(`${elementId}-loader`);
        if (loader) {
            loader.style.display = 'none';
        }
        // Restore opacity
        const contentElement = document.getElementById(elementId);
        if (contentElement) {
             contentElement.style.opacity = '1';
        }
    }
      
    // --- Helper to update drilldown indicator ---
    function updateDrilldownIndicator(productName) {
        let indicator = document.querySelector('.drilldown-indicator');
        
        if (indicator && productName) {
            document.getElementById('drilldown-indicator-name').textContent = productName;
            indicator.style.display = 'flex'; // Ensure it's visible
        } else if (indicator && !productName) {
            indicator.style.display = 'none'; // Hide if no product name
        }
    }
      
    // --- Function to clear drilldown via AJAX ---
    async function clearDrilldown() {
        document.getElementById('drilldown_product_id').value = ''; // Clear hidden input
        // Use the same AJAX logic as handleDrillDown, but with a null product ID
        await handleDrillDown(null); 
        // Hide the indicator bar
        const indicator = document.querySelector('.drilldown-indicator');
        if (indicator) indicator.style.display = 'none';
    }
      
    // --- Function to clear ALL filters (including drilldown) and reload ---
    function clearAllFilters() {
        // Construct base URL without any query parameters
        const baseUrl = window.location.origin + window.location.pathname;
        window.location.href = baseUrl; // Reload the page with default filters
    }
      
     // --- Helper function to update chart subtitles ---
    function updateChartSubtitle(elementId, timespan, barangay, drilldown) {
      const element = document.getElementById(elementId);
      if (element) {
          let subtitle = `Showing data for: ${timespan}`;
          if (barangay && barangay !== 'All Barangays') {
              subtitle += `, ${barangay}`;
          }
          if (drilldown) {
              subtitle += `, Product: ${drilldown}`;
          }
          element.textContent = subtitle + '.';
      }
    }

    // --- === NEW: AJAX FOR FORECAST FILTER === ---
    async function handleForecastFilterUpdate() {
        const form = document.getElementById('forecast-filter-form');
        const formData = new FormData(form);
        formData.append('ajax_update', 'forecast'); // Signal to backend
        
        const queryString = new URLSearchParams(formData).toString();
        const url = `${form.action}?${queryString}`;

        showLoader('forecast-table-body');

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json(); // Expecting { forecastHtml: '...' }
            
            if (data.forecastHtml) {
                document.getElementById('forecast-table-body').innerHTML = data.forecastHtml;
                // Persist the new forecast_days value in the *main* filter form
                document.querySelector('#dashboard-filter-form input[name="forecast_days"]').value = formData.get('forecast_days');
                // Update URL
                window.history.pushState({}, '', url);
            } else {
                throw new Error('Invalid JSON response from server');
            }
        } catch (error) {
            console.error('Forecast update failed:', error);
            alert('Could not update forecast table.');
        } finally {
            hideLoader('forecast-table-body');
        }
    }

    // --- === NEW: AJAX FOR MAIN CHART FILTERS === ---
    async function handleMainFilterSubmit() {
        const form = document.getElementById('dashboard-filter-form');
        const formData = new FormData(form);
        formData.append('ajax_update', 'main_charts'); // Signal to backend
        
        const queryString = new URLSearchParams(formData).toString();
        const url = `${form.action}?${queryString}`;

        // Show all relevant loaders
        showLoader('consumptionChart');
        showLoader('topProductsChart');
        showLoader('barangayChart');
        showLoader('patientVisitChart');
        showLoader('hotspots-table-body');

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json(); // Expecting full data blob

            // 1. Update Consumption Chart
            if (window.myCharts.consumptionChart) {
                window.myCharts.consumptionChart.data.labels = data.consumptionLabels;
                window.myCharts.consumptionChart.data.datasets[0].data = data.consumptionData;
                const cTitle = data.drilldownProductName ? `Dispensation Trend for ${data.drilldownProductName} (Items)` : 'Dispensation Trend (Items)';
                document.getElementById('consumptionChartTitle').textContent = cTitle;
                updateChartSubtitle('consumptionChartSubtitle', data.filterTimespanLabel, data.filterBarangayLabel, data.drilldownProductName);
                window.myCharts.consumptionChart.update();
            }

            // 2. Update Top Products Chart
            if (window.myCharts.topProductsChart) {
                window.myCharts.topProductsChart.data.labels = data.topProducts.labels;
                window.myCharts.topProductsChart.data.datasets[0].data = data.topProducts.data;
                // IMPORTANT: Update the drilldown data source
                initialChartData.topProducts.drilldown = data.topProducts.drilldown;
                updateChartSubtitle('topProductsChartSubtitle', data.filterTimespanLabel, data.filterBarangayLabel, null);
                window.myCharts.topProductsChart.update();
            }

            // 3. Update Barangay Chart
            if (window.myCharts.barangayChart) {
                window.myCharts.barangayChart.data.labels = data.barangay.labels;
                const stackedData = data.barangay.stackedData;
                const categories = Object.keys(stackedData);
                
                // Rebuild datasets
                window.myCharts.barangayChart.data.datasets = categories.map(category => ({
                    label: category,
                    data: stackedData[category],
                    backgroundColor: categoryColors[category] || '#cccccc'
                }));
                
                updateChartSubtitle('barangayChartSubtitle', data.filterTimespanLabel, data.filterBarangayLabel, data.drilldownProductName);
                window.myCharts.barangayChart.update();
            }

            // 4. Update Patient Visit Chart
            if (window.myCharts.patientVisitChart) {
                window.myCharts.patientVisitChart.data.labels = data.patientVisit.labels;
                window.myCharts.patientVisitChart.data.datasets[0].data = data.patientVisit.data;
                let pvTitle = 'Patient Visit Trend';
                if (data.filterBarangayLabel !== 'All Barangays') {
                    pvTitle += ` in ${data.filterBarangayLabel}`;
                }
                document.getElementById('patientVisitChartTitle').textContent = pvTitle;
                updateChartSubtitle('patientVisitChartSubtitle', data.filterTimespanLabel, data.filterBarangayLabel, data.drilldownProductName);
                window.myCharts.patientVisitChart.update();
            }
            
            // 5. Update Hotspots Table
            document.getElementById('hotspots-table-body').innerHTML = data.hotspotsHtml;
            updateChartSubtitle('hotspotsSubtitle', data.filterTimespanLabel, data.filterBarangayLabel, data.drilldownProductName);

            // Update global filter labels
            initialChartData.filterLabels.timespan = data.filterTimespanLabel;
            initialChartData.filterLabels.barangay = data.filterBarangayLabel;
            initialChartData.filterLabels.drilldownProduct = data.drilldownProductName;

            // Update URL
            window.history.pushState({}, '', url);

        } catch (error) {
            console.error('Main charts update failed:', error);
            alert('Could not update charts.');
        } finally {
            // Hide all loaders
            hideLoader('consumptionChart');
            hideLoader('topProductsChart');
            hideLoader('barangayChart');
            hideLoader('patientVisitChart');
            hideLoader('hotspots-table-body');
        }
    }
    
    // --- === NEW: AJAX FOR SEASONAL CHART FILTER === ---
    async function handleSeasonalFilterSubmit() {
        const form = document.getElementById('seasonal-filter-form');
        const formData = new FormData(form);
        formData.append('ajax_update', 'seasonal'); // Signal to backend

        // Also merge main chart filters to persist state
        const mainFormData = new FormData(document.getElementById('dashboard-filter-form'));
        mainFormData.forEach((value, key) => {
            if (!formData.has(key)) {
                formData.append(key, value);
            }
        });
        
        const queryString = new URLSearchParams(formData).toString();
        const url = `{{ route('admin.dashboard') }}?${queryString}#seasonal-chart-anchor`;

        showLoader('seasonalChart');

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json(); // Expecting { seasonal: {...} }

            // Update global JS data
            initialChartData.seasonal = data.seasonal;

            const seasonalCtx = document.getElementById('seasonalChart').getContext('2d');
            
            // Destroy old chart if it exists
            if (window.myCharts.seasonalChart) {
                window.myCharts.seasonalChart.destroy();
            }

            // Create new config
            const seasonalConfig = {
                type: 'line',
                data: {
                    labels: data.seasonal.labels,
                    datasets: []
                },
                options: window.originalChartConfigs.seasonalChart.options // Reuse original options
            };

            // Add Product 1 dataset
            if (data.seasonal.productName && data.seasonal.data) {
                seasonalConfig.data.datasets.push({
                    label: data.seasonal.productName,
                    data: data.seasonal.data,
                    borderColor: seasonalColor1,
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    fill: true,
                    tension: 0.1
                });
            }

            // Add Product 2 dataset
            if (data.seasonal.compareName && data.seasonal.compareData && data.seasonal.compareData.length > 0) {
                seasonalConfig.data.datasets.push({
                    label: data.seasonal.compareName,
                    data: data.seasonal.compareData,
                    borderColor: seasonalColor2,
                    backgroundColor: 'rgba(234, 179, 8, 0.1)',
                    fill: true,
                    tension: 0.1
                });
            }

            // Initialize new chart
            if (seasonalConfig.data.datasets.length > 0) {
                 window.myCharts.seasonalChart = new Chart(seasonalCtx, seasonalConfig);
                 // Store new config as "original"
                 window.originalChartConfigs.seasonalChart = JSON.parse(JSON.stringify(seasonalConfig));
            } else {
                 // Draw "No data" message
                 const ctx = seasonalCtx.getContext('2d'); 
                 ctx.clearRect(0, 0, seasonalCtx.canvas.width, seasonalCtx.canvas.height); // Clear old chart
                 ctx.font = "16px Arial"; ctx.fillStyle = "#aaa"; ctx.textAlign = "center";
                 ctx.fillText("No seasonal data for selected product(s)", seasonalCtx.canvas.width / 2, seasonalCtx.canvas.height / 2);
            }

            // Update AI Button state
            const aiButton = document.getElementById('get-ai-analysis');
            const aiButtonText = document.getElementById('ai-button-text');
            if (!data.seasonal.productName || !data.seasonal.data || data.seasonal.data.length === 0) {
                aiButton.disabled = true;
                aiButtonText.textContent = (!data.seasonal.productName) ? 'Select a Product First' : 'No Data to Analyze';
            } else {
                aiButton.disabled = false;
                aiButtonText.textContent = 'Get AI Analysis of this Trend';
            }

            // Update URL
            window.history.pushState({}, '', url);

        } catch (error) {
            console.error('Seasonal chart update failed:', error);
            alert('Could not update seasonal chart.');
        } finally {
            hideLoader('seasonalChart');
        }
    }


    document.addEventListener('DOMContentLoaded', function () {
    
      // Filter toggle logic for custom dates
      const timespanSelect = document.getElementById('filter_timespan');
      const customDates = document.getElementById('custom_dates_container');
      timespanSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
          customDates.classList.remove('hidden');
        } else {
          customDates.classList.add('hidden');
        }
      });
      
      
      // --- CHART INITIALIZATION ---
      
      // 1. Consumption Chart (Line)
      const consumptionCtx = document.getElementById('consumptionChart').getContext('2d');
      const consumptionConfig = {
        type: 'line', 
        data: {
          labels: initialChartData.consumption.labels,
          datasets: [{
            label: 'Items Dispensed',
            data: initialChartData.consumption.data,
            borderColor: consumptionLineColor,
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            fill: true,
            tension: 0.3
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { 
            legend: { display: false },
            title: {
                 display: false, // Title is now the H3 tag
                 text: '...', // Kept for config, but H3 is used
                 padding: { bottom: 5 } 
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            },
            // --- ADDED: Zoom ---
            zoom: {
                pan: {
                    enabled: true,
                    mode: 'x',
                },
                zoom: {
                    wheel: { enabled: true },
                    pinch: { enabled: true },
                    mode: 'x',
                }
            }
            // --- END Zoom ---
          },
          scales: { 
              y: { beginAtZero: true, title: { display: true, text: 'Quantity' } }, 
              x: { ticks: { autoSkip: true, maxRotation: 0 } } 
          }, 
          animation: { duration: 1000, easing: 'easeOutQuad' }
        }
      };
      // Set initial H3 title
      document.getElementById('consumptionChartTitle').textContent = initialChartData.consumption.productName ? `Dispensation Trend for ${initialChartData.consumption.productName} (Items)` : 'Dispensation Trend (Items)';
      window.myCharts.consumptionChart = new Chart(consumptionCtx, consumptionConfig);
      window.originalChartConfigs.consumptionChart = JSON.parse(JSON.stringify(consumptionConfig)); 

      // 2. Top Products Chart (Bar)
      const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
      const topProductsConfig = {
        type: 'bar', 
        data: {
          labels: initialChartData.topProducts.labels,
          datasets: [{
            label: 'Total Dispensed',
            data: initialChartData.topProducts.data,
            backgroundColor: topProductsBarColor,
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          scales: { x: { beginAtZero: true, title: { display: true, text: 'Total Quantity Dispensed' } }, y: { ticks: { autoSkip: false } } }, 
          plugins: { 
              legend: { display: false },
              title: { display: false } // No title needed here usually
          },
          animation: { duration: 1000, easing: 'easeOutQuad' },
            onClick: (evt) => {
              if (window.myCharts.topProductsChart?.config.type === 'bar') { // Add null check
                  const points = window.myCharts.topProductsChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                  if (points.length) {
                      const firstPoint = points[0];
                      const index = firstPoint.index;
                      const clickedItem = initialChartData.topProducts.drilldown[index];
                      if (clickedItem && clickedItem.id) {
                          handleDrillDown(clickedItem.id); 
                      }
                  }
              }
            }
        }
      };
      window.myCharts.topProductsChart = new Chart(topProductsCtx, topProductsConfig);
      window.originalChartConfigs.topProductsChart = JSON.parse(JSON.stringify(topProductsConfig)); 


      // 3. Barangay Chart (STACKED Bar)
      const barangayCtx = document.getElementById('barangayChart').getContext('2d');
        const barangayConfig = {
          type: 'bar',
          data: {
            labels: initialChartData.barangay.labels, // Barangay names
            datasets: Object.keys(initialChartData.barangay.stackedData).map(category => ({ // Create dataset per category
              label: category,
              data: initialChartData.barangay.stackedData[category],
              backgroundColor: categoryColors[category] || '#cccccc', // Use defined category colors
            }))
          },
          options: {
            indexAxis: 'x', 
            responsive: true,
            maintainAspectRatio: false,
            scales: { 
              y: { 
                  beginAtZero: true, 
                  stacked: true, // Enable stacking
                  title: { display: true, text: 'Number of Patients' } 
              }, 
              x: { 
                  stacked: true, // Enable stacking
                  ticks: { autoSkip: false } // Show all barangay labels
              } 
            },
            plugins: { 
              legend: { 
                  display: true, // Show legend for categories
                  position: 'bottom' 
              },
               title: { display: false } 
            },
             animation: { duration: 1000, easing: 'easeOutQuad' }
          }
        };
      window.myCharts.barangayChart = new Chart(barangayCtx, barangayConfig);
      window.originalChartConfigs.barangayChart = JSON.parse(JSON.stringify(barangayConfig)); 

      // --- 4. NEW: Patient Visit Trend Chart (Line) ---
      const patientVisitCtx = document.getElementById('patientVisitChart').getContext('2d');
      const patientVisitConfig = {
        type: 'line',
        data: {
          labels: initialChartData.patientVisit.labels,
          datasets: [
            {
                label: 'Patient Visits',
                data: initialChartData.patientVisit.data,
                borderColor: patientVisitColor,
                backgroundColor: 'rgba(234, 179, 8, 0.1)',
                fill: true,
                tension: 0.3
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { 
            legend: { display: false },
             title: { 
                 display: false, // Title is now the H3 tag
                 text: '...',
                 padding: { bottom: 5 }
             },
             tooltip: {
                mode: 'index',
                intersect: false,
             },
             // --- ADDED: Zoom ---
             zoom: {
                pan: {
                    enabled: true,
                    mode: 'x',
                },
                zoom: {
                    wheel: { enabled: true },
                    pinch: { enabled: true },
                    mode: 'x',
                }
             }
             // --- END Zoom ---
          },
          scales: { 
              y: { beginAtZero: true, title: { display: true, text: 'Number of Patients' } }, 
              x: { ticks: { autoSkip: true, maxRotation: 0 } } 
          }, 
          animation: { duration: 1000, easing: 'easeOutQuad' }
        }
      };
      // Set initial H3 title
      document.getElementById('patientVisitChartTitle').textContent = `Patient Visit Trend ${initialChartData.filterLabels.barangay !== 'All Barangays' ? 'in ' + initialChartData.filterLabels.barangay : ''}`;
      window.myCharts.patientVisitChart = new Chart(patientVisitCtx, patientVisitConfig);
      window.originalChartConfigs.patientVisitChart = JSON.parse(JSON.stringify(patientVisitConfig));


      // 5. Seasonal Chart
      const seasonalCtx = document.getElementById('seasonalChart').getContext('2d');
      const seasonalConfig = {
        type: 'line',
        data: {
          labels: initialChartData.seasonal.labels,
          datasets: [ /* Datasets added below */ ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, title: { display: true, text: 'Quantity Dispensed' } }, x: { ticks: { autoSkip: true, maxRotation: 0 } } },
          plugins: { 
            legend: { display: true },
            tooltip: {
                mode: 'index',
                intersect: false,
            },
            // --- ADDED: Zoom ---
            zoom: {
                pan: {
                    enabled: true,
                    mode: 'x',
                },
                zoom: {
                    wheel: { enabled: true },
                    pinch: { enabled: true },
                    mode: 'x',
                }
            }
            // --- END Zoom ---
          }, 
           animation: { duration: 1000, easing: 'easeOutQuad' }
        }
      };
       // Add Product 1 dataset if it exists
       if (initialChartData.seasonal.productName && initialChartData.seasonal.data) {
           seasonalConfig.data.datasets.push({
             label: initialChartData.seasonal.productName,
             data: initialChartData.seasonal.data,
             borderColor: seasonalColor1,
             backgroundColor: 'rgba(168, 85, 247, 0.1)',
             fill: true,
             tension: 0.1
           });
       }
      // Only add comparison if data exists
      if (initialChartData.seasonal.compareName && initialChartData.seasonal.compareData && initialChartData.seasonal.compareData.length > 0) {
        seasonalConfig.data.datasets.push({
          label: initialChartData.seasonal.compareName,
          data: initialChartData.seasonal.compareData,
          borderColor: seasonalColor2,
          backgroundColor: 'rgba(234, 179, 8, 0.1)',
          fill: true,
          tension: 0.1
        });
      }
      // Only initialize chart if there's data to show
        if (seasonalConfig.data.datasets.length > 0) {
           window.myCharts.seasonalChart = new Chart(seasonalCtx, seasonalConfig);
           window.originalChartConfigs.seasonalChart = JSON.parse(JSON.stringify(seasonalConfig)); // Store original
        } else {
           const ctx = seasonalCtx.getContext('2d'); 
           ctx.font = "16px Arial"; ctx.fillStyle = "#aaa"; ctx.textAlign = "center";
           ctx.fillText("No seasonal data for selected product(s)", seasonalCtx.canvas.width / 2, seasonalCtx.canvas.height / 2);
        }
      
      // --- TOGGLE BUTTONS ---
      document.querySelectorAll('.chart-toggle').forEach(button => {
        button.addEventListener('click', (e) => {
          const btn = e.currentTarget;
          const newType = btn.dataset.type;
          const parent = btn.parentElement;
          
          // === UPDATED: To include new toggle ===
          let chartId;
          if (parent.id === 'consumptionChartToggle') {
              chartId = 'consumptionChart';
          } else if (parent.id === 'topProductsChartToggle') {
              chartId = 'topProductsChart';
          } else if (parent.id === 'patientVisitChartToggle') { // <-- ADDED
              chartId = 'patientVisitChart';
          }
          // === END UPDATE ===

          if (chartId) {
             parent.querySelectorAll('.chart-toggle').forEach(b => b.classList.remove('active-toggle'));
             btn.classList.add('active-toggle');
             toggleChartType(chartId, newType);
          }
        });
      });

      // --- AI ANALYSIS ---
      const aiButton = document.getElementById('get-ai-analysis');
      const aiButtonText = document.getElementById('ai-button-text');
      const aiModal = document.getElementById('ai-modal');
      const closeAiModal = document.getElementById('close-ai-modal');
      const aiResponseContent = document.getElementById('ai-response-content');

      if (!initialChartData.seasonal.productName || !initialChartData.seasonal.data || initialChartData.seasonal.data.length === 0) {
        aiButton.disabled = true;
        aiButtonText.textContent = (!initialChartData.seasonal.productName) ? 'Select a Product First' : 'No Data to Analyze';
      }

      aiButton.addEventListener('click', async () => {
         // Double check if button should be enabled
         if (!initialChartData.seasonal.productName || !initialChartData.seasonal.data || initialChartData.seasonal.data.length === 0) {
             return; // Prevent running if disabled
         }
         
        aiButton.disabled = true;
        aiButtonText.textContent = 'Analyzing...';
        aiResponseContent.innerHTML = '<p>Loading analysis...</p>';
        aiModal.classList.remove('hidden');

        try {
          // Ensure data arrays exist before mapping
          const dataForBackend = initialChartData.seasonal.labels && initialChartData.seasonal.data ? initialChartData.seasonal.labels.map((label, index) => {
            return { label: label, data: initialChartData.seasonal.data[index] ?? 0 };
          }) : [];
          
          const compareForBackend = initialChartData.seasonal.compareName && initialChartData.seasonal.labels && initialChartData.seasonal.compareData ? initialChartData.seasonal.labels.map((label, index) => {
             // Ensure compareData has a value for the index, default to 0
             const compareValue = (initialChartData.seasonal.compareData && index < initialChartData.seasonal.compareData.length) ? initialChartData.seasonal.compareData[index] : 0;
            return { label: label, data: compareValue };
          }) : [];
          
          const payload = {
            product_name: initialChartData.seasonal.productName,
            seasonal_data: dataForBackend,
            compare_product_name: initialChartData.seasonal.compareName,
            compare_data: compareForBackend
            // No need to send _token in body for POST via fetch if using X-CSRF-TOKEN header
          };
          
          const response = await fetch("{{ route('admin.ai.analysis') }}", {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken, // Standard header for Laravel CSRF
              'Accept': 'application/json' // Expect JSON response
            },
            body: JSON.stringify(payload)
          });

          const result = await response.json();

          if (!response.ok) {
            // Log the detailed error from the server
             console.error('AI Analysis Error:', result);
            throw new Error(result.error || `Server Error: ${response.status} ${response.statusText}`);
          }

          if (result.analysis) {
            aiResponseContent.innerHTML = result.analysis;
          } else {
             console.error('AI Analysis Error: No analysis content in response', result);
             throw new Error('No valid analysis received from the server.');
          }

        } catch (error) {
           console.error('AI Analysis Fetch Failed:', error);
          aiResponseContent.innerHTML = `<p class="text-red-600 font-semibold">Sorry, the analysis could not be completed.</p><p class="text-sm text-gray-500 mt-2">${error.message}</p>`;
        } finally {
          aiButton.disabled = false;
          aiButtonText.textContent = 'Get AI Analysis of this Trend';
           // Re-disable if still no product selected
           if (!initialChartData.seasonal.productName || !initialChartData.seasonal.data || initialChartData.seasonal.data.length === 0) {
               aiButton.disabled = true;
               aiButtonText.textContent = (!initialChartData.seasonal.productName) ? 'Select a Product First' : 'No Data to Analyze';
           }
        }
      });

      closeAiModal.addEventListener('click', () => {
        aiModal.classList.add('hidden');
      });
      
       // --- Initial Drilldown Indicator Update on page load ---
        updateDrilldownIndicator(initialChartData.consumption.productName);
       
        // --- Initial Subtitle Updates ---
        updateChartSubtitle('consumptionChartSubtitle', initialChartData.filterLabels.timespan, initialChartData.filterLabels.barangay, initialChartData.filterLabels.drilldownProduct);
        updateChartSubtitle('topProductsChartSubtitle', initialChartData.filterLabels.timespan, initialChartData.filterLabels.barangay, null); // Top products ignores drilldown
        updateChartSubtitle('barangayChartSubtitle', initialChartData.filterLabels.timespan, initialChartData.filterLabels.barangay, initialChartData.filterLabels.drilldownProduct);
        updateChartSubtitle('patientVisitChartSubtitle', initialChartData.filterLabels.timespan, initialChartData.filterLabels.barangay, initialChartData.filterLabels.drilldownProduct); // <-- CHANGED
        updateChartSubtitle('hotspotsSubtitle', initialChartData.filterLabels.timespan, initialChartData.filterLabels.barangay, initialChartData.filterLabels.drilldownProduct);

        // --- Event Listener for AJAX Clear Drilldown ---
         const clearAjaxButton = document.getElementById('clear-drilldown-ajax');
         if(clearAjaxButton) {
             clearAjaxButton.addEventListener('click', (e) => {
                 e.preventDefault();
                 clearDrilldown();
             });
         }

        // --- === NEW: ADD AJAX EVENT LISTENERS === ---
        
        // 1. Forecast Filter
        document.getElementById('forecast_days_select').addEventListener('change', handleForecastFilterUpdate);

        // 2. Main Chart Filters
        document.getElementById('dashboard-filter-form').addEventListener('submit', (e) => {
            e.preventDefault(); // Stop form submission
            handleMainFilterSubmit();
        });

        // 3. Seasonal Filter
        document.getElementById('seasonal-filter-form').addEventListener('submit', (e) => {
            e.preventDefault(); // Stop form submission
            handleSeasonalFilterSubmit();
        });

    });
  </script>

</body>
</html>
</x-app-layout>