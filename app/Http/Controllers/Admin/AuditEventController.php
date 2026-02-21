<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Repositories\Interfaces\AuditEventRepositoryInterface;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    public function __construct(
        protected AuditEventRepositoryInterface $auditEventRepository
    ) {
    }

    public function index(Request $request)
    {
        $events = $this->auditEventRepository->paginateWithFilters(
            $request->action,
            $request->entity_type,
            $request->user_id ? (int) $request->user_id : null,
            $request->from,
            $request->to,
            30
        );

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
