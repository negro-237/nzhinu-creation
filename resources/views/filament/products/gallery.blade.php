<div style="display:flex;flex-wrap:wrap;gap:1rem">
    @forelse ($getRecord()?->getMedia('images') ?? [] as $media)
        <figure>
            <img src="{{ $media->getUrl() }}" alt="{{ $media->getCustomProperty('alt', '') }}" width="160" height="160" style="object-fit:contain" loading="lazy">
            <figcaption>{{ $media->getCustomProperty('alt', '') }}</figcaption>
        </figure>
    @empty
        <p>Aucune image. Ajoutez des images depuis les actions de la page de modification.</p>
    @endforelse
</div>
