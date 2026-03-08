<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowPermission;

class WorkflowDefinitionPolicy
{
    /**
     * Super admins bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->level && strtolower($user->level->name) === 'superadmin') {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('workflows.view');
    }

    public function view(User $user, WorkflowDefinition $workflow): bool
    {
        if ($user->hasPermission('workflows.view')) {
            return true;
        }

        return $this->hasWorkflowPermission($workflow, $user, 'view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('workflows.create');
    }

    public function edit(User $user, WorkflowDefinition $workflow): bool
    {
        if ($user->hasPermission('workflows.edit')) {
            return true;
        }

        return $this->hasWorkflowPermission($workflow, $user, 'edit');
    }

    public function publish(User $user, WorkflowDefinition $workflow): bool
    {
        if ($user->hasPermission('workflows.publish')) {
            return true;
        }

        return $this->hasWorkflowPermission($workflow, $user, 'publish');
    }

    public function run(User $user, WorkflowDefinition $workflow): bool
    {
        if ($user->hasPermission('workflows.run')) {
            return true;
        }

        return $this->hasWorkflowPermission($workflow, $user, 'run');
    }

    public function delete(User $user, WorkflowDefinition $workflow): bool
    {
        return $user->hasPermission('workflows.delete');
    }

    public function managePermissions(User $user, WorkflowDefinition $workflow): bool
    {
        if ($user->hasPermission('workflows.edit')) {
            return true;
        }

        // Owner can manage permissions
        return (int) $workflow->created_by === (int) $user->id;
    }

    public function rerun(User $user, WorkflowDefinition $workflow): bool
    {
        return $this->run($user, $workflow);
    }

    protected function hasWorkflowPermission(WorkflowDefinition $workflow, User $user, string $permission): bool
    {
        return WorkflowPermission::where('workflow_definition_id', $workflow->id)
            ->where('user_id', $user->id)
            ->where('permission', $permission)
            ->exists();
    }
}
