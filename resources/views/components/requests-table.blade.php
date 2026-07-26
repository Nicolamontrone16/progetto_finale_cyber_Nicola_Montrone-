@if($roleRequests->isEmpty())
    <x-empty-state title="Nessuna richiesta" message="Non ci sono richieste per questo ruolo." />
@else
<div class="table-responsive data-table-wrap"><table class="table data-table"><thead><tr><th scope="col">ID</th><th scope="col">Utente</th><th scope="col">Email</th><th scope="col">Ruolo richiesto</th><th scope="col" class="text-end">Azione</th></tr></thead><tbody>
@foreach($roleRequests as $user)<tr><td><span class="mono-id">#{{ $user->id }}</span></td><td><strong>{{ $user->name }}</strong></td><td>{{ $user->email }}</td><td><span class="status-badge status-neutral">{{ ucfirst($role) }}</span></td><td><div class="table-actions">@switch($role)@case('admin')<form action="{{ route('admin.setAdmin', $user) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="btn btn-primary btn-sm">Abilita admin</button></form>@break @case('revisor')<form action="{{ route('admin.setRevisor', $user) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="btn btn-primary btn-sm">Abilita revisore</button></form>@break @case('writer')<form action="{{ route('admin.setWriter', $user) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="btn btn-primary btn-sm">Abilita writer</button></form>@break @endswitch</div></td></tr>@endforeach
</tbody></table></div>
@endif
