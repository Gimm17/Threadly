<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hook_templates', 'category')) {
            return;
        }

        Schema::table('hook_templates', function (Blueprint $table) {
            $table->string('category', 100)->default('other')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('hook_templates', 'category')) {
            return;
        }

        Schema::table('hook_templates', function (Blueprint $table) {
            $table->enum('category', [
                'question',
                'controversial',
                'story',
                'statistic',
                'how_to',
                'listicle',
                'pov',
                'other',
            ])->default('other')->change();
        });
    }
};
