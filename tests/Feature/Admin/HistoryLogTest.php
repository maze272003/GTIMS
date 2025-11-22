<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\UserLevel;
use App\Models\Branch;
use App\Models\HistoryLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryLogTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $level = UserLevel::create(['name' => 'admin']);
        $branch = Branch::create(['name' => 'Head Office']);

        $this->adminUser = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
            'password' => bcrypt('password'),
        ]);
    }

    public function test_history_log_page_can_be_rendered()
    {
        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.historylog'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.historylog');
        $response->assertViewHas(['historyLogs', 'actions', 'users']);
    }

    public function test_search_functionality_works()
    {
        // Arrange
        HistoryLog::create(['action' => 'Login', 'description' => 'User logged in', 'user_name' => 'John Doe']);
        HistoryLog::create(['action' => 'Delete', 'description' => 'Deleted item', 'user_name' => 'Jane Smith']);

        // Act
        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.historylog', ['search' => 'John']));

        $response->assertOk();

        // FIX: Check View Data Collection instead of HTML
        // Kasi si "Jane Smith" ay nasa dropdown filter HTML pa rin kahit wala sa table.
        $logs = $response->viewData('historyLogs');

        $this->assertTrue($logs->contains('user_name', 'John Doe'), 'Results should contain John Doe');
        $this->assertFalse($logs->contains('user_name', 'Jane Smith'), 'Results should NOT contain Jane Smith');
    }

    public function test_filter_by_action_works()
    {
        HistoryLog::create(['action' => 'Update', 'user_name' => 'A', 'description' => 'desc']);
        HistoryLog::create(['action' => 'Create', 'user_name' => 'B', 'description' => 'desc']);

        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.historylog', ['action' => 'Update']));

        $response->assertOk();
        $data = $response->viewData('historyLogs');
        
        $this->assertEquals(1, $data->count());
        $this->assertEquals('Update', $data->first()->action);
    }

    public function test_filter_by_user_works()
    {
        HistoryLog::create(['user_name' => 'Maria', 'action' => 'A', 'description' => 'desc']);
        HistoryLog::create(['user_name' => 'Pedro', 'action' => 'B', 'description' => 'desc']);

        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.historylog', ['user' => 'Pedro']));

        $response->assertOk();
        
        // Check Data
        $logs = $response->viewData('historyLogs');
        $this->assertTrue($logs->contains('user_name', 'Pedro'));
        $this->assertFalse($logs->contains('user_name', 'Maria'));
    }

    public function test_filter_by_date_range_works()
    {
        // Arrange: Gumamit ng explicit dates para iwas sa timezone issues ng SQLite
        HistoryLog::create([
            'action' => 'Old Log', 
            'user_name' => 'A', 
            'description' => 'desc',
            'created_at' => Carbon::parse('2023-01-01 10:00:00') // Malayong past
        ]);

        HistoryLog::create([
            'action' => 'New Log', 
            'user_name' => 'B', 
            'description' => 'desc',
            'created_at' => Carbon::parse('2023-10-01 10:00:00') // Target date
        ]);

        // Act: Filter para lang sa October 1, 2023
        $from = '2023-10-01';
        $to = '2023-10-01';

        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.historylog', ['from' => $from, 'to' => $to]));

        $response->assertOk();

        // FIX: Check Data Collection
        $logs = $response->viewData('historyLogs');
        
        $this->assertTrue($logs->contains('action', 'New Log'), 'Should contain New Log');
        $this->assertFalse($logs->contains('action', 'Old Log'), 'Should NOT contain Old Log');
    }

    public function test_ajax_request_returns_partial_view()
    {
        HistoryLog::create(['action' => 'Ajax Test', 'user_name' => 'Bot', 'description' => 'desc']);

        $response = $this->actingAs($this->adminUser)
                         ->get(route('admin.historylog'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $response->assertViewIs('admin.partials._history_table');
    }
}


// controller sample
// namespace App\Http\Controllers\AdminController;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use App\Models\HistoryLog;
// use Carbon\Carbon;
// use Illuminate\Support\Facades\DB;

// class HistorylogController extends Controller
// {
//     public function showhistorylog(Request $request)
//     {
//         $search = $request->input('search', '');
//         $action = $request->input('action', '');
//         $user = $request->input('user', '');
//         $from = $request->input('from', '');
//         $to = $request->input('to', '');

//         $sort = $request->input('sort', 'desc');
//         $query = HistoryLog::query()->orderBy('created_at', $sort);

//         // === Search Filter ===
//         if (!empty($search)) {
//             $query->where(function ($q) use ($search) {
//                 $q->where('action', 'like', "%{$search}%")
//                   ->orWhere('description', 'like', "%{$search}%")
//                   ->orWhere('user_name', 'like', "%{$search}%");

//                 // FIX: SQLite Compatibility for Testing
//                 if (DB::connection()->getDriverName() !== 'sqlite') {
//                      $q->orWhereRaw("DATE_FORMAT(created_at, '%M %e, %Y %l:%i %p') LIKE ?", ["%{$search}%"])
//                        ->orWhereRaw("DATE_FORMAT(created_at, '%Y-%m-%d') LIKE ?", ["%{$search}%"]);
//                 } else {
//                     // Simple fallback for testing
//                     $q->orWhere('created_at', 'like', "%{$search}%");
//                 }
//             });
//         }

//         // === Action Filter ===
//         if (!empty($action)) {
//             $query->where('action', $action);
//         }

//         // === User Filter ===
//         if (!empty($user)) {
//             $query->where('user_name', $user);
//         }

//         // === Date Range Filter ===
//         if (!empty($from) && !empty($to)) {
//             $fromDate = Carbon::parse($from)->startOfDay();
//             $toDate = Carbon::parse($to)->endOfDay();
//             $query->whereBetween('created_at', [$fromDate, $toDate]);
//         } elseif (!empty($from)) {
//             $fromDate = Carbon::parse($from)->startOfDay();
//             $query->where('created_at', '>=', $fromDate);
//         } elseif (!empty($to)) {
//             $toDate = Carbon::parse($to)->endOfDay();
//             $query->where('created_at', '<=', $toDate);
//         }

//         $historyLogs = $query->paginate(20)->withQueryString();

//         // FIX: Return view object, NOT string render()
//         if ($request->ajax()) {
//             return view('admin.partials._history_table', compact('historyLogs'));
//         }
        
//         $actions = HistoryLog::select('action')->distinct()->pluck('action');
//         $users = HistoryLog::select('user_name')->distinct()->pluck('user_name');

//         return view('admin.historylog', compact('historyLogs', 'actions', 'users'));
//     }
// }