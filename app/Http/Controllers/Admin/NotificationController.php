<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
{
    $user = Auth::user();
    abort_if(!$user, 401);

    $notifications = $user->notifications()->paginate(20);
    $unreadCount = $user->unreadNotifications()->count();

    return view('admin.notifications.index', compact('notifications', 'unreadCount'));
}


    public function markAsRead(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
        return back()->with('success', 'All notifications marked as read.');
    }

    public function preferences()
{
    $notificationTypes = [
        'low_stock',
        'approval_needed',
        'hold_expiry',
        'request_status',
    ];

    // Convert DB rows into an easy array: $preferences['low_stock']['email_enabled'] = true
    $preferences = NotificationPreference::where('user_id', Auth::id())
        ->get()
        ->keyBy('type')
        ->map(fn ($row) => [
            'email_enabled' => (bool) $row->email_enabled,
            'in_app_enabled' => (bool) $row->in_app_enabled,
        ])
        ->toArray();

    return view('admin.notifications.preferences', compact('notificationTypes', 'preferences'));
}

public function updatePreferences(Request $request)
{
    $notificationTypes = [
        'low_stock',
        'approval_needed',
        'hold_expiry',
        'request_status',
    ];

    $input = $request->input('preferences', []);

    foreach ($notificationTypes as $type) {
        $emailEnabled = (bool) data_get($input, "{$type}.email_enabled", false);
        $inAppEnabled = (bool) data_get($input, "{$type}.in_app_enabled", true); // default true if you want

        NotificationPreference::updateOrCreate(
            ['user_id' => Auth::id(), 'type' => $type],
            [
                'email_enabled' => $emailEnabled,
                'in_app_enabled' => $inAppEnabled,
            ]
        );
    }

    return back()->with('success', 'Notification preferences updated.');
}

}
