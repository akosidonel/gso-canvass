<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function accountData(array $changes = []): array
{
    return array_merge([
        'employee_number' => '001234',
        'name' => 'New Canvasser', 'email' => 'new@example.com', 'role' => 'canvasser',
        'is_active' => '1', 'password' => 'abcde', 'password_confirmation' => 'abcde',
    ], $changes);
}

test('only administrators may access account management endpoints', function (string $role) {
    $actor = User::factory()->create(['role' => $role, 'is_active' => true]);
    $target = User::factory()->create();
    $this->actingAs($actor);
    $this->get('/users')->assertForbidden();
    $this->get('/users/create')->assertForbidden();
    $this->get('/users/'.$target->id.'/edit')->assertForbidden();
    $this->post('/users', accountData())->assertForbidden();
    $this->put('/users/'.$target->id, accountData())->assertForbidden();
    $this->delete('/users/'.$target->id)->assertForbidden();
})->with(['tl_canvasser', 'canvasser']);

test('administrator can create search and edit users', function () {
    $admin = User::factory()->create(['role' => 'system_admin', 'is_active' => true]);
    $this->actingAs($admin);
    $this->get('/users/create')->assertOk();
    $this->post('/users', accountData())->assertRedirect('/users');
    $user = User::where('email', 'new@example.com')->firstOrFail();
    expect($user->employee_number)->toBe('001234');
    $this->get('/users?search=001234')->assertOk()->assertSee('New Canvasser');
    expect(Hash::check('abcde', $user->password))->toBeTrue();
    $this->get('/users?search=new@example.com')->assertOk()->assertSee('New Canvasser');
    $this->get('/users/'.$user->id.'/edit')->assertOk();
    $this->put('/users/'.$user->id, accountData(['role' => 'tl_canvasser', 'password' => '', 'password_confirmation' => '']))->assertRedirect('/users');
    expect($user->fresh()->role)->toBe('tl_canvasser');
    expect(Hash::check('abcde', $user->fresh()->password))->toBeTrue();
});

test('administrator cannot delete self or remove the final active administrator', function () {
    $admin = User::factory()->create(['role' => 'system_admin', 'is_active' => true]);
    $this->actingAs($admin);
    $this->delete('/users/'.$admin->id)->assertSessionHasErrors('user');
    $this->put('/users/'.$admin->id, accountData(['email' => $admin->email]))->assertSessionHasErrors('role');
    $this->put('/users/'.$admin->id, accountData(['email' => $admin->email, 'role' => 'system_admin', 'is_active' => '0']))->assertSessionHasErrors('role');
    expect($admin->fresh()->canAccessSystem())->toBeTrue();
});

test('deleting another user revokes login and retains the record', function () {
    $admin = User::factory()->create(['role' => 'system_admin', 'is_active' => true]);
    $user = User::factory()->create(['role' => 'canvasser', 'is_active' => true]);
    $this->actingAs($admin)->delete('/users/'.$user->id)->assertRedirect('/users');
    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->post('/logout');
    $this->post('/signin', ['employee_number' => $user->employee_number, 'password' => 'password'])->assertSessionHasErrors('employee_number');
    $this->assertGuest();
});

test('duplicate emails invalid roles and weak passwords are rejected', function () {
    $admin = User::factory()->create(['role' => 'system_admin', 'is_active' => true]);
    $this->actingAs($admin)->post('/users', accountData([
        'email' => $admin->email, 'role' => 'owner', 'password' => 'tiny', 'password_confirmation' => 'tiny',
    ]))->assertSessionHasErrors(['email', 'role', 'password']);
});

test('presence expires and heartbeat restores it while logout clears it', function () {
    $user = User::factory()->create(['role' => 'canvasser', 'is_active' => true]);
    expect($user->isOnline())->toBeFalse();
    $this->actingAs($user)->post('/presence')->assertNoContent();
    expect($user->fresh()->isOnline())->toBeTrue();
    $this->travel(121)->seconds();
    expect($user->fresh()->isOnline())->toBeFalse();
    $this->post('/presence')->assertNoContent();
    expect($user->fresh()->isOnline())->toBeTrue();
    $this->post('/logout')->assertRedirect('/signin');
    expect($user->fresh()->isOnline())->toBeFalse();
});

test('employee number must be six digits and unique', function () {
    $admin = User::factory()->create(['role' => 'system_admin', 'is_active' => true, 'employee_number' => '123456']);
    $this->actingAs($admin);
    foreach (['', '12345', '1234567', '12345A', '123456'] as $number) {
        $this->post('/users', accountData(['employee_number' => $number]))->assertSessionHasErrors('employee_number');
    }
});
