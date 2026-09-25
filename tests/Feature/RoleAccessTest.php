<?php

use App\Models\User;
use App\Services\PriceMonitoring;
use App\Services\UserAccounts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('price controls match each role and forged requests cannot elevate access', function (string $role, bool $edit, bool $admin) {
    $actor = User::factory()->create(['role' => $role, 'is_active' => true]);
    $this->actingAs($actor);
    $page = $this->get('/price-monitoring')->assertOk();
    $edit ? $page->assertSee('Paste Excel Data') : $page->assertDontSee('Paste Excel Data');
    $admin ? $page->assertSee('href="/users"', false) : $page->assertDontSee('href="/users"', false);

    if (! $edit) {
        $this->get('/price-monitoring/1/edit')->assertForbidden();
        $this->postJson('/price-monitoring', ['role' => 'system_admin', 'rows' => []])->assertForbidden();
        $this->patchJson('/users/'.$actor->id, ['role' => 'system_admin'])->assertForbidden();
        expect($actor->fresh()->role)->toBe($role);
        $this->assertDatabaseCount('price_monitoring_records', 0);
    }
})->with([
    ['system_admin', true, true],
    ['tl_canvasser', true, false],
    ['canvasser', false, false],
]);

test('a role downgrade applies on the next request without signing out', function () {
    $actor = User::factory()->create(['role' => 'system_admin', 'is_active' => true]);
    $this->actingAs($actor)->get('/users')->assertOk();
    $actor->fresh()->forceFill(['role' => 'canvasser'])->save();

    $this->get('/users')->assertForbidden();
    $this->postJson('/price-monitoring', ['rows' => []])->assertForbidden();
    $this->get('/price-monitoring')->assertOk()->assertDontSee('Paste Excel Data');
});

test('revoked accounts lose an existing session including JSON requests', function (string $change) {
    $actor = User::factory()->create(['role' => 'system_admin', 'is_active' => true]);
    $this->actingAs($actor)->get('/price-monitoring')->assertOk();
    $account = $actor->fresh();
    match ($change) {
        'inactive' => $account->forceFill(['is_active' => false])->save(),
        'unknown' => $account->forceFill(['role' => 'unknown'])->save(),
        'deleted' => $account->delete(),
    };

    $this->postJson('/price-monitoring', ['rows' => []])->assertUnauthorized();
    $this->assertGuest();
    $this->assertDatabaseCount('price_monitoring_records', 0);
})->with(['inactive', 'unknown', 'deleted']);

test('services reject unauthorized mutations even without route middleware', function (string $role) {
    $actor = User::factory()->create(['role' => $role, 'is_active' => true]);
    $target = User::factory()->create();

    expect(fn () => UserAccounts::save([], $actor))->toThrow(AuthorizationException::class);
    expect(fn () => UserAccounts::delete($target, $actor))->toThrow(AuthorizationException::class);
    expect(fn () => PriceMonitoring::delete(1, $actor))->toThrow(AuthorizationException::class);
    if ($role === 'canvasser') {
        expect(fn () => PriceMonitoring::save([], $actor))->toThrow(AuthorizationException::class);
    }
    expect($target->fresh())->not->toBeNull();
})->with(['tl_canvasser', 'canvasser']);

test('services use the current account permissions instead of a stale administrator object', function () {
    $actor = User::factory()->create(['role' => 'system_admin', 'is_active' => true]);
    $actor->fresh()->forceFill(['role' => 'canvasser'])->save();

    expect(fn () => PriceMonitoring::save([], $actor))->toThrow(AuthorizationException::class);
    expect(fn () => UserAccounts::save([], $actor))->toThrow(AuthorizationException::class);
    $actor->fresh()->delete();
    expect(fn () => PriceMonitoring::delete(1, $actor))->toThrow(AuthorizationException::class);
});
