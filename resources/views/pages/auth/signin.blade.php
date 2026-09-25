@extends('layouts.auth')

@section('content')
    <main class="flex min-h-screen items-center justify-center px-6 py-12">
        <section aria-labelledby="login-title" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-8 text-center">
                <p class="text-2xl font-semibold tracking-tight text-brand-600 dark:text-brand-400 sm:text-title-sm">{{ __('GSO Monitoring') }}</p>
            </div>
            <h1 id="login-title" class="text-title-sm font-semibold">{{ __('Sign in') }}</h1>
            <p class="mb-8 mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Enter your employee number and password to access the system.') }}</p>
            @if ($errors->any())
                <div id="login-errors" role="alert" class="mb-5 rounded-lg border border-error-200 bg-error-50 p-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="employee_number" class="mb-2 block text-sm font-medium">{{ __('Employee number') }}</label>
                    <input id="employee_number" name="employee_number" type="text" value="{{ old('employee_number') }}" required minlength="6" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" data-digits-only title="{{ __('Employee number must be exactly 6 digits, with no letters or special characters.') }}" autocomplete="username" autofocus
                        @if ($errors->any()) aria-describedby="login-errors" aria-invalid="true" @endif
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm focus:border-brand-500 focus:outline-brand-500 dark:border-gray-700 dark:text-white/90">
                </div>
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium">{{ __('Password') }}</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required maxlength="1024" autocomplete="current-password"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent pe-20 ps-4 text-sm focus:border-brand-500 focus:outline-brand-500 dark:border-gray-700 dark:text-white/90">
                        <button type="button" data-toggle-password data-show="{{ __('Show') }}" data-hide="{{ __('Hide') }}" aria-controls="password" aria-pressed="false"
                            class="absolute end-3 top-0 h-11 text-sm font-medium text-brand-600 dark:text-brand-400">{{ __('Show') }}</button>
                    </div>
                </div>
                <button type="submit" class="h-11 w-full rounded-lg bg-brand-500 text-sm font-medium text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Sign in') }}</button>
            </form>
            <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">{{ __('For an account or password assistance, contact your System Admin.') }}</p>
        </section>
    </main>
@endsection
