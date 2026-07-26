<x-layout title="Profilo">
    <section class="content-section"><div class="container">
        <x-page-header eyebrow="Account" title="Profilo e sicurezza" description="Mantieni aggiornate le tue informazioni personali e le credenziali di accesso." />
        <div class="profile-grid">
            <aside class="surface-card profile-summary"><span class="profile-avatar profile-avatar-xl" aria-hidden="true">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span><h2>{{ Auth::user()->name }}</h2><p>{{ Auth::user()->email }}</p><hr><h3>Ruoli attivi</h3><div class="tag-list"><span>Utente</span>@if(Auth::user()->is_writer)<span>Writer</span>@endif @if(Auth::user()->is_revisor)<span>Revisore</span>@endif @if(Auth::user()->is_admin)<span>Admin</span>@endif</div><p class="security-note">I ruoli sono gestiti dagli amministratori e non sono modificabili dal profilo.</p></aside>
            <form action="{{ route('profile.update') }}" method="POST" class="surface-card profile-form">@csrf @method('PATCH')
                <div class="form-section"><div class="form-section-heading"><span>01</span><div><h2>Informazioni account</h2><p>Nome ed email associati al tuo profilo.</p></div></div>
                    <div class="form-grid"><div class="form-group"><label for="name">Nome</label><input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', Auth::user()->name) }}" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div><div class="form-group"><label for="email">Email</label><input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', Auth::user()->email) }}" required>@error('email')<span class="field-error">{{ $message }}</span>@enderror</div></div>
                </div>
                <div class="form-section"><div class="form-section-heading"><span>02</span><div><h2>Modifica password</h2><p>Lascia vuoto per mantenere la password attuale.</p></div></div>
                    <div class="form-grid"><div class="form-group"><label for="password">Nuova password</label><div class="password-control"><input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password"><button type="button" data-password-toggle="password" aria-label="Mostra password">Mostra</button></div>@error('password')<span class="field-error">{{ $message }}</span>@enderror</div><div class="form-group"><label for="password_confirmation">Conferma password</label><div class="password-control"><input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password"><button type="button" data-password-toggle="password_confirmation" aria-label="Mostra conferma password">Mostra</button></div></div></div>
                </div>
                @error('profile')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                <div class="form-actions"><button type="submit" class="btn btn-primary">Salva modifiche</button></div>
            </form>
        </div>
    </div></section>
</x-layout>
