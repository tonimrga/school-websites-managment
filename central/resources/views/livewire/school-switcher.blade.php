<div class="flex items-center gap-x-2">
    <label for="school-switcher" class="text-sm font-medium text-gray-700 dark:text-gray-200">
        Škola:
    </label>

    <x-filament::input.wrapper>
        <x-filament::input.select id="school-switcher" wire:model.live="schoolId">
            @if ($schools->isEmpty())
                <option value="">Nema škola</option>
            @endif

            @foreach ($schools as $school)
                <option value="{{ $school->id }}">
                    {{ $school->is_active ? $school->name : "{$school->name} (neaktivna)" }}
                </option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
