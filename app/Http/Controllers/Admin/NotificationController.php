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
        $notifications = Auth::user()->notifications()->paginate(20);
        $unreadCount = Auth::user()->unreadNotifications()->count();
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
        $types = ['low_stock', 'approval_needed', 'hold_expiry', 'request_status'];
        $preferences = NotificationPreference::where('user_id', Auth::id())->get()->keyBy('type');
        return view('admin.notifications.preferences', compact('types', 'preferences'));
    }

    public function updatePreferences(Request $request)
    {
        $types = ['low_stock', 'approval_needed', 'hold_expiry', 'request_status'];

        foreach ($types as $type) {
            NotificationPreference::updateOrCreate(
                ['user_id' => Auth::id(), 'type' => $type],
                [
                    'email_enabled' => $request->boolean("email_{$type}", false),
                    'in_app_enabled' => $request->boolean("in_app_{$type}", false),
                ]
            );
        }

        return back()->with('success', 'Notification preferences updated.');
    }
}
