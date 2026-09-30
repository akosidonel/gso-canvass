@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="$title" />
<section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    @can('edit-data')
        <div class="mb-6 flex justify-end">
            <a href="{{ route('price-monitoring.create') }}" class="rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Paste Excel Data') }}</a>
        </div>
    @endcan
    @if (session('status'))
        <p role="status" @if (session('price_record_deleted')) data-delete-success="{{ __('Deleted successfully') }}" data-ok-label="{{ __('OK') }}" @endif class="mb-4 rounded-lg bg-brand-50 p-3 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p role="alert" class="mb-4 text-error-600 dark:text-error-400">{{ $errors->first() }}</p>
    @endif
    <form method="GET" action="{{ route('price-monitoring.index') }}" class="mb-5 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-96">
            <label for="search" class="mb-1 block text-sm text-gray-700 dark:text-gray-300">{{ __('Search item, department, control number, store or canvasser') }}</label>
            <input id="search" name="search" value="{{ $search }}" maxlength="200" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
        </div>
        <button class="h-11 rounded-lg bg-brand-500 px-4 text-sm text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Search') }}</button>
        <a href="{{ route('price-monitoring.index') }}" class="py-3 text-sm text-brand-600 dark:text-brand-400">{{ __('Clear') }}</a>
    </form>
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
                        @foreach ($fields as $label)<th class="whitespace-nowrap p-3 text-start font-medium">{{ __($label) }}</th>@endforeach
                        @can('edit-data')<th class="p-3 text-start">{{ __('Actions') }}</th>@endcan
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
                            @foreach ($fields as $key => $label)
                                <td @class(['p-3', 'min-w-64 whitespace-pre-wrap' => in_array($key, ['particulars', 'store']), 'whitespace-nowrap' => !in_array($key, ['particulars', 'store']), 'tabular-nums' => in_array($key, ['qty', 'amount'])])>{{ $record->$key }}</td>
                            @endforeach
                            @can('edit-data')
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
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($fields) + 1 + (auth()->user()->can('edit-data') ? 1 : 0) }}" class="p-10 text-center text-gray-500 dark:text-gray-400">{{ __('No canvass records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $records->links() }}</div>
</section>
@endsection
