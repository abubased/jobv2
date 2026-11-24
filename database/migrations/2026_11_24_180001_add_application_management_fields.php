<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('all_applications')) {
            return;
        }

        Schema::table('all_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('all_applications', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false);
            }
            if (! Schema::hasColumn('all_applications', 'labels')) {
                $table->json('labels')->nullable();
            }
            if (! Schema::hasColumn('all_applications', 'notes')) {
                $table->json('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('all_applications')) {
            return;
        }

        Schema::table('all_applications', function (Blueprint $table): void {
            $columns = array_filter(
                ['is_blocked', 'labels', 'notes'],
                static fn (string $column): bool => Schema::hasColumn('all_applications', $column)
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
