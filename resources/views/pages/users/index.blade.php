@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="__('Users')" />
    <div class="space-y-6">
        @if (session('status'))
            <p role="status" class="rounded-lg bg-brand-50 p-4 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p role="alert" class="rounded-lg bg-error-50 p-4 text-error-700 dark:bg-error-500/15 dark:text-error-400">{{ $errors->first() }}</p>
        @endif
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('User accounts') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Online means activity within the last 2 minutes. Refresh to update statuses.') }}</p>
                </div>
                <a href="{{ route('users.create') }}" class="rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Add user') }}</a>
            </div>
            <form method="GET" action="{{ route('users.index') }}" class="mb-5 flex flex-wrap items-end gap-3">
                <div class="w-full sm:w-80">
                    <label for="search" class="mb-1 block text-sm text-gray-700 dark:text-gray-300">{{ __('Search name, email or employee number') }}</label>
                    <input id="search" name="search" value="{{ $search }}" maxlength="100" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:outline-brand-500 dark:border-gray-700 dark:text-white/90">
                </div>
                <button class="h-11 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">{{ __('Search') }}</button>
                <a href="{{ route('users.index') }}" class="py-3 text-sm text-brand-600 dark:text-brand-400">{{ __('Refresh') }}</a>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            @foreach (['Employee number', 'Name', 'Email address', 'Role', 'Account', 'Presence', 'Actions'] as $heading)
                                <th class="whitespace-nowrap px-3 py-3 text-start font-medium">{{ __($heading) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-300">
                        @forelse ($users as $account)
                            <tr>
                                <td class="px-3 py-4">{{ $account->employee_number ?? '—' }}</td>
                                <td class="px-3 py-4 font-medium">{{ $account->name }}</td>
                                <td class="px-3 py-4">{{ $account->email }}</td>
                                <td class="whitespace-nowrap px-3 py-4">{{ __(\App\Models\User::ROLES[$account->role] ?? $account->role) }}</td>
                                <td class="px-3 py-4">{{ $account->is_active ? __('Active') : __('Inactive') }}</td>
                                <td class="px-3 py-4">
                                    <span @class(['rounded-full px-2.5 py-1 text-xs font-medium', 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' => $account->isOnline(), 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => ! $account->isOnline()])>{{ $account->isOnline() ? __('Online') : __('Offline') }}</span>
                                    <p class="mt-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $account->last_seen_at?->diffForHumans() ?? __('No recent activity') }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex items-center gap-4">
                                        <a href="{{ route('users.edit', $account) }}" class="text-brand-600 dark:text-brand-400">{{ __('Edit') }}</a>
                                        @if ($account->id !== auth()->id())
                                            <form method="POST" action="{{ route('users.destroy', $account) }}" data-confirm-delete="{{ __('Delete :name? This removes their access to the system.', ['name' => $account->name]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-error-600 dark:text-error-400">{{ __('Delete') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-10 text-center text-gray-500 dark:text-gray-400">{{ __('No users found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $users->links() }}</div>
        </section>
    </div>
@endsection
