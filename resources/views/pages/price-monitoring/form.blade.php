@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="$title" />
<section data-price-editor data-url="{{ $record ? route('price-monitoring.update', $record->id) : route('price-monitoring.store') }}" data-method="{{ $record ? 'PUT' : 'POST' }}" data-record="{{ json_encode($record?->only(array_keys($fields))) }}"
    data-messages="{{ json_encode(collect(['Add the pasted rows to the preview before saving.', 'Paste copied Excel rows first.', 'Each row must have :count columns. Check the selected column order.', 'Use at most 500 rows per batch.', 'Review the highlighted fields.', 'Saving records…', 'Unable to save. Your rows are still here; check your connection and try again.', 'Your session expired. Sign in again in another tab, then retry.', 'No rows to save.', 'Unsaved changes will be lost. Leave this page?', 'Preview ready. Review the rows before saving.', 'Use values copied from Excel, not formula text.'])->mapWithKeys(fn ($message) => [$message => __($message)])) }}"
    class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <div>
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Total is calculated as Qty × Amount. Brand/Model and Store are optional.') }}</p>
    </div>
    @unless ($record)
        <div class="space-y-3">
            <label for="paste-layout" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Copied column order') }}</label>
            <select id="paste-layout" data-paste-layout class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="system">{{ __('System: Qty, Unit, Brand/Model, Particulars, Amount, Total, Department, Control number, Store, Canvasser') }}</option>
                <option value="workbook">{{ __('Price Monitoring Excel: Qty, Unit, Particulars, Amount, Total, Department, Control number, Item, Brand/Model, Canvasser, Canvass, Store') }}</option>
            </select>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('For the 12-column Excel layout, Item and Canvass are not saved. Copy values, with or without the header row. Maximum 500 rows per batch.') }}</p>
            <label for="excel-paste" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Paste copied rows here') }}</label>
            <textarea id="excel-paste" data-paste-source rows="5" class="w-full rounded-lg border border-gray-300 bg-transparent p-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90"></textarea>
            <div class="flex flex-wrap gap-3">
                <button type="button" data-preview-paste class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Add pasted rows to preview') }}</button>
                <button type="button" data-add-row class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">{{ __('Add blank row') }}</button>
            </div>
        </div>
    @endunless
    <p data-editor-status role="status" aria-live="polite" class="whitespace-pre-line text-sm text-brand-700 dark:text-brand-300"></p>
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-start text-sm">
            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300"><tr>
                <th class="p-2 text-start">#</th>
                @foreach ($fields as $label)<th class="whitespace-nowrap p-2 text-start font-medium">{{ __($label) }}</th>@endforeach
                @unless ($record)<th class="p-2 text-start">{{ __('Actions') }}</th>@endunless
            </tr></thead>
            <tbody data-preview-rows></tbody>
        </table>
    </div>
    <template data-row-template>
        <tr class="border-b border-gray-100 dark:border-gray-800">
            <td data-row-number class="p-2 text-gray-500 dark:text-gray-400"></td>
            @foreach ($fields as $key => $label)
                <td class="p-2 align-top">
                    <textarea data-field="{{ $key }}" aria-label="{{ __($label) }}" rows="2" @readonly($key === 'total')
                        @class(['rounded-lg border border-gray-300 bg-transparent p-2 text-sm text-gray-800 dark:border-gray-700 dark:text-gray-200', 'w-72' => in_array($key, ['particulars', 'store']), 'w-40' => !in_array($key, ['particulars', 'store']), 'bg-gray-50 dark:bg-gray-800' => $key === 'total'])></textarea>
                </td>
            @endforeach
            @unless ($record)<td class="p-2"><button type="button" data-remove-row class="text-sm text-error-600 dark:text-error-400">{{ __('Remove') }}</button></td>@endunless
        </tr>
    </template>
    <div class="flex items-center gap-4">
        <button type="button" data-save-rows class="rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Save Records') }}</button>
        <a href="{{ route('price-monitoring.index') }}" class="text-sm text-gray-600 dark:text-gray-300">{{ __('Cancel') }}</a>
    </div>
</section>
@endsection
