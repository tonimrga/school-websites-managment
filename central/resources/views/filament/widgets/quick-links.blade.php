<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
            <div style="min-width: 0;">
                <div style="font-weight: 600; font-size: 1.125rem;">{{ $school?->name }}</div>
                @if (filled($school?->site_url))
                    <a href="{{ $school->site_url }}" target="_blank" rel="noopener noreferrer"
                       style="display: inline-flex; align-items: center; gap: 0.25rem; color: var(--primary-600); text-decoration: underline; word-break: break-all;">
                        Javna stranica: {{ $school->site_url }}
                        <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" style="width: 1rem; height: 1rem; flex-shrink: 0;" />
                    </a>
                @endif
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                @if ($newArticleUrl)
                    <x-filament::button tag="a" :href="$newArticleUrl" icon="heroicon-m-plus">
                        Nova vijest
                    </x-filament::button>
                @endif
                @if ($newDocumentUrl)
                    <x-filament::button tag="a" :href="$newDocumentUrl" icon="heroicon-m-plus" color="gray">
                        Novi dokument
                    </x-filament::button>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
