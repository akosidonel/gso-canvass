@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="$title" />
<section data-price-editor data-url="{{ $record ? route('price-monitoring.update', $record->id) : route('price-monitoring.store') }}" data-method="{{ $record ? 'PUT' : 'POST' }}" data-record="{{ json_encode($record?->only(array_keys($fields))) }}" data-initial-rows="{{ json_encode($rows) }}"
    data-messages="{{ json_encode(collect(['Add new category', 'Category name', 'Add category', 'Enter a category name.', 'Use at most 255 characters.', 'Save records?', 'Save :count record(s)?', 'Yes, save records', 'Cancel', 'Saved successfully', 'Canvass records saved.', 'OK', 'Add the pasted rows to the preview before saving.', 'Paste copied Excel rows first.', 'Each row must have :count columns. Check the selected column order.', 'Use at most 500 rows per batch.', 'Review the highlighted fields.', 'Saving records…', 'Unable to save. Your rows are still here; check your connection and try again.', 'Your session expired. Sign in again in another tab, then retry.', 'No rows to save.', 'Unsaved changes will be lost. Leave this page?', 'Preview ready. Review the rows before saving.', 'Use values copied from Excel, not formula text.', 'The pasted cells do not fit. Check the selected column order or start in an earlier column.'])->mapWithKeys(fn ($message) => [$message => __($message)])) }}"
    class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <div>
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Brand/Model, Store and Category are optional.') }}</p>
    </div>
    @unless ($record)
        <button type="button" data-add-row class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">{{ __('Add blank row') }}</button>
    @endunless
    <p data-editor-status role="status" aria-live="polite" class="whitespace-pre-line text-sm text-brand-700 dark:text-brand-300"></p>
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-start text-sm">
            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300"><tr>
                <th class="p-2 text-start">#</th>
                @unless ($record)<th class="p-2 text-start">{{ __('Actions') }}</th>@endunless
                @foreach ($fields as $label)<th class="whitespace-nowrap p-2 text-start font-medium">{{ __($label) }}</th>@endforeach
            </tr></thead>
            <tbody data-preview-rows></tbody>
        </table>
    </div>
    <template data-row-template>
        <tr class="border-b border-gray-100 dark:border-gray-800">
            <td data-row-number class="p-2 text-gray-500 dark:text-gray-400"></td>
            @unless ($record)<td class="p-2"><button type="button" data-remove-row class="text-sm text-error-600 dark:text-error-400">{{ __('Remove') }}</button></td>@endunless
            @foreach ($fields as $key => $label)
                <td class="p-2 align-top">
                    @if ($key === 'category')
                        <select data-field="category" aria-label="{{ __('Category') }}" class="h-11 w-60 rounded-lg border border-gray-300 bg-white p-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            <option value="">{{ __('Select category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}">{{ __($category) }}</option>
                            @endforeach
                            <option value="" data-add-category>{{ __('Add new category…') }}</option>
                        </select>
                    @else
                    <textarea data-field="{{ $key }}" aria-label="{{ __($label) }}" rows="2"
                        @class(['rounded-lg border border-gray-300 bg-transparent p-2 text-sm text-gray-800 dark:border-gray-700 dark:text-gray-200', 'w-72' => in_array($key, ['particulars', 'store']), 'w-40' => !in_array($key, ['particulars', 'store'])])></textarea>
                    @endif
                </td>
            @endforeach
        </tr>
    </template>
    <div class="flex items-center gap-4">
        <button type="button" data-save-rows class="rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Save Records') }}</button>
        <a href="{{ route('price-monitoring.index') }}" class="text-sm text-gray-600 dark:text-gray-300">{{ __('Cancel') }}</a>
    </div>
</section>
@endsection
