<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

class TransactionLogSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (!$user) {
            return;
        }

        $entries = [
            [
                'action_type' => 'create',
                'category' => 'inventory',
                'details' => [
                    'product_name' => 'Paracetamol 500mg',
                    'batch_number' => 'BATCH-0001',
                    'quantity' => 100,
                    'branch' => 'RHU 1',
                ],
            ],
            [
                'action_type' => 'update',
                'category' => 'inventory',
                'details' => [
                    'product_name' => 'Ibuprofen 200mg',
                    'batch_number' => 'BATCH-0010',
                    'quantity_before' => 50,
                    'quantity_after' => 75,
                    'branch' => 'RHU 1',
                ],
            ],
            [
                'action_type' => 'transfer',
                'category' => 'inventory',
                'details' => [
                    'product_name' => 'Amoxicillin 250mg',
                    'batch_number' => 'BATCH-0005',
                    'quantity' => 30,
                    'from_branch' => 'RHU 1',
                    'to_branch' => 'RHU 2',
                ],
            ],
            [
                'action_type' => 'delete',
                'category' => 'inventory',
                'details' => [
                    'product_name' => 'Expired Cetirizine',
                    'batch_number' => 'BATCH-0020',
                    'quantity' => 10,
                    'reason' => 'Expired stock removal',
                ],
            ],
            [
                'action_type' => 'create',
                'category' => 'supplier_request',
                'details' => [
                    'request_id' => 1,
                    'supplier' => 'PharmaCorp',
                    'items_count' => 3,
                ],
            ],
            [
                'action_type' => 'approve',
                'category' => 'supplier_request',
                'details' => [
                    'request_id' => 1,
                    'approved_by' => $user->name,
                ],
            ],
            [
                'action_type' => 'create',
                'category' => 'stock_hold',
                'details' => [
                    'hold_id' => 1,
                    'type' => 'reservation',
                    'product_name' => 'Paracetamol 500mg',
                    'quantity' => 20,
                ],
            ],
            [
                'action_type' => 'approve',
                'category' => 'stock_hold',
                'details' => [
                    'hold_id' => 1,
                    'approved_by' => $user->name,
                ],
            ],
            [
                'action_type' => 'create',
                'category' => 'pull_out',
                'details' => [
                    'product_name' => 'Losartan 50mg',
                    'quantity' => 15,
                    'reason' => 'Recalled by manufacturer',
                ],
            ],
            [
                'action_type' => 'login',
                'category' => 'auth',
                'details' => ['event' => 'User logged in'],
            ],
            [
                'action_type' => 'logout',
                'category' => 'auth',
                'details' => ['event' => 'User logged out'],
            ],
            [
                'action_type' => 'create',
                'category' => 'low_stock_alert',
                'details' => [
                    'product_name' => 'Metformin 500mg',
                    'available' => 5,
                    'threshold' => 10,
                    'branch' => 'RHU 2',
                ],
            ],
        ];

        foreach ($entries as $entry) {
            DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => 'App\\Notifications\\TransactionLog',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'action_type' => $entry['action_type'],
                    'category' => $entry['category'],
                    'details' => $entry['details'],
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'timestamp' => now()->subMinutes(rand(1, 1440))->toIso8601String(),
                    'ip_address' => '127.0.0.1',
                ]),
                'read_at' => null,
                'created_at' => now()->subMinutes(rand(1, 1440)),
                'updated_at' => now(),
            ]);
        }
    }
}
