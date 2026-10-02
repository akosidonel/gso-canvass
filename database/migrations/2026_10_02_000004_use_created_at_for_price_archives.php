<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_monitoring_records', function (Blueprint $table) {
            $table->dropIndex(['archived_at', 'canvass_date']);
            $table->dropColumn('canvass_date');
            $table->index(['archived_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('price_monitoring_records', function (Blueprint $table) {
            $table->dropIndex(['archived_at', 'created_at']);
            $table->date('canvass_date')->nullable();
            $table->index(['archived_at', 'canvass_date']);
        });
    }
};
