<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAccounts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '';

        return view('pages.users.index', ['users' => UserAccounts::listing($search), 'search' => $search, 'title' => __('Users')]);
    }

    public function create()
    {
        $account = new User;
        $account->is_active = true;

        return view('pages.users.form', ['account' => $account, 'title' => __('Add user')]);
    }

    public function edit(User $user)
    {
        return view('pages.users.form', ['account' => $user, 'title' => __('Edit user')]);
    }

    public function store(Request $request)
    {
        UserAccounts::save($this->validated($request), $request->user());

        return redirect()->route('users.index')->with('status', __('User created.'));
    }

    public function update(Request $request, User $user)
    {
        UserAccounts::save($this->validated($request, $user), $request->user(), $user);

        return redirect()->route('users.index')->with('status', __('User updated.'));
    }

    public function destroy(Request $request, User $user)
    {
        UserAccounts::delete($user, $request->user());

        return redirect()->route('users.index')->with('status', __('User deleted.'));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_number' => ['required', 'string', 'regex:/\A[0-9]{6}\z/', Rule::unique('users')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'confirmed', Password::min(5), 'max:1024'],
        ]);
    }
}
