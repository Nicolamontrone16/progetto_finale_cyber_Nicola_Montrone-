<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class CriticalRoleOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    public function test_get_requests_cannot_change_roles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach ($this->roleRoutes() as [$routeName, $attribute]) {
            $user = User::factory()->create([$attribute => false]);

            $this->actingAs($admin)
                ->get($this->internalAdminUrl($routeName, $user))
                ->assertMethodNotAllowed();

            $this->assertFalse((bool) $user->fresh()->{$attribute});
        }
    }

    public function test_unauthenticated_patch_request_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->patch($this->internalAdminUrl('admin.setAdmin', $user))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_patch_request_is_rejected(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->patch($this->internalAdminUrl('admin.setAdmin', $target))
            ->assertRedirect(route('homepage'));

        $this->assertFalse((bool) $target->fresh()->is_admin);
    }

    public function test_patch_without_csrf_token_is_rejected_by_web_middleware(): void
    {
        $application = Mockery::mock(ApplicationContract::class);
        $application->shouldReceive('runningInConsole')->once()->andReturnFalse();

        $request = Request::create('/admin/1/set-admin', 'PATCH');
        $request->setLaravelSession(app('session')->driver());

        $this->expectException(TokenMismatchException::class);

        (new ValidateCsrfToken($application, app('encrypter')))
            ->handle($request, fn () => response('unexpected'));
    }

    public function test_admin_can_assign_each_role_and_changes_are_persisted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach ($this->roleRoutes() as [$routeName, $attribute]) {
            $user = User::factory()->create([$attribute => false]);

            $this->actingAs($admin)
                ->patch($this->internalAdminUrl($routeName, $user))
                ->assertRedirect(route('admin.dashboard'));

            $this->assertTrue((bool) $user->fresh()->{$attribute});
        }
    }

    public function test_role_routes_are_patch_only_and_use_all_security_middleware(): void
    {
        foreach ($this->roleRoutes() as [$routeName]) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertSame(['PATCH'], $route->methods());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('admin', $route->gatherMiddleware());
            $this->assertContains('admin.local', $route->gatherMiddleware());
        }
    }

    public function test_role_request_component_uses_csrf_protected_patch_forms(): void
    {
        foreach ($this->roleRoutes() as [$routeName, $attribute]) {
            $role = str($attribute)->after('is_')->toString();
            $user = User::factory()->create([$attribute => null]);
            $html = (string) $this->view('components.requests-table', [
                'roleRequests' => collect([$user]),
                'role' => $role,
            ]);

            $this->assertStringContainsString('method="POST"', $html);
            $this->assertStringContainsString('name="_token"', $html);
            $this->assertStringContainsString('name="_method" value="PATCH"', $html);
            $this->assertStringNotContainsString('<a href="'.route($routeName, $user), $html);
        }
    }

    private function roleRoutes(): array
    {
        return [
            ['admin.setAdmin', 'is_admin'],
            ['admin.setRevisor', 'is_revisor'],
            ['admin.setWriter', 'is_writer'],
        ];
    }

    private function internalAdminUrl(string $routeName, User $user): string
    {
        return 'http://internal.admin:8000'.route($routeName, $user, false);
    }
}
