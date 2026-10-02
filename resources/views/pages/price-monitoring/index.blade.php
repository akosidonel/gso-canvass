@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="$title" />
<section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <div class="mb-4 flex flex-wrap items-center gap-4 text-sm">
        <a href="{{ route('price-monitoring.index') }}" @class(['font-medium text-brand-600 dark:text-brand-400', 'underline underline-offset-4' => ! $archived])>{{ __('Active records') }}</a>
        <a href="{{ route('price-monitoring.index', ['view' => 'archived']) }}" @class(['font-medium text-brand-600 dark:text-brand-400', 'underline underline-offset-4' => $archived])>{{ __('Archived records') }}</a>
        @if ($archived)<span class="text-gray-500 dark:text-gray-400">{{ __('Archived records are read-only.') }}</span>@endif
    </div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('price-monitoring.index') }}" x-data class="flex w-full flex-wrap items-center gap-3 xl:w-auto xl:flex-1">
            <input type="hidden" name="view" value="{{ $archived ? 'archived' : 'active' }}">
            <div class="w-full sm:w-56">
                <label for="category" class="sr-only">{{ __('Category') }}</label>
                <select id="category" name="category" @change="$el.form.requestSubmit()" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $option)
                        <option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full sm:w-56">
                <label for="search" class="sr-only">{{ __('Search') }}</label>
                <input id="search" name="search" value="{{ $search }}" maxlength="200" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
            </div>
            <div class="w-full sm:w-32">
                <label for="year" class="sr-only">{{ __('Year') }}</label>
                <select id="year" name="year" @change="$el.form.requestSubmit()" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">{{ __('All years') }}</option>
                    @foreach ($years as $option)<option value="{{ $option }}" @selected((string) $year === (string) $option)>{{ $option }}</option>@endforeach
                </select>
            </div>
            <button class="h-11 rounded-lg bg-brand-500 px-4 text-sm text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Search') }}</button>
            <a href="{{ route('price-monitoring.index', $archived ? ['view' => 'archived'] : []) }}" class="py-3 text-sm text-brand-600 dark:text-brand-400">{{ __('Clear') }}</a>
        </form>
        <div class="flex flex-wrap items-center gap-3 xl:ms-auto">
            <a href="{{ route('price-monitoring.export', array_filter(['view' => $archived ? 'archived' : 'active', 'year' => $year])) }}" class="inline-flex h-11 items-center gap-2 rounded-lg border border-brand-500 px-4 py-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-brand-400 dark:hover:bg-brand-500/15">
                <svg aria-hidden="true" class="h-5 w-5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3v12m-4-4 4 4 4-4M5 16v4a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4" />
                </svg>
                {{ __('Export all to Excel') }}
            </a>
            @can('edit-data')
                <a href="{{ route('price-monitoring.create') }}" class="inline-flex h-11 items-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Paste Excel Data') }}</a>
            @endcan
        </div>
    </div>
    @if (session('status'))
        <p role="status" @if (session('price_record_deleted')) data-delete-success="{{ __('Deleted successfully') }}" data-ok-label="{{ __('OK') }}" @endif class="mb-4 rounded-lg bg-brand-50 p-3 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p role="alert" class="mb-4 text-error-600 dark:text-error-400">{{ $errors->first() }}</p>
    @endif
    <div data-price-list data-copy-success="{{ __('Row copied. Paste it into Excel.') }}" data-copy-error="{{ __('Could not copy. Allow clipboard access and try again.') }}" data-copied-label="{{ __('Copied!') }}">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __(':count records found', ['count' => $records->total()]) }}</p>
        </div>
        <p data-copy-status role="status" class="mb-3 text-sm text-brand-600 dark:text-brand-400"></p>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-sm text-start">
                <thead class="border-b border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="p-3 text-start font-medium">{{ __('Copy') }}</th>
                        @if (! $archived && auth()->user()->can('edit-data'))<th class="p-3 text-start">{{ __('Actions') }}</th>@endif
                        @foreach ($fields as $label)<th class="whitespace-nowrap p-3 text-start font-medium">{{ __($label) }}</th>@endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-300">
                    @forelse ($records as $record)
                        <tr>
                            <td class="p-3">
                                <button type="button" data-copy-row="{{ json_encode(array_map(fn ($key) => $record->$key ?? '', ['qty', 'unit', 'particulars', 'amount'])) }}"
                                    aria-label="{{ __('Copy record :id', ['id' => $record->id]) }}" title="{{ __('Copy row to Excel') }}"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-brand-50 hover:text-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-brand-500/15 dark:hover:text-brand-300">
                                    <svg data-copy-icon aria-hidden="true" class="h-5 w-5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="8" y="8" width="12" height="12" rx="2" />
                                        <path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3" />
                                    </svg>
                                    <svg data-copied-icon aria-hidden="true" class="hidden h-5 w-5 stroke-current text-success-600 dark:text-success-400" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m5 12 4 4L19 6" />
                                    </svg>
                                </button>
                            </td>
                            @if (! $archived && auth()->user()->can('edit-data'))
                                <td class="p-3"><div class="flex items-center gap-3">
                                    <a href="{{ route('price-monitoring.edit', $record->id) }}" class="text-brand-600 dark:text-brand-400">{{ __('Edit') }}</a>
                                    @can('delete-data')
                                        <form method="POST" action="{{ route('price-monitoring.destroy', $record->id) }}" data-confirm-delete="{{ __('Delete this canvass record?') }}"
                                            data-delete-messages="{{ json_encode(['warning' => __('This action cannot be undone.'), 'confirm' => __('Yes, delete record'), 'cancel' => __('Cancel')]) }}">
                                            @csrf @method('DELETE')
                                            <button class="text-error-600 dark:text-error-400">{{ __('Delete') }}</button>
                                        </form>
                                    @endcan
                                </div></td>
                            @endif
                            @foreach ($fields as $key => $label)
                                <td @class(['p-3', 'min-w-64 whitespace-pre-wrap' => in_array($key, ['particulars', 'store']), 'whitespace-nowrap' => !in_array($key, ['particulars', 'store']), 'tabular-nums' => in_array($key, ['qty', 'amount'])])>{{ $record->$key }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($fields) + 1 + (! $archived && auth()->user()->can('edit-data') ? 1 : 0) }}" class="p-10 text-center text-gray-500 dark:text-gray-400">{{ __('No canvass records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $records->links() }}</div>
</section>
@endsection
