<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve existing meta_info bytes while allowing the JSON payload to grow.
     * Convert the legacy MyISAM table to InnoDB so future transactional updates
     * and row-level locking are actually supported.
     */
    public function up(): void
    {
        if (! Schema::hasTable('all_applications')) {
            return;
        }

        DB::statement('ALTER TABLE all_applications ENGINE=InnoDB');
        DB::statement('ALTER TABLE all_applications MODIFY meta_info LONGTEXT NULL');

        $indexes = collect(DB::select('SHOW INDEX FROM all_applications'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        if (! in_array('all_applications_updated_at_id_index', $indexes, true)) {
            DB::statement('CREATE INDEX all_applications_updated_at_id_index ON all_applications (updated_at, id)');
        }
    }

    /** Keep the safer InnoDB engine and expanded text column on rollback. */
    public function down(): void
    {
        if (! Schema::hasTable('all_applications')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM all_applications'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        if (in_array('all_applications_updated_at_id_index', $indexes, true)) {
            DB::statement('DROP INDEX all_applications_updated_at_id_index ON all_applications');
        }
    }
};
