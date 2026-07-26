<x-layout title="Registrati">
    <section class="auth-section"><div class="container auth-grid">
        <div class="auth-intro reveal"><p class="eyebrow">Cyber community</p><h1>Condividi conoscenza. Costruisci sicurezza.</h1><p>Crea il tuo account e partecipa a una community orientata alla qualità e alla consapevolezza digitale.</p><div class="auth-decoration" aria-hidden="true"><span></span><span></span><span></span></div></div>
        <div class="auth-card reveal"><h2>Crea account</h2><p>Tutti i campi sono obbligatori.</p>
            <form action="{{ route('register') }}" method="POST">@csrf
                <div class="form-group"><label for="name">Nome completo</label><input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>@error('name')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label for="email">Email</label><input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label for="password">Password</label><div class="password-control"><input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="new-password" required><button type="button" data-password-toggle="password" aria-label="Mostra password">Mostra</button></div><small>Usa una password robusta e unica.</small>@error('password')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label for="password_confirmation">Conferma password</label><div class="password-control"><input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required><button type="button" data-password-toggle="password_confirmation" aria-label="Mostra conferma password">Mostra</button></div></div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Crea account</button>
            </form><p class="auth-switch">Hai già un account? <a href="{{ route('login') }}">Accedi</a></p>
        </div>
    </div></section>
</x-layout>
