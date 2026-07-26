@props(['status'])
@php
    $normalized = strtolower((string) $status);
    $variant = match ($normalized) {
        'accepted', 'pubblicato', 'approvato' => 'success',
        'rejected', 'rifiutato' => 'danger',
        'pending', 'in revisione', 'unrevisioned' => 'warning',
        default => 'neutral',
    };
@endphp
<span class="status-badge status-{{ $variant }}">{{ ucfirst($status) }}</span>
