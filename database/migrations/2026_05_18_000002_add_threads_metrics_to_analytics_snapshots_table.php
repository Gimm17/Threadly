<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_snapshots', function (Blueprint $table) {
            if (! Schema::hasColumn('analytics_snapshots', 'likes')) {
                $table->unsignedBigInteger('likes')->default(0)->after('impressions');
            }

            if (! Schema::hasColumn('analytics_snapshots', 'replies')) {
                $table->unsignedBigInteger('replies')->default(0)->after('likes');
            }

            if (! Schema::hasColumn('analytics_snapshots', 'reposts')) {
                $table->unsignedBigInteger('reposts')->default(0)->after('replies');
            }

            if (! Schema::hasColumn('analytics_snapshots', 'quotes')) {
                $table->unsignedBigInteger('quotes')->default(0)->after('reposts');
            }

            if (! Schema::hasColumn('analytics_snapshots', 'source')) {
                $table->string('source')->default('manual')->after('engagement_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('analytics_snapshots', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['likes', 'replies', 'reposts', 'quotes', 'source'],
                fn (string $column): bool => Schema::hasColumn('analytics_snapshots', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
