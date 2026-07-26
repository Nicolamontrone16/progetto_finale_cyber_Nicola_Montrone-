<x-layout title="Accedi">
    <section class="auth-section"><div class="container auth-grid">
        <div class="auth-intro reveal"><p class="eyebrow">Bentornato</p><h1>Accedi al tuo spazio sicuro.</h1><p>Gestisci il profilo, segui la community e accedi agli strumenti dedicati al tuo ruolo.</p><div class="auth-decoration" aria-hidden="true"><span></span><span></span><span></span></div></div>
        <div class="auth-card reveal">
            <h2>Accedi</h2><p>Inserisci le credenziali del tuo account.</p>
            <form action="{{ route('login') }}" method="POST">@csrf
                <div class="form-group"><label for="email">Email</label><input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label for="password">Password</label><div class="password-control"><input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="current-password" required><button type="button" data-password-toggle="password" aria-label="Mostra password">Mostra</button></div>@error('password')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Accedi</button>
            </form>
            <p class="auth-switch">Non hai ancora un account? <a href="{{ route('register') }}">Registrati</a></p>
        </div>
    </div></section>
</x-layout>
