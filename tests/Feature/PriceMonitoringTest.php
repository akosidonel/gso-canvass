<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function priceActor(string $role): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => $role, 'is_active' => true])->save();

    return $user;
}

function priceRow(array $changes = []): array
{
    return array_replace([
        'qty' => '2.125', 'unit' => 'pcs', 'brand_model' => 'Sample brand',
        'particulars' => "Paper\nA4", 'amount' => '10.20',
        'department' => 'GSO', 'control_number' => '001-2026', 'store' => 'Sample store', 'canvasser' => 'Staff',
    ], $changes);
}

test('guests cannot access price monitoring', function () {
    $this->get('/price-monitoring')->assertRedirect('/signin');
    $this->postJson('/price-monitoring', ['rows' => [priceRow()]])->assertUnauthorized();
});

test('canvassers can search and copy but cannot change records', function () {
    $this->actingAs(priceActor('canvasser'));
    $this->get('/price-monitoring')->assertOk()->assertSee('Copy')->assertDontSee('data-select-all', false)->assertDontSee('data-confirm-delete', false);
    $this->get('/price-monitoring/create')->assertForbidden();
    $this->postJson('/price-monitoring', ['rows' => [priceRow()]])->assertForbidden();
    $this->putJson('/price-monitoring/1', ['rows' => [priceRow()]])->assertForbidden();
    $this->delete('/price-monitoring/1')->assertForbidden();
});

test('TL can paste a batch and edit without a total column', function () {
    $this->actingAs(priceActor('tl_canvasser'));
    $this->get('/price-monitoring/create')->assertOk()->assertSee('data-price-editor', false);
    $this->postJson('/price-monitoring', ['rows' => [priceRow(), priceRow(['particulars' => 'Second item'])]])->assertOk();
    $this->assertDatabaseCount('price_monitoring_records', 2);
    expect(\Illuminate\Support\Facades\Schema::hasColumn('price_monitoring_records', 'total'))->toBeFalse();
    $this->assertDatabaseHas('price_monitoring_records', ['id' => 1, 'qty' => '2.125', 'amount' => '10.20', 'control_number' => '001-2026']);
    $this->get('/price-monitoring?search=001-2026')->assertOk()->assertSee('Second item');
    $this->get('/price-monitoring?search=unmatched')->assertOk()->assertDontSee('Second item');
    $this->get('/price-monitoring/1/edit')->assertOk();
    $this->putJson('/price-monitoring/1', ['rows' => [priceRow(['qty' => '3'])]])->assertOk();
    $this->assertDatabaseHas('price_monitoring_records', ['id' => 1, 'qty' => '3', 'amount' => '10.20']);
    $this->delete('/price-monitoring/1')->assertForbidden();
    $this->assertDatabaseCount('price_monitoring_records', 2);
});

test('invalid batches save nothing and preserve valid zero prices', function () {
    $this->actingAs(priceActor('tl_canvasser'));
    $this->postJson('/price-monitoring', ['rows' => [priceRow(), priceRow(['qty' => '=2*3'])]])->assertUnprocessable()->assertJsonValidationErrors('rows.1.qty');
    $this->assertDatabaseCount('price_monitoring_records', 0);
    $this->postJson('/price-monitoring', ['rows' => [priceRow(['amount' => '-1'])]])->assertUnprocessable();
    $this->postJson('/price-monitoring', ['rows' => [priceRow(['amount' => '1.123'])]])->assertUnprocessable();
    $this->postJson('/price-monitoring', ['rows' => [priceRow(['department' => '', 'control_number' => ''])]])->assertUnprocessable();
    $this->postJson('/price-monitoring', ['rows' => array_fill(0, 501, priceRow())])->assertUnprocessable();
    $this->postJson('/price-monitoring', ['rows' => [priceRow(['amount' => '0', 'brand_model' => '', 'store' => ''])]])->assertOk();
    $this->assertDatabaseHas('price_monitoring_records', ['amount' => '0']);
});

test('duplicates roll back the entire batch and editing the same record is allowed', function () {
    $this->actingAs(priceActor('system_admin'));
    $this->postJson('/price-monitoring', ['rows' => [priceRow(), priceRow()]])->assertUnprocessable();
    $this->assertDatabaseCount('price_monitoring_records', 0);
    $this->postJson('/price-monitoring', ['rows' => [priceRow()]])->assertOk();
    $this->postJson('/price-monitoring', ['rows' => [priceRow(['particulars' => 'New']), priceRow()]])->assertUnprocessable();
    $this->assertDatabaseCount('price_monitoring_records', 1);
    $this->putJson('/price-monitoring/1', ['rows' => [priceRow()]])->assertOk();
    $this->delete('/price-monitoring/1')->assertRedirect('/price-monitoring');
    $this->assertDatabaseCount('price_monitoring_records', 0);
});

test('invalid or inactive roles cannot access prices', function () {
    $user = priceActor('canvasser');
    $user->forceFill(['is_active' => false])->save();
    $this->actingAs($user)->get('/price-monitoring')->assertRedirect('/signin');
});

test('removing total preserves existing records and duplicate detection', function () {
    $this->actingAs(priceActor('system_admin'));
    $this->postJson('/price-monitoring', ['rows' => [priceRow()]])->assertOk();
    $record = \App\Models\PriceRecord::firstOrFail();
    $fingerprint = $record->fingerprint;
    $migration = require database_path('migrations/2026_09_26_000001_remove_total_from_price_monitoring_records.php');
    $migration->down();
    $this->assertDatabaseHas('price_monitoring_records', ['id' => $record->id, 'total' => '21.68']);
    $migration->up();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('price_monitoring_records', 'total'))->toBeFalse();
    $this->assertDatabaseHas('price_monitoring_records', ['id' => $record->id, 'fingerprint' => $fingerprint, 'control_number' => '001-2026']);
    $this->postJson('/price-monitoring', ['rows' => [priceRow()]])->assertUnprocessable();
    $this->assertDatabaseCount('price_monitoring_records', 1);
    $this->get('/price-monitoring')->assertOk()->assertDontSee('>Total<', false);
    $this->get('/price-monitoring/'.$record->id.'/edit')->assertOk()->assertDontSee('data-field="total"', false);
});
