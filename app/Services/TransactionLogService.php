<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionLogService
{
    /**
     * Record a transaction log entry into the notifications table.
     *
     * @param string $actionType  e.g. create, update, delete, transfer, approve, reject, login, logout
     * @param string $category    e.g. inventory, supplier_request, stock_hold, pull_out, adjustment
     * @param array  $details     Granular metadata: item details, quantity changes, audit data, etc.
     * @param User|null $user     The user performing the action (defaults to Auth::user())
     */
    public function log(string $actionType, string $category, array $details = [], ?User $user = null): void
    {
        $user = $user ?? Auth::user();

        if (!$user) {
            return;
        }

        $data = [
            'action_type' => $actionType,
            'category' => $category,
            'details' => $details,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'timestamp' => now()->toIso8601String(),
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ];

        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TransactionLog',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log an inventory create action.
     */
    public function logInventoryCreate(array $itemDetails, ?User $user = null): void
    {
        $this->log('create', 'inventory', $itemDetails, $user);
    }

    /**
     * Log an inventory update action.
     */
    public function logInventoryUpdate(array $itemDetails, ?User $user = null): void
    {
        $this->log('update', 'inventory', $itemDetails, $user);
    }

    /**
     * Log an inventory delete/deactivate action.
     */
    public function logInventoryDelete(array $itemDetails, ?User $user = null): void
    {
        $this->log('delete', 'inventory', $itemDetails, $user);
    }

    /**
     * Log an inventory transfer action.
     */
    public function logInventoryTransfer(array $itemDetails, ?User $user = null): void
    {
        $this->log('transfer', 'inventory', $itemDetails, $user);
    }

    /**
     * Log a supplier request action.
     */
    public function logSupplierRequest(string $actionType, array $details, ?User $user = null): void
    {
        $this->log($actionType, 'supplier_request', $details, $user);
    }

    /**
     * Log a stock hold action.
     */
    public function logStockHold(string $actionType, array $details, ?User $user = null): void
    {
        $this->log($actionType, 'stock_hold', $details, $user);
    }

    /**
     * Log a pull-out action.
     */
    public function logPullOut(array $details, ?User $user = null): void
    {
        $this->log('create', 'pull_out', $details, $user);
    }

    /**
     * Log a general inventory adjustment.
     */
    public function logAdjustment(array $details, ?User $user = null): void
    {
        $this->log('update', 'adjustment', $details, $user);
    }

    /**
     * Log an approval action.
     */
    public function logApproval(string $category, array $details, ?User $user = null): void
    {
        $this->log('approve', $category, $details, $user);
    }

    /**
     * Log a rejection action.
     */
    public function logRejection(string $category, array $details, ?User $user = null): void
    {
        $this->log('reject', $category, $details, $user);
    }

    /**
     * Log a user login event.
     */
    public function logLogin(?User $user = null): void
    {
        $this->log('login', 'auth', ['event' => 'User logged in'], $user);
    }

    /**
     * Log a user logout event.
     */
    public function logLogout(?User $user = null): void
    {
        $this->log('logout', 'auth', ['event' => 'User logged out'], $user);
    }

    /**
     * Log a low stock alert.
     */
    public function logLowStockAlert(array $details, ?User $user = null): void
    {
        $this->log('create', 'low_stock_alert', $details, $user);
    }
}
