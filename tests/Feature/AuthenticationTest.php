<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('login renders a real form and registration is unavailable', function () {
    $this->get('/signin')->assertOk()->assertSee('name="employee_number"', false)->assertDontSee('name="email"', false)->assertSee('pattern="[0-9]{6}"', false)->assertSee('name="password"', false)->assertSee('name="_token"', false);
    $this->get('/signup')->assertNotFound();
    $this->post('/signup', [])->assertNotFound();
});

test('all application pages require authentication', function () {
    foreach (['/', '/price-monitoring', '/basic-tables', '/locale/en', '/form-elements'] as $url) {
        $this->get($url)->assertRedirect('/signin');
    }
});

test('active users can log in and log out', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);
    $this->withSession(['marker' => 'before-login']);
    $sessionId = session()->getId();
    $this->post('/signin', ['employee_number' => $user->employee_number, 'password' => 'password'])->assertRedirect('/price-monitoring');
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($sessionId);
    $this->get('/price-monitoring')->assertOk()->assertSee(e($user->name), false);
    $this->get('/signin')->assertRedirect('/price-monitoring');
    $this->post('/logout')->assertRedirect('/signin');
    $this->assertGuest();
    expect(session()->has('marker'))->toBeFalse();
    $this->get('/')->assertRedirect('/signin');
})->with(array_keys(User::ROLES));

test('invalid credentials and inactive users get the same error', function () {
    $user = User::factory()->create(['is_active' => false]);
    foreach ([[$user->employee_number, 'password'], [$user->employee_number, 'wrong'], ['999999', 'password']] as [$employee_number, $password]) {
        $this->post('/signin', compact('employee_number', 'password'))->assertSessionHasErrors([
            'employee_number' => 'The provided credentials could not be verified.',
        ])->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }
});

test('repeated login failures are throttled even with the correct password', function () {
    $user = User::factory()->create(['is_active' => true]);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/signin', ['employee_number' => $user->employee_number, 'password' => 'wrong'])->assertSessionHasErrors('employee_number');
    }
    $this->post('/signin', ['employee_number' => $user->employee_number, 'password' => 'password'])->assertSessionHasErrors('employee_number');
    $this->assertGuest();
    $this->travel(61)->seconds();
    $this->post('/signin', ['employee_number' => $user->employee_number, 'password' => 'password'])->assertRedirect('/price-monitoring');
    $this->assertAuthenticatedAs($user);
});

test('deactivating an account ends its access', function () {
    $user = User::factory()->create(['is_active' => true]);
    $this->actingAs($user->refresh())->get('/price-monitoring')->assertOk();
    $user->is_active = false;
    $user->save();
    $this->get('/')->assertRedirect('/signin');
    $this->assertGuest();
});

test('permissions are enforced by server gates', function (string $role, bool $edit, bool $admin) {
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);
    $this->actingAs($user);
    expect(Gate::allows('view-data'))->toBeTrue();
    expect(Gate::allows('edit-data'))->toBe($edit);
    expect(Gate::allows('delete-data'))->toBe($admin);
    Route::middleware(['web', 'auth', 'active', 'can:manage-users'])->get('/test-admin', fn () => 'allowed');
    $this->get('/test-admin')->assertStatus($admin ? 200 : 403);
})->with([
    ['system_admin', true, true],
    ['tl_canvasser', true, false],
    ['canvasser', false, false],
]);

test('unknown roles cannot sign in', function () {
    $user = User::factory()->create(['role' => 'unknown', 'is_active' => true]);
    $this->post('/signin', ['employee_number' => $user->employee_number, 'password' => 'password'])->assertSessionHasErrors('employee_number');
    $this->assertGuest();
});

test('login and logout reject requests without a csrf token', function () {
    $this->app['env'] = 'local';
    $this->post('/signin', ['employee_number' => '001234', 'password' => 'password'])->assertStatus(419);
    $user = User::factory()->create(['role' => 'canvasser', 'is_active' => true]);
    $this->actingAs($user)->post('/logout')->assertStatus(419);
});

test('administrator setup hashes the password and activates the account', function () {
    $this->artisan('gso:create-admin')
        ->expectsQuestion('Full name', 'System Owner')
        ->expectsQuestion('Employee number (6 digits)', '000001')
        ->expectsQuestion('Email address', 'OWNER@example.com')
        ->expectsQuestion('Password (at least 5 characters)', 'abcde')
        ->expectsQuestion('Confirm password', 'abcde')
        ->assertSuccessful();
    $user = User::firstOrFail();
    expect($user->email)->toBe('owner@example.com');
    expect($user->role)->toBe('system_admin');
    expect($user->is_active)->toBeTrue();
    expect(Hash::check('abcde', $user->password))->toBeTrue();
});


test('employee number login rejects anything except exactly six ASCII digits', function ($number) {
    $this->post('/signin', ['employee_number' => $number, 'password' => 'password'])
        ->assertSessionHasErrors('employee_number');
    $this->assertGuest();
})->with(['12345', '1234567', 'abc123', '12-345', '12 345', '123.45', '１２３４５６', '', ['123456']]);

test('employee number login preserves leading zeros and no longer accepts email', function () {
    $user = User::factory()->create(['employee_number' => '001234', 'role' => 'canvasser', 'is_active' => true]);
    $this->post('/signin', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('employee_number');
    $this->assertGuest();
    $this->post('/signin', ['employee_number' => '001234', 'password' => 'password'])->assertRedirect('/price-monitoring');
    $this->assertAuthenticatedAs($user);
});


test('removed template pages are unavailable and home opens price monitoring', function () {
    $user = User::factory()->create(['role' => 'canvasser', 'is_active' => true]);
    $this->actingAs($user)->get('/')->assertRedirect('/price-monitoring');
    foreach (['/dashboard', '/calendar', '/profile'] as $url) {
        $this->get($url)->assertNotFound();
    }
    $this->get('/price-monitoring')->assertOk()
        ->assertDontSee('Purchase Plan')->assertDontSee('Ecommerce')->assertDontSee('User Profile');
});
