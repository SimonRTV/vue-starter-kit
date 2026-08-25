<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class RegistrationEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        putenv('FORTIFY_REGISTRATION_ENABLED=true');
        $_ENV['FORTIFY_REGISTRATION_ENABLED'] = 'true';
        $_SERVER['FORTIFY_REGISTRATION_ENABLED'] = 'true';

        return parent::createApplication();
    }

    public function test_registration_screen_can_be_rendered_when_enabled(): void
    {
        $this->assertTrue(Features::enabled(Features::registration()));

        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/Register')
                ->where('registrationUrl', route('register.store', absolute: false))
                ->has('passwordRules'));

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('registrationUrl', route('register', absolute: false)));
    }

    public function test_new_users_can_register_when_enabled(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Nouvelle Utilisatrice',
            'email' => '  NEW.USER@EXAMPLE.COM  ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'new.user@example.com')->firstOrFail();

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_validates_email_and_password_when_enabled(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Nouvel Utilisateur',
            'email' => 'EXISTING@EXAMPLE.COM',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }
}
