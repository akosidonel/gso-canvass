@props(['version' => config('app.version')])

<span {{ $attributes->class(['text-theme-xs text-gray-500 dark:text-gray-400']) }} title="{{ __('Version :version', ['version' => $version]) }}">v{{ $version }}</span>
