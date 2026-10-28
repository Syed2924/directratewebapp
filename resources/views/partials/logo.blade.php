{{-- Placeholder emblem. Put the Directorate's approved logo at public/images/logo.png and it is used automatically. --}}
@php($size = $size ?? 44)
@if (file_exists(public_path('images/logo.png')))
    <img src="{{ asset('images/logo.png') }}" alt="Directorate of Youth Affairs (KP)" width="{{ $size }}" height="{{ $size }}" style="border-radius:50%;background:#fff;object-fit:contain">
@else
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" role="img" aria-label="Directorate of Youth Affairs (KP)">
        <circle cx="24" cy="24" r="23" fill="#fff"/>
        <circle cx="24" cy="24" r="20" fill="none" stroke="#0f7b55" stroke-width="2"/>
        <path d="M10 31 L18 21 L23 27 L28 19 L38 31 Z" fill="#0f7b55"/>
        <circle cx="31" cy="15" r="3" fill="#e0a526"/>
        <text x="24" y="40" text-anchor="middle" font-family="Segoe UI, Arial" font-size="6.5" font-weight="700" fill="#0b2a4a">DYA</text>
    </svg>
@endif
