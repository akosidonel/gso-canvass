<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('price_monitoring_records', function (Blueprint $table) {
                $table->string('category')->nullable()->after('id')->change();
            });
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('price_monitoring_records', function (Blueprint $table) {
                $table->string('category')->nullable()->after('updated_at')->change();
            });
        }
    }
};
