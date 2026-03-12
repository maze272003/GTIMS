<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Repositories\Interfaces\AuditEventRepositoryInterface;
use App\Services\BranchAccessService;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    public function __construct(
        protected AuditEventRepositoryInterface $auditEventRepository,
        protected BranchAccessService $branchAccessService
    ) {
    }

    public function index(Request $request)
    {
        $branchId = $this->branchAccessService->resolveBranchFilter($request->user(), null, defaultToUserBranch: true);

        $events = $this->auditEventRepository->paginateWithFilters(
            $request->action,
            $request->entity_type,
            $request->user_id ? (int) $request->user_id : null,
            $request->from,
            $request->to,
            30,
            $branchId
        );

        $actions = $this->auditEventRepository->getDistinctActions($branchId);
        $entityTypes = $this->auditEventRepository->getDistinctEntityTypes($branchId);

        return view('admin.audit.index', compact('events', 'actions', 'entityTypes'));
    }

    public function show(AuditEvent $auditEvent)
    {
        $auditEvent->load('user');
        $this->branchAccessService->authorizeBranchAccess(
            request()->user(),
            data_get($auditEvent->metadata, 'branch_id') ?? $auditEvent->user?->branch_id,
            'view audit logs from another branch'
        );
        return view('admin.audit.show', compact('auditEvent'));
    }
}
