<?php

use App\Models\PriceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function categoryPriceRow(array $overrides = []): array
{
    return array_replace([
        'qty' => '1', 'unit' => 'pcs', 'particulars' => 'Category item', 'amount' => '12.50',
        'department' => 'GSO', 'control_number' => '001-2026', 'canvasser' => 'Staff',
        'category' => 'Office Supplies',
    ], $overrides);
}

test('preview and edit forms offer preset and previously saved custom categories', function () {
    $this->actingAs(User::factory()->create(['role' => 'tl_canvasser', 'is_active' => true]));
    $this->postJson('/price-monitoring/preview', ['rows' => [[]]])->assertOk();
    $form = $this->get('/price-monitoring/preview')->assertOk()
        ->assertSee('<select data-field="category"', false)->assertSee('Add new category');
    foreach (PriceRecord::CATEGORIES as $category) {
        $form->assertSee($category);
    }
    $this->postJson('/price-monitoring', ['rows' => [categoryPriceRow(['category' => '  Medical Equipment  '])]])->assertOk();
    $record = PriceRecord::firstOrFail();
    expect($record->category)->toBe('Medical Equipment');
    $this->postJson('/price-monitoring/preview', ['rows' => [[]]])->assertOk();
    $this->get('/price-monitoring/preview')->assertOk()->assertSee('value="Medical Equipment"', false);
    $this->get('/price-monitoring/'.$record->id.'/edit')->assertOk()->assertSee('Medical Equipment');
    $this->putJson('/price-monitoring/'.$record->id, ['rows' => [categoryPriceRow(['category' => 'Laptop'])]])->assertOk();
    expect($record->refresh()->category)->toBe('Laptop');
    $this->get('/price-monitoring?search=Laptop')->assertOk()->assertSee('Category item')->assertSee('Category');
    $this->get('/price-monitoring?search=Medical')->assertOk()->assertDontSee('Category item');
});

test('optional categories preserve legacy imports and duplicate detection', function () {
    $this->actingAs(User::factory()->create(['role' => 'tl_canvasser', 'is_active' => true]));
    $legacy = categoryPriceRow();
    unset($legacy['category']);
    $this->postJson('/price-monitoring', ['rows' => [$legacy]])->assertOk();
    expect(PriceRecord::firstOrFail()->category)->toBeNull();
    $this->postJson('/price-monitoring', ['rows' => [categoryPriceRow()]])->assertUnprocessable();
    $this->putJson('/price-monitoring/1', ['rows' => [categoryPriceRow()]])->assertOk();
    $this->putJson('/price-monitoring/1', ['rows' => [$legacy]])->assertOk();
    expect(PriceRecord::firstOrFail()->category)->toBe('Office Supplies');
    $this->putJson('/price-monitoring/1', ['rows' => [categoryPriceRow(['category' => ''])]])->assertOk();
    expect(PriceRecord::firstOrFail()->category)->toBeNull();
});

test('category filters combine with search and show records to read only canvassers', function () {
    $this->actingAs(User::factory()->create(['role' => 'tl_canvasser', 'is_active' => true]));
    $this->postJson('/price-monitoring', ['rows' => [
        categoryPriceRow(['particulars' => 'Paper selected']),
        categoryPriceRow(['particulars' => 'Pens selected']),
        categoryPriceRow(['particulars' => 'Paper excluded', 'category' => 'Medical Equipment']),
        categoryPriceRow(['particulars' => 'Legacy item', 'category' => '']),
    ]])->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'canvasser', 'is_active' => true]));

    $this->get('/price-monitoring?category=Office%20Supplies&search=Paper')->assertOk()
        ->assertSee('Paper selected')->assertDontSee('Pens selected')->assertDontSee('Paper excluded')
        ->assertDontSee('Legacy item')->assertSee('All categories')
        ->assertSee('value="Office Supplies" selected', false)
        ->assertSee('value="Medical Equipment"', false);
    $this->get('/price-monitoring?category=Medical%20Equipment')->assertOk()
        ->assertSee('Paper excluded')->assertDontSee('Paper selected');
    $this->get('/price-monitoring')->assertOk()->assertSee('Legacy item')->assertSee('Pens selected');
    $this->get('/price-monitoring?category=Unknown')->assertOk()->assertSee('No canvass records found.');
});

test('category and search filters persist on pagination links', function () {
    $this->actingAs(User::factory()->create(['role' => 'tl_canvasser', 'is_active' => true]));
    $rows = array_map(fn ($number) => categoryPriceRow(['particulars' => 'Paper '.$number]), range(1, 16));
    $this->postJson('/price-monitoring', ['rows' => $rows])->assertOk();
    $response = $this->get('/price-monitoring?category=Office%20Supplies&search=Paper')->assertOk();
    $records = $response->viewData('records');
    expect($records->total())->toBe(16);
    expect($records->url(2))->toContain('category=Office%20Supplies', 'search=Paper', 'page=2');
    $this->get($records->url(2))->assertOk()->assertSee('Paper 1');
});

test('category filters reject invalid input', function () {
    $this->actingAs(User::factory()->create(['role' => 'canvasser', 'is_active' => true]));
    $this->getJson('/price-monitoring?category[]=Laptop')->assertUnprocessable()->assertJsonValidationErrors('category');
    $this->getJson('/price-monitoring?category='.str_repeat('x', 256))->assertUnprocessable()->assertJsonValidationErrors('category');
});

test('invalid categories reject the whole batch', function ($category) {
    $this->actingAs(User::factory()->create(['role' => 'tl_canvasser', 'is_active' => true]));
    $this->postJson('/price-monitoring', ['rows' => [
        categoryPriceRow(), categoryPriceRow(['particulars' => 'Second', 'category' => $category]),
    ]])->assertUnprocessable()->assertJsonValidationErrors('rows.1.category');
    $this->assertDatabaseCount('price_monitoring_records', 0);
})->with([str_repeat('x', 256), ['category' => ['invalid']]]);

test('category migration preserves existing rows and supports rollback', function () {
    $this->actingAs(User::factory()->create(['role' => 'tl_canvasser', 'is_active' => true]));
    $this->postJson('/price-monitoring', ['rows' => [categoryPriceRow()]])->assertOk();
    $record = PriceRecord::firstOrFail();
    $fingerprint = $record->fingerprint;
    $uploadDates = require database_path('migrations/2026_10_02_000004_use_created_at_for_price_archives.php');
    $uploadDates->down();
    $archives = require database_path('migrations/2026_10_02_000003_add_price_record_archives.php');
    $archives->down();
    $migration = require database_path('migrations/2026_10_02_000001_add_category_to_price_monitoring_records.php');
    $migration->down();
    $migration->up();
    expect($record->refresh()->category)->toBeNull();
    expect($record->fingerprint)->toBe($fingerprint);
    expect($record->particulars)->toBe('Category item');
});
