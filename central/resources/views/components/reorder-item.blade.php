@props(['key'])

{{-- One draggable row of a reorder page; the list around it carries x-sortable. --}}
<li
    x-sortable-item="{{ $key }}"
    wire:key="reorder-item-{{ $key }}"
    style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.75rem; border: 1px solid rgb(128 128 128 / 0.3); border-radius: 0.5rem;"
>
    <button
        type="button"
        x-sortable-handle
        title="Povucite za promjenu redoslijeda"
        aria-label="Povucite za promjenu redoslijeda"
        style="cursor: grab; display: flex; opacity: 0.6;"
    >
        <x-filament::icon icon="heroicon-m-bars-2" style="width: 1.25rem; height: 1.25rem;" />
    </button>

    <span style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
        {{ $slot }}
    </span>

    {{ $after ?? '' }}
</li>
