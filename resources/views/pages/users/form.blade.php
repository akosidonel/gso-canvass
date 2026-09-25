@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />
    <section class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h2>
        @if ($errors->any())
            <ul role="alert" class="mb-5 space-y-1 rounded-lg bg-error-50 p-4 text-sm text-error-700 dark:bg-error-500/15 dark:text-error-400">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        <form method="POST" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}" class="space-y-5">
            @csrf
            @if ($account->exists) @method('PUT') @endif
            <div>
                <label for="employee_number" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Employee number') }}</label>
                <input id="employee_number" name="employee_number" data-digits-only type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" required value="{{ old('employee_number', $account->employee_number) }}" aria-describedby="employee-number-help" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-gray-800 focus:outline-brand-500 dark:border-gray-700 dark:text-white/90">
                <p id="employee-number-help" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Enter exactly 6 digits, including any leading zeros.') }}</p>
            </div>
            @foreach (['name' => 'Full name', 'email' => 'Email address'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __($label) }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field, $account->$field) }}" required maxlength="{{ $field === 'email' ? 254 : 255 }}" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-gray-800 focus:outline-brand-500 dark:border-gray-700 dark:text-white/90">
                </div>
            @endforeach
            <div>
                <label for="role" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Role') }}</label>
                <select id="role" name="role" required class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-gray-800 focus:outline-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    @foreach (\App\Models\User::ROLES as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $account->role ?? 'canvasser') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="is_active" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Account status') }}</label>
                <select id="is_active" name="is_active" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-gray-800 focus:outline-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="1" @selected((string) old('is_active', (int) $account->is_active) === '1')>{{ __('Active') }}</option>
                    <option value="0" @selected((string) old('is_active', (int) $account->is_active) === '0')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $account->exists ? __('Leave password blank to keep the current password.') : __('Use at least 5 characters for the password.') }}</p>
            @foreach (['password' => 'Password', 'password_confirmation' => 'Confirm password'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __($label) }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="password" autocomplete="new-password" minlength="5" maxlength="1024" @required(! $account->exists) class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-gray-800 focus:outline-brand-500 dark:border-gray-700 dark:text-white/90">
                </div>
            @endforeach
            <div class="flex items-center gap-4 pt-2">
                <button class="rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Save user') }}</button>
                <a href="{{ route('users.index') }}" class="text-sm text-gray-600 dark:text-gray-400">{{ __('Cancel') }}</a>
            </div>
        </form>
    </section>
@endsection
