@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="$title" />
<section data-price-paste data-preview-url="{{ route('price-monitoring.preview.prepare') }}" data-fields="{{ json_encode(array_keys($fields)) }}"
    data-messages="{{ json_encode(collect(['Paste copied Excel rows first.', 'Each row must have :count columns. Check the selected column order.', 'Use at most 500 rows per batch.', 'Unable to open preview. Your pasted rows are still here; try again.', 'Your session expired. Sign in again in another tab, then retry.', 'The pasted cells do not fit. Check the selected column order or start in an earlier column.'])->mapWithKeys(fn ($message) => [$message => __($message)])) }}"
    class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h2>
        <div class="space-y-3">
            <label for="paste-layout" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Copied column order') }}</label>
            <select id="paste-layout" data-paste-layout class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="system">{{ __('System: Qty, Unit, Particulars, Amount, Department, Control number, Brand/Model, Store, Canvasser') }}</option>
                <option value="system-category">{{ __('System with Category: Category, Qty, Unit, Particulars, Amount, Department, Control number, Brand/Model, Store, Canvasser') }}</option>
                <option value="workbook">{{ __('Price Monitoring Excel: Qty, Unit, Particulars, Amount, Department, Control number, Item, Brand/Model, Canvasser, Canvass, Store') }}</option>
            </select>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('For the 11-column Excel layout, Item and Canvass are not saved. Copy values, with or without the header row. Maximum 500 rows per batch.') }}</p>
            <div data-paste-grid data-labels="{{ json_encode(collect($fields)->merge(['item' => 'Item', 'canvass' => 'Canvass'])->map(fn ($label) => __($label))) }}" class="space-y-2">
                <p id="paste-grid-label" class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Paste copied rows here') }}</p>
                <p id="paste-grid-help" class="text-sm text-gray-500 dark:text-gray-400">{{ __('Click a cell and paste from Excel, or type directly. Tab moves across cells; Enter moves down. Shift + Enter adds a line within a cell.') }}</p>
                <div class="custom-scrollbar max-h-80 overflow-auto rounded-lg border border-gray-300 dark:border-gray-700">
                    <table aria-labelledby="paste-grid-label" aria-describedby="paste-grid-help" class="w-full border-collapse text-start text-sm">
                        <thead data-paste-head class="sticky top-0 z-1 bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300"></thead>
                        <tbody data-paste-rows></tbody>
                    </table>
                </div>
                <button type="button" data-clear-paste class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Clear cells') }}</button>
                <template data-paste-cell-template>
                    <td class="border border-gray-200 p-0 dark:border-gray-700">
                        <textarea rows="2" spellcheck="false" class="block min-h-12 w-40 resize-y border-0 bg-white px-2 py-1.5 text-sm text-gray-800 focus:relative focus:z-1 focus:outline-2 focus:outline-brand-500 dark:bg-gray-900 dark:text-gray-200 dark:focus:outline-brand-400"></textarea>
                    </td>
                </template>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="button" data-preview-paste class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Continue to preview') }}</button>
                <button type="button" data-preview-blank class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">{{ __('Start with a blank row') }}</button>
            </div>
        </div>
    <p data-editor-status role="status" aria-live="polite" class="whitespace-pre-line text-sm text-brand-700 dark:text-brand-300"></p>
    <a href="{{ route('price-monitoring.index') }}" class="inline-block text-sm text-gray-600 dark:text-gray-300">{{ __('Cancel') }}</a>
</section>
@endsection
