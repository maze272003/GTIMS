<?php

namespace App\Support;

use App\Models\User;
use App\Services\AuthSessionService;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class PermissionView
{
    protected ?Collection $permissions = null;

    public function __construct(
        protected ?User $user,
        protected AuthSessionService $authSessionService
    ) {
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function permissions(): Collection
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }

        if (!$this->user) {
            return $this->permissions = collect();
        }

        return $this->permissions = $this->user
            ->getEffectivePermissions()
            ->pluck('name')
            ->filter()
            ->values();
    }

    public function names(): array
    {
        return $this->permissions()->all();
    }

    public function has(string|array $permissions): bool
    {
        return $this->hasAny($permissions);
    }

    public function allows(string|array $permissions, bool $requireAll = false): bool
    {
        return $requireAll
            ? $this->hasAll($permissions)
            : $this->hasAny($permissions);
    }

    public function hasAny(string|array ...$permissions): bool
    {
        $requiredPermissions = $this->flattenPermissions($permissions);

        if ($requiredPermissions === [] || !$this->user) {
            return false;
        }

        $availablePermissions = $this->names();

        foreach ($requiredPermissions as $permission) {
            if (in_array($permission, $availablePermissions, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasAll(string|array ...$permissions): bool
    {
        $requiredPermissions = $this->flattenPermissions($permissions);

        if ($requiredPermissions === [] || !$this->user) {
            return false;
        }

        $availablePermissions = $this->names();

        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $availablePermissions, true)) {
                return false;
            }
        }

        return true;
    }

    public function destination(): ?array
    {
        if (!$this->user) {
            return null;
        }

        return $this->authSessionService->getRedirectDestination($this->user);
    }

    public function deniedMessage(?string $subject = null): string
    {
        return $this->authSessionService->getForbiddenMessage($this->user, $subject);
    }

    public function disabledClasses(
        string|array $permissions,
        bool $requireAll = false,
        string $classes = 'cursor-not-allowed opacity-50 saturate-50'
    ): string {
        return $this->allows($permissions, $requireAll) ? '' : $classes;
    }

    public function disabledAttributes(
        string|array $permissions,
        ?string $subject = null,
        bool $nativeDisabled = false,
        bool $requireAll = false
    ): HtmlString {
        if ($this->allows($permissions, $requireAll)) {
            return new HtmlString('');
        }

        $message = e($this->deniedMessage($subject));
        $attributes = [
            'aria-disabled="true"',
            'data-permission-disabled="true"',
            'data-permission-message="'.$message.'"',
            'title="'.$message.'"',
        ];

        if ($nativeDisabled) {
            $attributes[] = 'disabled="disabled"';
        } else {
            $attributes[] = 'tabindex="-1"';
        }

        return new HtmlString(implode(' ', $attributes));
    }

    /**
     * @param  array<int, string|array<int, string>>  $permissions
     * @return array<int, string>
     */
    protected function flattenPermissions(array $permissions): array
    {
        return collect($permissions)
            ->flatten()
            ->filter(fn ($permission) => is_string($permission) && $permission !== '')
            ->values()
            ->all();
    }
}
