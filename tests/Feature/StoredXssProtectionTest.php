<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Services\ArticleContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoredXssProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('scout.driver', 'null');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake();
    }

    public function test_allowlisted_rich_text_is_preserved(): void
    {
        $safe = app(ArticleContentSanitizer::class)->sanitize(
            '<h2>Heading</h2><p>Hello <strong>bold</strong> and <em>italic</em>.</p><ul><li>Item</li></ul><blockquote>Quote</blockquote><a href="https://example.test">Link</a>'
        );

        $this->assertStringContainsString('<h2>Heading</h2>', $safe);
        $this->assertStringContainsString('<strong>bold</strong>', $safe);
        $this->assertStringContainsString('<em>italic</em>', $safe);
        $this->assertStringContainsString('<ul><li>Item</li></ul>', $safe);
        $this->assertStringContainsString('href="https://example.test"', $safe);
    }

    public function test_dangerous_elements_attributes_protocols_and_malformed_html_are_removed(): void
    {
        $payload = <<<'HTML'
<p onclick="alert('x')">Safe text</p>
<script>alert('hacked')</script>
<img src="x" onerror="alert('hacked')">
<a href="javascript:alert('x')">unsafe link</a>
<iframe src="https://example.test"></iframe><object data="x"></object><embed src="x">
<form action="x"><input name="x"><button>Send</button></form>
<svg onload="alert('x')"><circle></circle></svg>
<scr<script>ipt>alert('malformed')</scr</script>ipt>
HTML;

        $safe = strtolower(app(ArticleContentSanitizer::class)->sanitize($payload));

        foreach (['<script', 'onerror=', 'onclick=', 'javascript:', '<iframe', '<object', '<embed', '<form', '<input', '<button', '<svg', 'onload='] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $safe);
        }

        $this->assertStringContainsString('<p>safe text</p>', $safe);
    }

    public function test_article_creation_sanitizes_body_and_logs_without_payload(): void
    {
        Log::spy();
        [$writer, $category] = $this->writerAndCategory();
        $payload = '<p>Legitimate article text.</p><script>alert("hacked")</script><img src="x" onerror="alert(1)">';

        $this->actingAs($writer)
            ->post(route('articles.store'), $this->articlePayload($category, $payload))
            ->assertRedirect(route('homepage'));

        $article = Article::where('title', 'Secure article title')->firstOrFail();
        $this->assertStringContainsString('<p>Legitimate article text.</p>', $article->body);
        $this->assertStringNotContainsString('<script', strtolower($article->body));
        $this->assertStringNotContainsString('onerror=', strtolower($article->body));

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $context['event'] === 'article_xss_content_sanitized'
                && $context['actor_user_id'] === $writer->id
                && $context['article_id'] === $article->id
                && $context['removed_content_detected'] === true
                && ! array_key_exists('body', $context)
                && ! str_contains(json_encode($context), 'alert')
        )->once();

        Http::assertNothingSent();
    }

    public function test_article_update_uses_the_same_sanitization(): void
    {
        [$writer, $category] = $this->writerAndCategory();
        $article = $this->article($writer, $category, '<p>Original safe content.</p>');
        $payload = '<h3>Updated safe text</h3><a href="javascript:alert(1)" onclick="alert(1)">link</a>';

        $this->actingAs($writer)
            ->put(route('articles.update', $article), [
                'title' => 'Updated secure title',
                'subtitle' => 'Updated secure subtitle',
                'body' => $payload,
                'category' => $category->id,
                'tags' => 'security',
            ])
            ->assertRedirect(route('writer.dashboard'));

        $body = strtolower($article->fresh()->body);
        $this->assertStringContainsString('<h3>updated safe text</h3>', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('onclick=', $body);
    }

    public function test_historical_malicious_article_is_sanitized_when_rendered_publicly_and_for_revisor(): void
    {
        [$writer, $category] = $this->writerAndCategory();
        $article = $this->article(
            $writer,
            $category,
            '<p>Historical text</p><script>alert("hacked")</script><img src="x" onerror="alert(1)">'
        );

        $publicResponse = $this->get(route('articles.show', $article));
        $publicResponse->assertOk()
            ->assertSee('<p>Historical text</p>', false)
            ->assertDontSee('alert("hacked")', false)
            ->assertDontSee('onerror=', false);

        $revisor = User::factory()->create(['is_revisor' => true]);
        $this->actingAs($revisor)
            ->get(route('articles.show', $article))
            ->assertOk()
            ->assertDontSee('alert("hacked")', false)
            ->assertDontSee('onerror=', false);

        $this->assertStringContainsString('<script', $article->fresh()->body);
        Http::assertNothingSent();
    }

    public function test_body_without_meaningful_safe_text_is_rejected_and_other_validation_remains_active(): void
    {
        [$writer, $category] = $this->writerAndCategory();

        $this->actingAs($writer)
            ->from(route('articles.create'))
            ->post(route('articles.store'), $this->articlePayload(
                $category,
                '<script>alert("hacked")</script><iframe src="x"></iframe>'
            ))
            ->assertRedirect(route('articles.create'))
            ->assertSessionHasErrors('body');

        $invalid = $this->articlePayload($category, '<p>Valid body text here.</p>');
        $invalid['title'] = 'bad';

        $this->actingAs($writer)
            ->post(route('articles.store'), $invalid)
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('articles', 0);
        Http::assertNothingSent();
    }

    public function test_editor_configuration_matches_the_server_allowlist(): void
    {
        $writer = User::factory()->create(['is_writer' => true]);

        $this->actingAs($writer)
            ->get(route('articles.create'))
            ->assertOk()
            ->assertSee("selector: 'textarea#body'", false)
            ->assertSee("invalid_elements: 'script,iframe,object,embed,applet,form,input,button,meta,base,style,svg,math'", false)
            ->assertDontSee(' link image media table ', false);
    }

    private function writerAndCategory(): array
    {
        return [
            User::factory()->create(['is_writer' => true]),
            Category::query()->firstOrFail(),
        ];
    }

    private function articlePayload(Category $category, string $body): array
    {
        return [
            'title' => 'Secure article title',
            'subtitle' => 'Secure article subtitle',
            'body' => $body,
            'image' => UploadedFile::fake()->create('cover.jpg', 10, 'image/jpeg'),
            'category' => $category->id,
            'tags' => 'security, xss',
        ];
    }

    private function article(User $writer, Category $category, string $body): Article
    {
        return Article::withoutSyncingToSearch(fn () => Article::create([
            'title' => 'Historical article',
            'subtitle' => 'Historical subtitle',
            'body' => $body,
            'image' => 'public/images/test.jpg',
            'category_id' => $category->id,
            'user_id' => $writer->id,
            'slug' => 'historical-article-'.str()->random(8),
            'is_accepted' => true,
        ]));
    }
}
