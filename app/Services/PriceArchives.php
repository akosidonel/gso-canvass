<?php

namespace App\Services;

use App\Jobs\ProcessPriceArchive;
use App\Models\PriceArchiveBatch;
use App\Models\PriceRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceArchives
{
    public static function eligible(int $year)
    {
        return PriceRecord::whereNull('archived_at')
            ->where('created_at', '>=', $year.'-01-01')
            ->where('created_at', '<', ($year + 1).'-01-01');
    }

    public static function archive(int $year, int $expectedCount, User $actor): PriceArchiveBatch
    {
        UserAccounts::authorize($actor, 'manage-archives');
        if ($year < 1900 || $year >= now()->year || $expectedCount < 1) {
            throw ValidationException::withMessages(['year' => __('Select a completed year with active records.')]);
        }

        return DB::transaction(function () use ($year, $expectedCount, $actor) {
            // Serialize new operations, including requests from different administrators.
            DB::table('users')->orderBy('id')->lockForUpdate()->first();
            if (PriceArchiveBatch::whereIn('status', ['queued', 'processing', 'restoring', 'failed'])->exists()) {
                throw ValidationException::withMessages(['archive' => __('Finish or retry the existing archive operation first.')]);
            }
            $batch = PriceArchiveBatch::create(['year' => $year, 'actor_id' => $actor->id, 'actor_name' => $actor->name]);
            // Capture membership without loading the year's records into PHP memory.
            DB::table('price_archive_items')->insertUsing(['batch_id', 'record_id'],
                self::eligible($year)->selectRaw('? as batch_id, id', [$batch->id])->toBase());
            $count = DB::table('price_archive_items')->where('batch_id', $batch->id)->count();
            if ($count === 0 || $count !== $expectedCount) {
                throw ValidationException::withMessages(['archive' => __('The record count changed. Preview the year again before archiving.')]);
            }
            $batch->update(['total' => $count]);
            ProcessPriceArchive::dispatch($batch->id);

            return $batch;
        });
    }

    public static function restore(PriceArchiveBatch $batch, User $actor): void
    {
        UserAccounts::authorize($actor, 'manage-archives');
        DB::transaction(function () use ($batch, $actor) {
            $batch = PriceArchiveBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'completed') {
                throw ValidationException::withMessages(['archive' => __('Only completed archives can be restored.')]);
            }
            $batch->update(['action' => 'restore', 'status' => 'restoring', 'processed' => 0,
                'cursor' => 0, 'restored_by' => $actor->name]);
            ProcessPriceArchive::dispatch($batch->id);
        });
    }

    public static function retry(PriceArchiveBatch $batch, User $actor): void
    {
        UserAccounts::authorize($actor, 'manage-archives');
        DB::transaction(function () use ($batch) {
            $batch = PriceArchiveBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'failed') {
                throw ValidationException::withMessages(['archive' => __('Only failed operations can be retried.')]);
            }
            $batch->update(['status' => $batch->action === 'restore' ? 'restoring' : 'queued']);
            ProcessPriceArchive::dispatch($batch->id);
        });
    }

    public static function assertEditable(PriceRecord $record): void
    {
        if ($record->archived_at || DB::table('price_archive_items')->where('record_id', $record->id)->exists()) {
            throw ValidationException::withMessages(['archive' => __('Archived records and records in an archive operation are read-only.')]);
        }
    }
}
