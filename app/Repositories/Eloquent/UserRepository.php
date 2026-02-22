<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function findByEmailWithRelations(string $email, array $relations = []): ?User
    {
        return $this->model->with($relations)->where('email', $email)->first();
    }

    public function getAllOrderedByName(array $columns = ['*']): Collection
    {
        return $this->model->select($columns)->orderBy('name')->get();
    }

    public function updateLoginMetadata(int $userId, ?string $ip): bool
    {
        /** @var User $user */
        $user = $this->model->findOrFail($userId);
        $user->last_login_at = now();
        $user->last_login_ip = $ip;

        return (bool) $user->save();
    }

    public function updateOtp(int $userId, ?string $otp, mixed $expiresAt): bool
    {
        /** @var User $user */
        $user = $this->model->findOrFail($userId);
        $user->otp = $otp;
        $user->otp_expires_at = $expiresAt;

        return (bool) $user->save();
    }
}

