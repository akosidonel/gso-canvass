<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_monitoring_records', function (Blueprint $table) {
            $table->string('category')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('price_monitoring_records', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
