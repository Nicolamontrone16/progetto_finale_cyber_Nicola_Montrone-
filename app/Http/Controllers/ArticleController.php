<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\User;
use App\Models\Article;
use App\Models\Category;
use App\Services\ArticleContentSanitizer;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;

class ArticleController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('auth', except: ['index', 'show', 'byCategory', 'byUser', 'articleSearch']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $articles = Article::where('is_accepted', true)->orderBy('created_at', 'desc')->get();
        return view('articles.index', compact('articles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('articles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, ArticleContentSanitizer $sanitizer)
    {
        $validated = $request->validate([
            'title' => 'required|unique:articles|min:5',
            'subtitle' => 'required|min:5',
            'body' => 'required|string|min:10|max:50000',
            'image' => 'required|image',
            'category' => 'required',
            'tags' => 'required'
        ]);

        $dangerousContentDetected = $sanitizer->containsClearlyDangerousContent($validated['body']);
        $safeBody = $sanitizer->sanitize($validated['body']);

        if (! $sanitizer->hasMeaningfulText($safeBody)) {
            throw ValidationException::withMessages([
                'body' => 'Il contenuto non contiene testo valido.',
            ]);
        }

        $article = Article::create([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'],
            'body' => $safeBody,
            'image' => $request->file('image')->store('public/images'),
            'category_id' => $request->category,
            'user_id' => Auth::user()->id,
            'slug' => Str::slug($request->title),
        ]);

        $this->logSanitizedContent($request, $article, $validated['body'], $safeBody, $dangerousContentDetected);
        
        $tags = explode(',', $request->tags);

        foreach($tags as $i => $tag){
            $tags[$i] = trim($tag);
        }

        foreach($tags as $tag){
            $newTag = Tag::updateOrCreate([
                'name' => strtolower($tag)
            ]);
            $article->tags()->attach($newTag);
        }

        Log::info('Article created', [
            'event' => 'article_created',
            'actor_user_id' => $request->user()->id,
            'article_id' => $article->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'result' => 'success',
        ]);

        return redirect(route('homepage'))->with('message', 'Articolo creato con successo');
    }

    /**
     * Display the specified resource.
     */
    public function show(Article $article, ArticleContentSanitizer $sanitizer)
    {
        $safeBody = $sanitizer->sanitize($article->body);

        return view('articles.show', compact('article', 'safeBody'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Article $article)
    {
        if(Auth::user()->id != $article->user_id){
            return redirect()->route('homepage')->with('alert', 'Accesso non consentito');
        }
        return view('articles.edit', compact('article'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Article $article, ArticleContentSanitizer $sanitizer)
    {
        $validated = $request->validate([
            'title' => 'required|min:5|unique:articles,title,' . $article->id,
            'subtitle' => 'required|min:5',
            'body' => 'required|string|min:10|max:50000',
            'image' => 'image',
            'category' => 'required',
            'tags' => 'required'
        ]);

        $dangerousContentDetected = $sanitizer->containsClearlyDangerousContent($validated['body']);
        $safeBody = $sanitizer->sanitize($validated['body']);

        if (! $sanitizer->hasMeaningfulText($safeBody)) {
            throw ValidationException::withMessages([
                'body' => 'Il contenuto non contiene testo valido.',
            ]);
        }

        $originalAttributes = $article->getAttributes();

        $article->update([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'],
            'body' => $safeBody,
            'category_id' => $request->category,
            'slug' => Str::slug($request->title),
        ]);

        if($request->image){
            Storage::delete($article->image);
            $article->update([
                'image' => $request->file('image')->store('public/images')
            ]);
        }
        
        $tags = explode(',', $request->tags);

        foreach($tags as $i => $tag){
            $tags[$i] = trim($tag);
        }

        $newTags = [];

        foreach($tags as $tag){
            $newTag = Tag::updateOrCreate([
                'name' => strtolower($tag)
            ]);
            $newTags[] = $newTag->id;
        }
        $tagChanges = $article->tags()->sync($newTags);

        $article->refresh();
        $this->logSanitizedContent($request, $article, $validated['body'], $safeBody, $dangerousContentDetected);
        $changedFields = [];

        foreach ($article->getAttributes() as $field => $value) {
            if (array_key_exists($field, $originalAttributes) && $originalAttributes[$field] !== $value) {
                $changedFields[] = $field;
            }
        }

        $changedFields = array_values(array_diff($changedFields, ['updated_at']));

        if ($tagChanges['attached'] || $tagChanges['detached'] || $tagChanges['updated']) {
            $changedFields[] = 'tags';
        }

        Log::info('Article updated', [
            'event' => 'article_updated',
            'actor_user_id' => $request->user()->id,
            'article_id' => $article->id,
            'changed_fields' => array_values(array_unique($changedFields)),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'result' => 'success',
        ]);

        return redirect(route('writer.dashboard'))->with('message', 'Articolo modificato con successo');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Article $article)
    {
        $articleId = $article->id;

        foreach ($article->tags as $tag) {
            $article->tags()->detach($tag);
        }
        $article->delete();

        Log::info('Article deleted', [
            'event' => 'article_deleted',
            'actor_user_id' => $request->user()->id,
            'article_id' => $articleId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'result' => 'success',
        ]);
        
        return redirect()->back()->with('message', 'Articolo cancellato con successo');
    }

    public function byCategory(Category $category){
        $articles = $category->articles()->where('is_accepted', true)->orderBy('created_at', 'desc')->get();
        return view('articles.by-category', compact('category', 'articles'));
    }
    
    public function byUser(User $user){
        $articles = $user->articles()->where('is_accepted', true)->orderBy('created_at', 'desc')->get();
        return view('articles.by-user', compact('user', 'articles'));
    }

    public function articleSearch(Request $request){
        $query = $request->input('query');
        $articles = Article::search($query)->where('is_accepted', true)->orderBy('created_at', 'desc')->get();
        return view('articles.search-index', compact('articles', 'query'));
    }

    private function logSanitizedContent(
        Request $request,
        Article $article,
        string $originalBody,
        string $safeBody,
        bool $dangerousContentDetected
    ): void {
        if (! $dangerousContentDetected) {
            return;
        }

        Log::warning('Potentially dangerous article content sanitized', [
            'event' => 'article_xss_content_sanitized',
            'actor_user_id' => $request->user()?->id,
            'article_id' => $article->id,
            'ip_address' => $request->ip(),
            'route' => $request->route()?->getName() ?? $request->path(),
            'removed_content_detected' => true,
            'original_length' => strlen($originalBody),
            'sanitized_length' => strlen($safeBody),
            'result' => 'sanitized',
        ]);
    }
}
