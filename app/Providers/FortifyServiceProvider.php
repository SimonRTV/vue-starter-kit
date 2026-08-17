<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Head\Facades\Head;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()
                ->where('email', User::normalizeEmail($request->string('email')->toString()))
                ->whereNull('disabled_at')
                ->first();

            if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
                return null;
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request): Response => $this->authenticationView('auth/Login', 'Connexion', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request): Response => $this->authenticationView('auth/ResetPassword', 'Réinitialiser le mot de passe', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request): Response => $this->authenticationView('auth/ForgotPassword', 'Mot de passe oublié', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request): Response => $this->authenticationView('auth/VerifyEmail', 'Vérification de l’adresse e-mail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn (): Response => $this->authenticationView('auth/TwoFactorChallenge', 'Authentification à deux facteurs'));

        Fortify::confirmPasswordView(fn (): Response => $this->authenticationView('auth/ConfirmPassword', 'Confirmer le mot de passe'));
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function authenticationView(string $component, string $title, array $props = []): Response
    {
        Head::title($title)->hiddenFromRobots();

        return Inertia::render($component, $props);
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $email = User::normalizeEmail($request->string(Fortify::username())->toString());
            $throttleKey = Str::transliterate($email.'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
