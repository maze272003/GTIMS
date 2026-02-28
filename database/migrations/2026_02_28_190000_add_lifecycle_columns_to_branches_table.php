<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->boolean('is_main')->default(false)->after('code');
            $table->boolean('is_archived')->default(false)->after('is_main');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('archive_checksum', 64)->nullable()->after('archived_by');
            $table->json('archive_metadata')->nullable()->after('archive_checksum');
            $table->index('is_archived');
            $table->unique('code');
        });

        $branches = DB::table('branches')
            ->select('id', 'name')
            ->orderBy('id')
            ->get();

        $usedCodes = [];
        $firstBranchId = $branches->first()->id ?? null;

        foreach ($branches as $branch) {
            $base = Str::slug((string) $branch->name);

            if ($base === '') {
                $base = 'branch-'.$branch->id;
            }

            $candidate = $base;
            $suffix = 2;

            while (in_array($candidate, $usedCodes, true)) {
                $candidate = $base.'-'.$suffix;
                $suffix++;
            }

            $usedCodes[] = $candidate;

            DB::table('branches')
                ->where('id', $branch->id)
                ->update([
                    'code' => $candidate,
                    'is_main' => $branch->id === $firstBranchId,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
            $table->dropIndex(['is_archived']);
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code',
                'is_main',
                'is_archived',
                'archived_at',
                'archived_by',
                'archive_checksum',
                'archive_metadata',
            ]);
        });
    }
};

