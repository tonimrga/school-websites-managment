@php
    $article = $this->getArticle();
    $cover = $article->getFirstMedia('cover');
    $gallery = $this->getGallery();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <article style="max-width: 48rem; margin: 0 auto; display: grid; gap: 1.25rem;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                <x-filament::badge :color="$article->statusColor()">
                    {{ $article->statusLabel() }}
                </x-filament::badge>

                @if ($article->is_featured)
                    <x-filament::badge color="warning" icon="heroicon-m-star">Istaknuta</x-filament::badge>
                @endif

                <span style="opacity: 0.7;">
                    {{ $article->published_at?->format('d. m. Y. H:i') ?? 'Datum objave još nije postavljen' }}
                </span>
            </div>

            <h1 style="font-size: 1.875rem; line-height: 1.2; font-weight: 700;">{{ $article->title }}</h1>

            @if (filled($article->excerpt))
                <p style="font-size: 1.125rem; opacity: 0.8;">{{ $article->excerpt }}</p>
            @endif

            @if ($cover)
                <img
                    src="{{ $cover->getUrl('large') }}"
                    alt="{{ $article->title }}"
                    style="width: 100%; height: auto; border-radius: 0.5rem;"
                >
            @else
                <x-filament::badge color="danger">Naslovna fotografija nije dodana (potrebna je za objavu).</x-filament::badge>
            @endif

            {{-- The body is sanitized with the allow-list when it is saved. --}}
            <div class="fi-prose">
                {!! $article->body !!}
            </div>

            @if ($gallery !== [])
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem;">Galerija</h2>

                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr)); gap: 0.75rem;">
                        @foreach ($gallery as $image)
                            <figure style="margin: 0;">
                                <a href="{{ $image['large'] }}" target="_blank" rel="noopener">
                                    <img
                                        src="{{ $image['thumb'] }}"
                                        alt="{{ $image['caption'] ?? $article->title }}"
                                        loading="lazy"
                                        style="width: 100%; height: auto; border-radius: 0.375rem;"
                                    >
                                </a>

                                @if (filled($image['caption']))
                                    <figcaption style="font-size: 0.875rem; opacity: 0.75; margin-top: 0.25rem;">
                                        {{ $image['caption'] }}
                                    </figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>
    </x-filament::section>
</x-filament-panels::page>
