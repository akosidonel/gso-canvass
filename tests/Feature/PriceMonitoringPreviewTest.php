<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('pasting and reviewing are separate pages and preview does not save data', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));
    $this->get('/price-monitoring/create')->assertOk()
        ->assertSee('data-price-paste', false)->assertSee('Continue to preview')
        ->assertDontSee('data-preview-rows', false)->assertDontSee('data-save-rows', false);
    $rows = [[
        'category' => 'Office Supplies', 'qty' => '2', 'unit' => 'pcs', 'particulars' => 'Preview item',
        'amount' => '10.20', 'department' => 'GSO', 'control_number' => '001-26', 'canvasser' => 'Staff',
    ]];
    $this->postJson('/price-monitoring/preview', ['rows' => $rows])->assertOk()
        ->assertJsonPath('redirect', route('price-monitoring.preview'))
        ->assertSessionHas('price_monitoring_preview', $rows);
    $this->assertDatabaseCount('price_monitoring_records', 0);
    $this->get('/price-monitoring/preview')->assertOk()
        ->assertSee('Preview canvass records')->assertSee('data-preview-rows', false)
        ->assertSee('Preview item')->assertSee('data-save-rows', false)
        ->assertSeeInOrder(['data-field="category"', 'data-field="qty"'], false)
        ->assertDontSee('data-paste-grid', false);
    $this->get('/price-monitoring/preview')->assertOk()->assertSee('Preview item');
    $this->postJson('/price-monitoring', ['rows' => $rows])->assertOk()
        ->assertSessionMissing('price_monitoring_preview');
    $this->assertDatabaseCount('price_monitoring_records', 1);
    $this->get('/price-monitoring/preview')->assertRedirect('/price-monitoring/create');
})->with(['tl_canvasser', 'system_admin']);

test('incomplete preview rows can be reviewed but are validated when saving', function () {
    $this->actingAs(User::factory()->create(['role' => 'tl_canvasser', 'is_active' => true]));
    $this->get('/price-monitoring/preview')->assertRedirect('/price-monitoring/create');
    $this->postJson('/price-monitoring/preview', ['rows' => [[]]])->assertOk();
    $this->get('/price-monitoring/preview')->assertOk();
    $this->postJson('/price-monitoring', ['rows' => [[]]])->assertUnprocessable()
        ->assertSessionHas('price_monitoring_preview');
    $this->assertDatabaseCount('price_monitoring_records', 0);
});

test('preview endpoints require edit permission', function () {
    $this->get('/price-monitoring/preview')->assertRedirect('/signin');
    $this->postJson('/price-monitoring/preview', ['rows' => [[]]])->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'canvasser', 'is_active' => true]));
    $this->get('/price-monitoring/preview')->assertForbidden();
    $this->postJson('/price-monitoring/preview', ['rows' => [[]]])->assertForbidden();
});

test('invalid preview requests do not replace the previous draft', function () {
    $this->actingAs(User::factory()->create(['role' => 'tl_canvasser', 'is_active' => true]));
    $this->postJson('/price-monitoring/preview', ['rows' => [['particulars' => 'Existing draft']]])->assertOk();
    foreach ([[], array_fill(0, 501, []), [['qty' => ['invalid']]], [['particulars' => str_repeat('x', 10001)]], [['unexpected' => 'value']]] as $rows) {
        $this->postJson('/price-monitoring/preview', ['rows' => $rows])->assertUnprocessable();
    }
    $this->get('/price-monitoring/preview')->assertOk()->assertSee('Existing draft');
    $this->assertDatabaseCount('price_monitoring_records', 0);
});
