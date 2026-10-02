<?php

namespace App\Jobs;

use App\Models\PriceArchiveBatch;
use App\Models\PriceRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessPriceArchive implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $batchId)
    {
        $this->onConnection('database');
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $batch = PriceArchiveBatch::whereKey($this->batchId)->lockForUpdate()->firstOrFail();
            if (! in_array($batch->status, ['queued', 'processing', 'restoring'])) {
                return;
            }
            $restoring = $batch->status === 'restoring';
            $ids = DB::table('price_archive_items')->where('batch_id', $batch->id)
                ->where('record_id', '>', $batch->cursor)->orderBy('record_id')->limit(500)->pluck('record_id');
            if ($ids->isNotEmpty()) {
                $records = PriceRecord::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);
                if ($restoring) {
                    PriceRecord::whereIn('id', $records->modelKeys())->where('archive_batch_id', $batch->id)
                        ->toBase()->update(['archived_at' => null, 'archive_batch_id' => null]);
                } else {
                    PriceRecord::whereIn('id', $records->modelKeys())->whereNull('archived_at')
                        ->toBase()->update(['archived_at' => now(), 'archive_batch_id' => $batch->id]);
                }
                $batch->cursor = $ids->last();
                $batch->processed += $ids->count();
            }
            if ($batch->processed >= $batch->total) {
                $batch->status = $restoring ? 'restored' : 'completed';
                $batch->{$restoring ? 'restored_at' : 'completed_at'} = now();
                if ($restoring) {
                    DB::table('price_archive_items')->where('batch_id', $batch->id)->delete();
                }
            } else {
                $batch->status = $restoring ? 'restoring' : 'processing';
                // The database queue insert commits atomically with this chunk's progress.
                self::dispatch($batch->id);
            }
            $batch->save();
        });
    }

    public function failed(?Throwable $exception): void
    {
        PriceArchiveBatch::whereKey($this->batchId)->whereIn('status', ['queued', 'processing', 'restoring'])
            ->update(['status' => 'failed']);
    }
}
