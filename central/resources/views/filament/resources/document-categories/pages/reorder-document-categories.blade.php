<x-filament-panels::page>
    <p style="opacity: 0.7;">
        Povucite kategoriju za ručicu da biste joj promijenili mjesto. Tim se redom kategorije prikazuju na stranici s dokumentima. Promjene se spremaju odmah.
    </p>

    <x-filament::section>
        @php($categories = $this->getCategories())

        @if ($categories->isEmpty())
            <p style="opacity: 0.6;">Još nema kategorija.</p>
        @else
            <x-reorder-list method="reorder">
                @foreach ($categories as $category)
                    <x-reorder-item :key="$category->id">
                        {{ $category->name }}

                        <x-slot:after>
                            <span style="opacity: 0.6; white-space: nowrap;">
                                {{ trans_choice(':count dokument|:count dokumenta|:count dokumenata', $category->documents_count) }}
                            </span>
                        </x-slot:after>
                    </x-reorder-item>
                @endforeach
            </x-reorder-list>
        @endif
    </x-filament::section>
</x-filament-panels::page>
