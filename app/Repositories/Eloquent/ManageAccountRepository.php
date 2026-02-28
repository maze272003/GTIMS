<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\User;
use App\Models\UserLevel;
use App\Repositories\Interfaces\ManageAccountRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ManageAccountRepository implements ManageAccountRepositoryInterface
{
    public function paginateUsersWithRelations(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = User::with(['level', 'branch']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getLevelsForManage(bool $includeSuperadmin): Collection
    {
        return $includeSuperadmin
            ? UserLevel::all()
            : UserLevel::where('name', '!=', 'superadmin')->get();
    }

    public function getAllBranches(): Collection
    {
        return Branch::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function findUserLevelOrFail(int $id): UserLevel
    {
        return UserLevel::findOrFail($id);
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function findUserOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function updateUser(User $user, array $data): bool
    {
        return $user->update($data);
    }

    public function markUserVerifiedNow(User $user): bool
    {
        $user->email_verified_at = now();

        return (bool) $user->save();
    }
}
