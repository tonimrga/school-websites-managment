{{-- Only Filament's own components here: the panel has no custom theme, so Tailwind utility classes are not compiled. --}}
<div>
    <x-filament::input.wrapper
        prefix-icon="heroicon-m-home-modern"
        title="Dom na kojem radite"
    >
        <x-filament::input.select
            id="school-switcher"
            aria-label="Dom na kojem radite"
            wire:model.live="schoolId"
        >
            @if ($schools->isEmpty())
                <option value="">Nema domova</option>
            @endif

            @foreach ($schools as $school)
                <option value="{{ $school->id }}">
                    {{ $school->is_active ? $school->name : "{$school->name} (neaktivan)" }}
                </option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
