<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_archive_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('status')->default('queued')->index();
            $table->string('action')->default('archive');
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('processed')->default(0);
            $table->unsignedBigInteger('cursor')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->string('restored_by')->nullable();
            $table->timestamps();
        });
        Schema::table('price_monitoring_records', function (Blueprint $table) {
            $table->date('canvass_date')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archive_batch_id')->nullable()->constrained('price_archive_batches')->restrictOnDelete();
            $table->index(['archived_at', 'category']);
            $table->index(['archived_at', 'canvass_date']);
        });
        Schema::create('price_archive_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->constrained('price_archive_batches')->cascadeOnDelete();
            $table->foreignId('record_id')->constrained('price_monitoring_records')->restrictOnDelete();
            $table->primary(['batch_id', 'record_id']);
            $table->index('record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_archive_items');
        Schema::table('price_monitoring_records', function (Blueprint $table) {
            $table->dropForeign(['archive_batch_id']);
            $table->dropIndex(['archived_at', 'category']);
            $table->dropIndex(['archived_at', 'canvass_date']);
            $table->dropColumn(['archived_at', 'archive_batch_id', 'canvass_date']);
        });
        Schema::dropIfExists('price_archive_batches');
    }
};
