<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Http\Middleware\BlockSuspiciousIPs;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CriticalOperationsLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('scout.driver', 'null');
        $app['config']->set('cache.default', 'array');
    }

    public function test_registration_is_logged_without_credentials(): void
    {
        Log::spy();

        $password = 'LoggingTestPassword123!';
        $this->post('/register', [
            'name' => 'Audit User',
            'email' => 'audit@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect('/');

        $user = User::where('email', 'audit@example.test')->firstOrFail();

        Log::shouldHaveReceived('notice')->withArgs(
            fn (string $message, array $context) => $message === 'User registered'
                && $context['event'] === 'user_registered'
                && $context['user_id'] === $user->id
                && $context['result'] === 'success'
                && $this->containsNoSensitiveData($context, [$password])
        )->once();
    }

    public function test_logout_is_logged_without_credentials(): void
    {
        $password = 'LoggingTestPassword123!';
        $user = User::factory()->create(['password' => $password]);

        Log::spy();
        $this->actingAs($user);
        $this->post('/logout')->assertRedirect('/');

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context) => $message === 'User logged out'
                && $context['event'] === 'user_logout'
                && $context['user_id'] === $user->id
                && $this->containsNoSensitiveData($context, [$password])
        )->once();
    }

    public function test_login_is_logged_without_credentials(): void
    {
        $password = 'LoggingTestPassword123!';
        $user = User::factory()->create(['password' => $password]);

        Log::spy();
        $this->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect('/');

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context) => $message === 'User logged in'
                && $context['event'] === 'user_login'
                && $context['user_id'] === $user->id
                && $context['result'] === 'success'
                && $this->containsNoSensitiveData($context, [$password])
        )->once();
    }

    public function test_article_create_update_and_delete_are_logged_safely(): void
    {
        Storage::fake('local');
        Log::spy();

        $writer = User::factory()->create(['is_writer' => true]);
        $category = Category::query()->firstOrFail();
        $body = 'Contenuto completo riservato usato soltanto per il test dei log.';

        $this->actingAs($writer)->post(route('articles.store'), [
            'title' => 'Titolo audit article',
            'subtitle' => 'Sottotitolo audit article',
            'body' => $body,
            'image' => UploadedFile::fake()->create('cover.jpg', 10, 'image/jpeg'),
            'category' => $category->id,
            'tags' => 'audit, logging',
        ])->assertRedirect(route('homepage'));

        $article = Article::where('title', 'Titolo audit article')->firstOrFail();

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context) => $message === 'Article created'
                && $context['event'] === 'article_created'
                && $context['actor_user_id'] === $writer->id
                && $context['article_id'] === $article->id
                && $this->containsNoSensitiveData($context, [$body])
        )->once();

        $this->actingAs($writer)->put(route('articles.update', $article), [
            'title' => 'Titolo audit aggiornato',
            'subtitle' => 'Sottotitolo audit aggiornato',
            'body' => $body,
            'category' => $category->id,
            'tags' => 'audit, security',
        ])->assertRedirect(route('writer.dashboard'));

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context) => $message === 'Article updated'
                && $context['event'] === 'article_updated'
                && $context['actor_user_id'] === $writer->id
                && $context['article_id'] === $article->id
                && in_array('title', $context['changed_fields'], true)
                && $this->containsNoSensitiveData($context, [$body])
        )->once();

        $article->refresh();

        $this->actingAs($writer)
            ->from(route('writer.dashboard'))
            ->delete(route('articles.destroy', $article))
            ->assertRedirect(route('writer.dashboard'));

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context) => $message === 'Article deleted'
                && $context['event'] === 'article_deleted'
                && $context['actor_user_id'] === $writer->id
                && $context['article_id'] === $article->id
                && $this->containsNoSensitiveData($context, [$body])
        )->once();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    public function test_successful_role_assignment_is_logged(): void
    {
        Log::spy();

        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['is_writer' => false]);

        $this->actingAs($admin)
            ->patch('http://internal.admin:8000'.route('admin.setWriter', $target, false))
            ->assertRedirect(route('admin.dashboard'));

        Log::shouldHaveReceived('notice')->withArgs(
            fn (string $message, array $context) => $message === 'User role assigned'
                && $context['event'] === 'role_assigned'
                && $context['actor_user_id'] === $admin->id
                && $context['target_user_id'] === $target->id
                && $context['role'] === 'writer'
                && $context['result'] === 'success'
                && $this->containsNoSensitiveData($context)
        )->once();
    }

    public function test_unauthorized_role_change_attempt_is_logged_as_warning(): void
    {
        Log::spy();

        $actor = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create(['is_admin' => false]);
        $request = Request::create('/admin/'.$target->id.'/set-admin', 'PATCH');
        $request->setUserResolver(fn () => $actor);

        $this->actingAs($actor);

        try {
            app(AdminController::class)->setAdmin($request, $target);
            $this->fail('The unauthorized role change was not rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $message === 'Unauthorized role change attempt'
                && $context['event'] === 'unauthorized_role_change'
                && $context['actor_user_id'] === $actor->id
                && $context['target_user_id'] === $target->id
                && $context['role'] === 'admin'
                && $context['result'] === 'denied'
                && $this->containsNoSensitiveData($context)
        )->once();
    }

    public function test_rate_limiter_block_is_logged_as_warning(): void
    {
        Cache::flush();
        Log::spy();
        $middleware = app(BlockSuspiciousIPs::class);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $request = Request::create('/articles/search', 'GET', server: [
                'REMOTE_ADDR' => '192.0.2.50',
            ]);
            $middleware->handle($request, fn () => response('OK'));
        }

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $context['event'] === 'ip_temporarily_blocked'
                && $context['ip_address'] === '192.0.2.50'
                && $context['attempts'] === 6
                && $context['result'] === 'blocked'
                && $this->containsNoSensitiveData($context)
        )->once();
    }

    private function containsNoSensitiveData(array $context, array $forbiddenValues = []): bool
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            '_token',
            'cookie',
            'session_id',
            'authorization',
            'body',
        ];

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $context)) {
                return false;
            }
        }

        $serializedContext = json_encode($context);

        foreach ($forbiddenValues as $value) {
            if ($value !== '' && str_contains($serializedContext, $value)) {
                return false;
            }
        }

        return true;
    }
}
