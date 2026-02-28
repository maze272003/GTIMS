<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use Illuminate\Support\Str;

class BranchSeeder extends Seeder
{
    public function run()
    {
        Branch::query()->update(['is_main' => false]);

        $defaults = [
            ['name' => 'RHU 1', 'is_main' => true],
            ['name' => 'RHU 2', 'is_main' => false],
        ];

        foreach ($defaults as $index => $branch) {
            $code = Str::slug($branch['name']);

            Branch::query()->updateOrCreate(
                ['name' => $branch['name']],
                [
                    'code' => $code !== '' ? $code : 'branch-'.($index + 1),
                    'is_main' => $branch['is_main'],
                    'is_archived' => false,
                ]
            );
        }
    }
}
