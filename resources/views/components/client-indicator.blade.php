@props([
    'name' => null,
    'color' => null,
    'size' => 'sm',
])

@php
    $dotSize = match ($size) {
        'xs' => 'size-2.5',
        'lg' => 'size-4',
        default => 'size-3',
    };
@endphp

<span class="inline-flex items-center gap-2">
    @if ($color)
        <span class="{{ $dotSize }} rounded-full border border-base-300" style="background-color: {{ $color }}"></span>
    @endif

    @if ($name)
        <span>{{ $name }}</span>
    @endif
</span>
