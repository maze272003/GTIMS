<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    public function index(Request $request)
    {
        $events = AuditEvent::with('user')
            ->when($request->action, fn($q, $a) => $q->where('action', $a))
            ->when($request->entity_type, fn($q, $t) => $q->where('entity_type', $t))
            ->when($request->user_id, fn($q, $u) => $q->where('user_id', $u))
            ->when($request->from, fn($q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($request->to, fn($q, $t) => $q->whereDate('created_at', '<=', $t))
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        $actions = AuditEvent::select('action')->distinct()->pluck('action');
        $entityTypes = AuditEvent::select('entity_type')->distinct()->pluck('entity_type');

        return view('admin.audit.index', compact('events', 'actions', 'entityTypes'));
    }

    public function show(AuditEvent $auditEvent)
    {
        $auditEvent->load('user');
        return view('admin.audit.show', compact('auditEvent'));
    }
}
