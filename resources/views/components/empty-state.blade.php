@props(['title' => 'Nessun elemento', 'message' => 'Non ci sono ancora contenuti da mostrare.'])
<div class="empty-state" role="status">
    <span class="empty-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4V4Zm3 4v2h10V8H7Zm0 5v2h7v-2H7Z"/></svg></span>
    <h2>{{ $title }}</h2>
    <p>{{ $message }}</p>
    {{ $slot }}
</div>
