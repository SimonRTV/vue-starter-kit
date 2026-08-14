<?php

namespace Tests\Feature;

use App\Actions\Permissions\DiscoverPolicyPermissions;
use App\Models\User;
use App\Notifications\UserPasswordSetup;
use App\Policies\PagePolicy;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_application_uses_french_locales(): void
    {
        $this->assertSame('fr', app()->getLocale());
        $this->assertSame('fr', config('app.fallback_locale'));
        $this->assertSame('fr_CH', config('app.faker_locale'));
    }

    public function test_validation_messages_and_attributes_are_in_french(): void
    {
        $validator = Validator::make([], [
            'email' => ['required'],
        ]);

        $this->assertSame(
            'Le champ adresse e-mail est obligatoire.',
            $validator->errors()->first('email'),
        );
    }

    public function test_application_and_permission_messages_are_in_french(): void
    {
        $permission = app(DiscoverPolicyPermissions::class)
            ->handle()
            ->find(PagePolicy::UPDATE);

        $this->assertSame('La page a été créée.', __('Page created.'));
        $this->assertSame('Modifier', $permission['label'] ?? null);
        $this->assertSame(
            'Modifier les pages existantes.',
            $permission['description'] ?? null,
        );
    }

    public function test_account_setup_notification_is_in_french(): void
    {
        $user = User::factory()->make(['email' => 'marie@example.com']);
        $message = (new UserPasswordSetup('token', invitation: true))
            ->toMail($user);

        $this->assertSame('Configurer votre compte', $message->subject);
        $this->assertSame('Bienvenue !', $message->greeting);
        $this->assertSame('Configurer le compte', $message->actionText);
        $this->assertContains(
            'Un compte a été créé pour vous. Utilisez le bouton ci-dessous pour choisir votre mot de passe.',
            $message->introLines,
        );
    }
}
