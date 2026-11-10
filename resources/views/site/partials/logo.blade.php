{{-- Replace with the school's logo file when available: <img src="/images/logo.svg" class="brand-logo" alt=""> --}}
<svg class="brand-logo" viewBox="0 0 64 64" aria-hidden="true">
    <circle cx="32" cy="32" r="30" fill="{{ ($inverse ?? false) ? '#fff' : '#2894ac' }}"/>
    <path d="M32 14l18 9-18 9-18-9z" fill="{{ ($inverse ?? false) ? '#2894ac' : '#fff' }}"/>
    <path d="M20 29v9c6 5 18 5 24 0v-9l-12 6z" fill="#43036e"/>
    @unless ($inverse ?? false)
        <circle cx="48" cy="40" r="2.6" fill="#fff"/><path d="M48 24v16" stroke="#fff" stroke-width="2"/>
    @endunless
</svg>
