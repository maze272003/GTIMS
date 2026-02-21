<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Find a record by its primary key.
     */
    public function find(int $id, array $columns = ['*']): ?Model;

    /**
     * Find a record by its primary key or throw an exception.
     */
    public function findOrFail(int $id, array $columns = ['*']): Model;

    /**
     * Create a new record.
     */
    public function create(array $data): Model;

    /**
     * Update an existing record.
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a record.
     */
    public function delete(int $id): bool;

    /**
     * Paginate records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Find records by a specific field.
     */
    public function findByField(string $field, mixed $value, array $columns = ['*']): Collection;
}
