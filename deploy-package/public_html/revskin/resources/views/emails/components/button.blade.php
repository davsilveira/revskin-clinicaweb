@props(['url', 'color' => 'primary'])

@php
$colors = [
    'primary' => '#059669',
    'secondary' => '#4b5563',
    'danger' => '#dc2626',
];
$bgColor = $colors[$color] ?? $colors['primary'];
@endphp

<div class="button-container" style="text-align: center; margin: 32px 0;">
    <a href="{{ $url }}" 
       class="button" 
       style="display: inline-block; padding: 14px 28px; background-color: {{ $bgColor }}; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center;">
        {{ $slot }}
    </a>
</div>
