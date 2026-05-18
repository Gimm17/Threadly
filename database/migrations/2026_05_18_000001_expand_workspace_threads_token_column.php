<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('workspaces', 'threads_access_token')) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->text('threads_access_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workspaces', 'threads_access_token')) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('threads_access_token', 2048)->nullable()->change();
        });
    }
};
