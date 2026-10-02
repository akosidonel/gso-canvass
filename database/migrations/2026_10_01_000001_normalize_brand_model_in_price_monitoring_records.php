<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('price_monitoring_records', 'brand_model')) {
            Schema::table('price_monitoring_records', function (Blueprint $table) {
                if (Schema::hasColumn('price_monitoring_records', 'brand/model')) {
                    $table->renameColumn('brand/model', 'brand_model');
                } else {
                    $table->string('brand_model')->nullable();
                }
            });
        }

        Schema::table('price_monitoring_records', function (Blueprint $table) {
            $table->string('brand_model')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('price_monitoring_records', 'brand_model')
            && ! Schema::hasColumn('price_monitoring_records', 'brand/model')) {
            Schema::table('price_monitoring_records', function (Blueprint $table) {
                $table->renameColumn('brand_model', 'brand/model');
            });
        }
    }
};
