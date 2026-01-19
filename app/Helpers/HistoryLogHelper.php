<?php

namespace App\Helpers;

use App\Models\HistoryLog;
use Illuminate\Support\Facades\Auth;

class HistoryLogHelper
{
    /**
     * Log a product-related action
     */
    public static function logProductAction(string $action, string $description, array $metadata = []): HistoryLog
    {
        return HistoryLog::create([
            'action' => $action,
            'description' => $description,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'System',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log an inventory-related action
     */
    public static function logInventoryAction(string $action, string $description, array $metadata = []): HistoryLog
    {
        return HistoryLog::create([
            'action' => $action,
            'description' => $description,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'System',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a patient record action
     */
    public static function logPatientRecordAction(string $action, string $description, array $metadata = []): HistoryLog
    {
        return HistoryLog::create([
            'action' => $action,
            'description' => $description,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'System',
            'metadata' => $metadata,
        ]);
    }
}
