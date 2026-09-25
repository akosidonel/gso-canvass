<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_monitoring_records', function (Blueprint $table) {
            $table->id();
            $table->decimal('qty', 8, 3);
            $table->string('unit', 50);
            $table->string('brand_model')->nullable();
            $table->text('particulars');
            $table->decimal('amount', 9, 2);
            $table->decimal('total', 18, 2);
            $table->string('department')->index();
            $table->string('control_number', 100)->index();
            $table->string('store', 1000)->nullable();
            $table->string('canvasser')->index();
            $table->char('fingerprint', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_monitoring_records');
    }
};
