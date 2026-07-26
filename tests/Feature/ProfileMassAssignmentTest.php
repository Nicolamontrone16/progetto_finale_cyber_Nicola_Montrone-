<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProfileMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    public function test_authenticated_user_can_open_profile_but_guest_cannot(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertDontSee('name="is_admin"', false);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patch(route('profile.update'), [
            'name' => 'Guest Name',
            'email' => 'guest@example.test',
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_update_own_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.test',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.test',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.test', $user->email);
    }

    public function test_email_must_be_unique_but_ignores_current_user(): void
    {
        $user = User::factory()->create(['email' => 'current@example.test']);
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'taken@example.test',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_is_optional_confirmed_and_hashed_when_changed(): void
    {
        $user = User::factory()->create(['password' => 'OriginalPassword123!']);
        $originalHash = $user->password;

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($originalHash, $user->fresh()->password);

        $newPassword = 'NewSecurePassword123!';

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertSessionHasNoErrors();

        $newHash = $user->fresh()->password;
        $this->assertNotSame($newPassword, $newHash);
        $this->assertTrue(Hash::check($newPassword, $newHash));

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'AnotherPassword123!',
                'password_confirmation' => 'DifferentPassword123!',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_profile_route_never_uses_browser_supplied_user_id(): void
    {
        $actor = User::factory()->create(['name' => 'Actor']);
        $other = User::factory()->create(['name' => 'Other User']);

        $this->actingAs($actor)
            ->patch(route('profile.update').'?user='.$other->id, [
                'name' => 'Actor Updated',
                'email' => $actor->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Actor Updated', $actor->fresh()->name);
        $this->assertSame('Other User', $other->fresh()->name);
    }

    public function test_sensitive_fields_are_rejected_roles_unchanged_and_warning_contains_no_values(): void
    {
        Log::spy();
        $user = User::factory()->create([
            'name' => 'Safe User',
            'is_admin' => false,
            'is_writer' => false,
            'is_revisor' => false,
        ]);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->patchJson(route('profile.update'), [
                'name' => 'Attempted Update',
                'email' => $user->email,
                'password' => 'SecretPassword123!',
                'password_confirmation' => 'SecretPassword123!',
                'is_admin' => 1,
                'is_writer' => 1,
                'is_revisor' => 1,
                'role' => 'admin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile');

        $user->refresh();
        $this->assertSame('Safe User', $user->name);
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->is_writer);
        $this->assertFalse($user->is_revisor);

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $context['event'] === 'mass_assignment_attempt_blocked'
                && $context['actor_user_id'] === $user->id
                && $context['rejected_fields'] === ['is_admin', 'is_writer', 'is_revisor', 'role']
                && $context['result'] === 'blocked'
                && ! str_contains(json_encode($context), 'SecretPassword')
        )->once();
    }

    public function test_user_fillable_contains_only_profile_fields_and_guarded_is_not_empty(): void
    {
        $user = new User();

        $this->assertSame(['name', 'email', 'password'], $user->getFillable());
        $this->assertNotSame([], $user->getGuarded());

        foreach (['is_admin', 'is_writer', 'is_revisor', 'role', 'permissions'] as $field) {
            $this->assertNotContains($field, $user->getFillable());
        }
    }

    public function test_successful_update_is_logged_without_password_or_hash(): void
    {
        Log::spy();
        $user = User::factory()->create();
        $password = 'LoggingSafePassword123!';

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Logged Update',
                'email' => $user->email,
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertSessionHasNoErrors();

        Log::shouldHaveReceived('notice')->withArgs(
            fn (string $message, array $context) => $context['event'] === 'user_profile_updated'
                && $context['actor_user_id'] === $user->id
                && $context['changed_fields'] === ['name', 'password']
                && $context['result'] === 'success'
                && ! str_contains(json_encode($context), $password)
                && ! array_key_exists('password', $context)
        )->once();
    }

    public function test_fortify_registration_seeders_and_admin_role_assignment_still_work(): void
    {
        $password = 'RegistrationPassword123!';

        $this->post('/register', [
            'name' => 'Registered User',
            'email' => 'registered@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'registered@example.test']);

        $this->seed(DatabaseSeeder::class);
        $this->assertTrue(User::where('email', 'admin@aulab.it')->firstOrFail()->is_admin);
        $this->assertTrue(User::where('email', 'writer@aulab.it')->firstOrFail()->is_writer);

        $admin = User::where('email', 'admin@aulab.it')->firstOrFail();
        $target = User::where('email', 'user@aulab.it')->firstOrFail();

        $this->actingAs($admin)
            ->patch('http://internal.admin:8000'.route('admin.setRevisor', $target, false))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue($target->fresh()->is_revisor);
    }
}
