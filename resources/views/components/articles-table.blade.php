@if($articles->isEmpty())
    <x-empty-state title="Coda vuota" message="Non ci sono articoli in questa sezione." />
@else
<div class="table-responsive data-table-wrap"><table class="table data-table"><thead><tr><th scope="col">ID</th><th scope="col">Articolo</th><th scope="col">Autore</th><th scope="col">Stato</th><th scope="col" class="text-end">Azione</th></tr></thead><tbody>
@foreach($articles as $article)<tr><td><span class="mono-id">#{{ $article->id }}</span></td><td><strong>{{ $article->title }}</strong><small>{{ $article->subtitle }}</small></td><td>{{ $article->user->name }}</td><td>@if(is_null($article->is_accepted))<x-status-badge status="in revisione" />@elseif($article->is_accepted)<x-status-badge status="approvato" />@else<x-status-badge status="rifiutato" />@endif</td><td><div class="table-actions">@if(is_null($article->is_accepted))<a href="{{ route('articles.show', $article) }}" class="btn btn-primary btn-sm">Revisiona</a>@else<form action="{{ route('revisor.undoArticle', $article) }}" method="POST">@csrf<button type="submit" class="btn btn-outline-primary btn-sm">Riapri revisione</button></form>@endif</div></td></tr>@endforeach
</tbody></table></div>
@endif
