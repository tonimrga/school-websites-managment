<x-filament-panels::page>
    <p style="opacity: 0.7;">
        Povucite dokument za ručicu da biste mu promijenili mjesto unutar kategorije. Promjene se spremaju odmah.
    </p>

    @foreach ($this->getCategories() as $category)
        <x-filament::section
            :heading="$category->name"
            :description="trans_choice(':count dokument|:count dokumenta|:count dokumenata', $category->documents->count())"
            wire:key="category-{{ $category->id }}"
        >
            @if ($category->documents->isEmpty())
                <p style="opacity: 0.6;">U ovoj kategoriji nema dokumenata.</p>
            @else
                {{-- One list per category: documents cannot be dragged into another category. --}}
                <x-reorder-list method="reorderCategory" :scope="$category->id">
                    @foreach ($category->documents as $document)
                        <x-reorder-item :key="$document->id">
                            {{ $document->title }}

                            <x-slot:after>
                                @unless ($document->is_published)
                                    <x-filament::badge color="gray">Nije objavljeno</x-filament::badge>
                                @endunless

                                @if ($document->document_date)
                                    <span style="opacity: 0.6; white-space: nowrap;">{{ $document->document_date->format('d. m. Y.') }}</span>
                                @endif
                            </x-slot:after>
                        </x-reorder-item>
                    @endforeach
                </x-reorder-list>
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
