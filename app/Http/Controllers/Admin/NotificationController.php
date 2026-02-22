<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationManagementService $notificationManagementService
    ) {
    }

    public function index()
    {
        $user = Auth::user();
        abort_if(!$user, 401);

        return view('admin.notifications.index', $this->notificationManagementService->getIndexData($user));
    }

    public function markAsRead(string $id)
    {
        $user = Auth::user();
        abort_if(!$user, 401);

        $this->notificationManagementService->markAsRead($user, $id);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        abort_if(!$user, 401);

        $this->notificationManagementService->markAllAsRead($user);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function preferences()
    {
        return view(
            'admin.notifications.preferences',
            $this->notificationManagementService->getPreferenceData((int) Auth::id())
        );
    }

    public function updatePreferences(Request $request)
    {
        $this->notificationManagementService->updatePreferences(
            (int) Auth::id(),
            $request->input('preferences', [])
        );

        return back()->with('success', 'Notification preferences updated.');
    }
}

