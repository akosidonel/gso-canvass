@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="$title" />
<div class="space-y-6">
    @if (session('status'))
        <p role="status" class="rounded-lg bg-brand-50 p-3 text-sm text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p role="alert" class="rounded-lg bg-error-50 p-3 text-sm text-error-700 dark:bg-error-500/15 dark:text-error-400">{{ $errors->first() }}</p>
    @endif
    <x-common.component-card :title="__('Archive by year')" :desc="__('Archive a completed year based on when records were uploaded. Records stay searchable and can be restored.')">
        <form method="GET" action="{{ route('price-monitoring.archives.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="archive-year" class="mb-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Year') }}</label>
                <select id="archive-year" name="year" required class="h-11 w-56 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">{{ __('Select year') }}</option>
                    @foreach ($years as $option)<option value="{{ $option }}" @selected((string) $year === (string) $option)>{{ $option }}</option>@endforeach
                </select>
            </div>
            <button class="h-11 rounded-lg bg-brand-500 px-4 text-sm text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Preview archive') }}</button>
            <a href="{{ route('price-monitoring.index', ['view' => 'archived']) }}" class="py-3 text-sm text-brand-600 dark:text-brand-400">{{ __('View archived records') }}</a>
        </form>
        @if ($year)
            <div class="space-y-4 rounded-lg border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10">
                <p class="font-medium text-gray-800 dark:text-white/90">{{ __(':count active records uploaded in :year will be archived.', ['count' => $count, 'year' => $year]) }}</p>
                <a href="{{ route('price-monitoring.index', ['year' => $year]) }}" class="inline-block text-sm text-brand-600 dark:text-brand-400">{{ __('Review matching records') }}</a>
                @if ($count > 0)
                    <form method="POST" action="{{ route('price-monitoring.archives.store') }}" class="space-y-4" x-data="{ confirmed: false, submitting: false }" @submit="submitting = true">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="expected_count" value="{{ $count }}">
                        <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="confirmed" value="1" required x-model="confirmed" class="h-4 w-4 accent-brand-500 dark:accent-brand-400">
                            {{ __('I reviewed this year and want to archive these records.') }}
                        </label>
                        <button :disabled="!confirmed || submitting" class="rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600">{{ __('Archive records') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </x-common.component-card>
    <x-common.component-card :title="__('Archive history')">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Operations run in the background. Refresh to check progress. Failed operations can resume without starting over.') }}</p>
            <a href="{{ request()->fullUrl() }}" class="text-sm text-brand-600 dark:text-brand-400">{{ __('Refresh') }}</a>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-start text-sm">
                <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        @foreach (['Year', 'Archived by', 'Started', 'Status', 'Progress', 'Completed', 'Restored by', 'Restored', 'Actions'] as $label)
                            <th class="whitespace-nowrap p-3 text-start font-medium">{{ __($label) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-300">
                    @forelse ($batches as $batch)
                        <tr>
                            <td class="p-3">{{ $batch->year }}</td>
                            <td class="p-3">{{ $batch->actor_name }}</td>
                            <td class="whitespace-nowrap p-3">{{ $batch->created_at->format('Y-m-d H:i') }}</td>
                            <td class="p-3">{{ __(ucfirst($batch->status)) }} @if ($batch->action === 'restore')({{ __('Restore') }})@endif</td>
                            <td class="whitespace-nowrap p-3 tabular-nums">{{ number_format($batch->processed) }} / {{ number_format($batch->total) }}</td>
                            <td class="whitespace-nowrap p-3">{{ $batch->completed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="p-3">{{ $batch->restored_by ?? '—' }}</td>
                            <td class="whitespace-nowrap p-3">{{ $batch->restored_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="p-3">
                                @if ($batch->status === 'completed')
                                    <form method="POST" action="{{ route('price-monitoring.archives.restore', $batch) }}" class="flex items-center gap-3">
                                        @csrf
                                        <label class="flex items-center gap-2 whitespace-nowrap">
                                            <input type="checkbox" name="confirmed" value="1" required class="h-4 w-4 accent-brand-500 dark:accent-brand-400">
                                            {{ __('Confirm restore') }}
                                        </label>
                                        <button class="text-brand-600 dark:text-brand-400">{{ __('Restore') }}</button>
                                    </form>
                                @elseif ($batch->status === 'failed')
                                    <form method="POST" action="{{ route('price-monitoring.archives.retry', $batch) }}">
                                        @csrf
                                        <button class="text-brand-600 dark:text-brand-400">{{ __('Retry') }}</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-6 text-center text-gray-500 dark:text-gray-400">{{ __('No archive operations yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $batches->links() }}
    </x-common.component-card>
</div>
@endsection
