<?php

use App\Jobs\ProcessPriceArchive;
use App\Models\PriceArchiveBatch;
use App\Models\PriceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function archiveActor(string $role = 'system_admin'): User
{
    return User::factory()->create(['role' => $role, 'is_active' => true]);
}

function archiveRecord(?string $date, string $name = 'Archive item'): PriceRecord
{
    $record = PriceRecord::create([
        'qty' => '1', 'unit' => 'pcs', 'particulars' => $name, 'amount' => '10.00',
        'department' => 'GSO', 'control_number' => '2025-001', 'canvasser' => 'Staff',
        'category' => 'Office Supplies',
        'fingerprint' => hash('sha256', $name),
    ]);
    $record->forceFill(['created_at' => $date])->save();

    return $record;
}

function queueArchive($test, int $count): PriceArchiveBatch
{
    $test->post('/price-monitoring/archives', [
        'year' => 2025, 'expected_count' => $count, 'confirmed' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return PriceArchiveBatch::latest('id')->firstOrFail();
}

test('only system admins can preview archive or restore operations', function () {
    $this->get('/price-monitoring/archives')->assertRedirect('/signin');
    PriceArchiveBatch::create(['year' => 2025, 'actor_name' => 'Admin']);
    foreach (['canvasser', 'tl_canvasser'] as $role) {
        $this->actingAs(archiveActor($role));
        $this->get('/price-monitoring/archives')->assertForbidden();
        $this->post('/price-monitoring/archives', ['year' => 2025])->assertForbidden();
        $this->post('/price-monitoring/archives/1/restore')->assertForbidden();
        $this->post('/price-monitoring/archives/1/retry')->assertForbidden();
        $this->get('/price-monitoring')->assertDontSee('Archive Management');
    }
    $this->actingAs(archiveActor());
    $this->get('/price-monitoring/archives')->assertOk()->assertSee('Archive by year');
});

test('preview uses creation timestamps and excludes other years', function () {
    $this->actingAs(archiveActor());
    archiveRecord('2025-01-01', 'Start boundary');
    archiveRecord('2025-12-31 23:59:59', 'End boundary');
    archiveRecord('2026-01-01', 'Next year');
    archiveRecord('2024-12-31', 'Previous year');
    archiveRecord(null, 'Unknown');
    $this->get('/price-monitoring/archives?year=2025')->assertOk()
        ->assertViewHas('count', 2)
        ->assertSee('Review matching records');
    $this->get('/price-monitoring?year=2025')->assertOk()->assertSee('Start boundary')
        ->assertSee('End boundary')->assertDontSee('Next year')->assertDontSee('Unknown');
});

test('archiving requires confirmation and an unchanged nonempty preview count', function () {
    Bus::fake();
    $this->actingAs(archiveActor());
    archiveRecord('2025-06-01');
    $this->post('/price-monitoring/archives', ['year' => 2025, 'expected_count' => 1])->assertSessionHasErrors('confirmed');
    $this->post('/price-monitoring/archives', ['year' => 2025, 'expected_count' => 2, 'confirmed' => 1])->assertSessionHasErrors('archive');
    $this->post('/price-monitoring/archives', ['year' => 2026, 'expected_count' => 1, 'confirmed' => 1])->assertSessionHasErrors('year');
    expect(PriceArchiveBatch::count())->toBe(0);
    expect(DB::table('price_archive_items')->count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('archive snapshots membership and queued and archived records are read only', function () {
    Bus::fake();
    $this->actingAs(archiveActor());
    $record = archiveRecord('2025-06-01', 'Selected archive');
    $batch = queueArchive($this, 1);
    Bus::assertDispatched(ProcessPriceArchive::class);
    archiveRecord('2025-07-01', 'Late addition');
    $this->get('/price-monitoring/'.$record->id.'/edit')->assertSessionHasErrors('archive');
    $this->delete('/price-monitoring/'.$record->id)->assertSessionHasErrors('archive');
    (new ProcessPriceArchive($batch->id))->handle();
    expect($record->refresh()->archived_at)->not->toBeNull();
    expect($batch->refresh()->status)->toBe('completed');
    $this->get('/price-monitoring')->assertOk()->assertDontSee('Selected archive')->assertSee('Late addition');
    $this->get('/price-monitoring?view=archived&year=2025')->assertOk()->assertSee('Selected archive')->assertDontSee('Late addition')->assertDontSee('data-confirm-delete', false);
    $this->putJson('/price-monitoring/'.$record->id, ['rows' => [$record->only(array_keys(PriceRecord::FIELDS))]])
        ->assertUnprocessable()->assertJsonValidationErrors('archive');
    $this->actingAs(archiveActor('canvasser'));
    $this->get('/price-monitoring?view=archived&category=Office%20Supplies&search=Selected')->assertOk()->assertSee('Selected archive');
});

test('chunks resume after failure and restore the exact batch', function () {
    Bus::fake();
    $this->actingAs(archiveActor());
    for ($i = 1; $i <= 501; $i++) {
        archiveRecord('2025-06-01', 'Bulk '.$i);
    }
    $batch = queueArchive($this, 501);
    $job = new ProcessPriceArchive($batch->id);
    $job->handle();
    expect($batch->refresh()->processed)->toBe(500);
    expect(PriceRecord::whereNotNull('archived_at')->count())->toBe(500);
    $job->failed(new RuntimeException('Simulated worker interruption'));
    expect($batch->refresh()->status)->toBe('failed');
    $this->post('/price-monitoring/archives/'.$batch->id.'/retry')->assertRedirect()->assertSessionHasNoErrors();
    $job->handle();
    $job->handle();
    expect($batch->refresh()->processed)->toBe(501);
    expect($batch->status)->toBe('completed');
    archiveRecord('2025-06-01', 'Unrelated item');
    $this->post('/price-monitoring/archives/'.$batch->id.'/restore')->assertSessionHasErrors('confirmed');
    $this->post('/price-monitoring/archives/'.$batch->id.'/restore', ['confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $job->handle();
    $job->failed(new RuntimeException('Restore interrupted'));
    $this->post('/price-monitoring/archives/'.$batch->id.'/retry')->assertRedirect()->assertSessionHasNoErrors();
    $job->handle();
    expect($batch->refresh()->status)->toBe('restored');
    expect($batch->restored_by)->toBe(auth()->user()->name);
    expect(PriceRecord::whereNotNull('archived_at')->count())->toBe(0);
    expect(DB::table('price_archive_items')->count())->toBe(0);
    expect(PriceRecord::count())->toBe(502);
    $this->post('/price-monitoring/archives/'.$batch->id.'/restore', ['confirmed' => 1])->assertSessionHasErrors('archive');
});

test('editing archiving and restoring preserve the original creation timestamp', function () {
    Bus::fake();
    $this->actingAs(archiveActor());
    $record = archiveRecord('2025-06-01 12:30:00');
    $created = $record->created_at->toDateTimeString();
    $row = $record->only(array_keys(PriceRecord::FIELDS));
    $row['particulars'] = 'Updated description';
    $this->putJson('/price-monitoring/'.$record->id, ['rows' => [$row]])->assertOk();
    expect($record->refresh()->created_at->toDateTimeString())->toBe($created);
    $this->get('/price-monitoring/'.$record->id.'/edit')->assertOk()->assertDontSee('Canvass Date');
    expect(\Illuminate\Support\Facades\Schema::hasColumn('price_monitoring_records', 'canvass_date'))->toBeFalse();
    $batch = queueArchive($this, 1);
    (new ProcessPriceArchive($batch->id))->handle();
    expect($record->refresh()->created_at->toDateTimeString())->toBe($created);
    $this->post('/price-monitoring/archives/'.$batch->id.'/restore', ['confirmed' => 1])->assertRedirect();
    (new ProcessPriceArchive($batch->id))->handle();
    expect($record->refresh()->created_at->toDateTimeString())->toBe($created);
});

test('database worker executes queued chunks and completes the archive', function () {
    $this->actingAs(archiveActor());
    for ($i = 1; $i <= 501; $i++) {
        archiveRecord('2025-06-01', 'Queued '.$i);
    }
    $batch = queueArchive($this, 501);
    expect(DB::table('jobs')->count())->toBe(1);
    $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 3])->assertSuccessful();
    expect($batch->refresh()->status)->toBe('completed');
    expect($batch->processed)->toBe(501);
    expect(DB::table('jobs')->count())->toBe(0);
    expect(PriceRecord::whereNotNull('archived_at')->count())->toBe(501);
});

test('exports keep active and archived records separate and honor the year', function () {
    Bus::fake();
    $this->actingAs(archiveActor());
    archiveRecord('2025-06-01', 'Historical price');
    archiveRecord('2024-06-01', 'Other year price');
    $batch = queueArchive($this, 1);
    (new ProcessPriceArchive($batch->id))->handle();
    foreach ([
        '/price-monitoring/export' => ['Other year price', 'Historical price'],
        '/price-monitoring/export?view=archived&year=2025' => ['Historical price', 'Other year price'],
    ] as $url => [$included, $excluded]) {
        $response = $this->get($url)->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        try {
            $zip->open($path);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            expect($sheet)->toContain($included)->not->toContain($excluded);
        } finally {
            $zip->close();
            unlink($path);
        }
    }
});
