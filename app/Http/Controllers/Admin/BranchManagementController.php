<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchArchivalRun;
use App\Services\BranchLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class BranchManagementController extends Controller
{
    public function __construct(
        private readonly BranchLifecycleService $branchLifecycleService
    ) {
    }

    public function index(): View
    {
        return view('admin.branches.index', $this->branchLifecycleService->getIndexData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:branches,name'],
            'code' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:branches,code'],
            'is_main' => ['nullable', 'boolean'],
        ]);

        try {
            $this->branchLifecycleService->createBranch($validated, $request->user());
            return redirect()->route('admin.branches.index')->with('success', 'Branch created successfully.');
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.branches.index')
                ->withInput()
                ->with('error', 'Unable to create branch: '.$exception->getMessage());
        }
    }

    public function setMain(Request $request, Branch $branch): RedirectResponse
    {
        try {
            $this->branchLifecycleService->setMainBranch($branch, $request->user());
            return redirect()->route('admin.branches.index')->with('success', 'Main branch updated successfully.');
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.branches.index')
                ->with('error', 'Unable to set main branch: '.$exception->getMessage());
        }
    }

    public function archive(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'target_main_branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')
                    ->where(fn ($query) => $query->where('is_main', true)->where('is_archived', false)),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $run = $this->branchLifecycleService->archiveBranch(
                $branch,
                $request->user(),
                (int) $validated['target_main_branch_id'],
                $validated['reason'] ?? null
            );

            $statusLabel = str_replace('_', ' ', $run->status);
            return redirect()->route('admin.branches.index')
                ->with('success', "Archival workflow completed with status: {$statusLabel}.");
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.branches.index')
                ->with('error', 'Archival failed: '.$exception->getMessage());
        }
    }

    public function rollback(Request $request, BranchArchivalRun $run): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->branchLifecycleService->rollbackFailedRun(
                $run,
                $request->user(),
                $validated['reason'] ?? null
            );

            return redirect()->route('admin.branches.index')->with('success', 'Rollback marker applied to failed run.');
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.branches.index')
                ->with('error', 'Rollback failed: '.$exception->getMessage());
        }
    }
}

