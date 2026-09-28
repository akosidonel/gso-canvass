<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_monitoring_records', function (Blueprint $table) {
            $table->dropColumn('total');
        });
        $this->refreshFingerprints(false);
    }

    public function down(): void
    {
        Schema::table('price_monitoring_records', function (Blueprint $table) {
            $table->decimal('total', 18, 2)->default(0);
        });
        $this->refreshFingerprints(true);
    }

    private function refreshFingerprints(bool $withTotal): void
    {
        DB::table('price_monitoring_records')->orderBy('id')->chunkById(500, function ($records) use ($withTotal) {
            foreach ($records as $record) {
                $qty = number_format((float) $record->qty, 3, '.', '');
                $amount = number_format((float) $record->amount, 2, '.', '');
                $identity = [$qty, $record->unit, $record->brand_model ?? '', $record->particulars, $amount];
                $updates = [];
                if ($withTotal) {
                    $cents = intdiv((int) str_replace('.', '', $qty) * (int) str_replace('.', '', $amount) + 500, 1000);
                    $updates['total'] = intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
                    $identity[] = $updates['total'];
                }
                array_push($identity, $record->department, $record->control_number, $record->store ?? '', $record->canvasser);
                $identity = array_map(fn ($value) => mb_strtolower(trim($value)), $identity);
                $updates['fingerprint'] = hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE));
                DB::table('price_monitoring_records')->where('id', $record->id)->update($updates);
            }
        });
    }
};
