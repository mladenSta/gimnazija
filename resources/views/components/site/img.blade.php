@props([
    'media',              // App\Models\Media|null
    'variant' => 'large', // src for browsers without srcset
    'sizes' => '100vw',
    'alt' => null,
    'position' => null,   // CSS object-position, e.g. "30% 60%"
    'eager' => false,
])
@if ($media)
    <img
        src="{{ $media->url($variant) }}"
        @if ($srcset = $media->srcset()) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
        @if ($media->width) width="{{ $media->width }}" height="{{ $media->height }}" @endif
        alt="{{ $alt ?? $media->alt ?? '' }}"
        @if ($position) style="object-position: {{ $position }}" @endif
        loading="{{ $eager ? 'eager' : 'lazy' }}"
        @if ($eager) fetchpriority="high" @endif
        decoding="async"
        {{ $attributes }}
    >
@endif
