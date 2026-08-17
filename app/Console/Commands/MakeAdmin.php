<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateAdministrator;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('make:admin')]
#[Description('Create a verified administrator with all application permissions')]
class MakeAdmin extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(CreateAdministrator $createAdministrator): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('The make:admin command must be run interactively.');

            return self::FAILURE;
        }

        $this->components->info('Create an administrator account');

        $administrator = $createAdministrator->handle([
            'name' => $this->promptForName(),
            'email' => $this->promptForEmail(),
            'password' => $this->promptForPassword(),
        ]);

        $this->components->success(sprintf(
            'Administrator [%s] created successfully.',
            $administrator->email,
        ));

        return self::SUCCESS;
    }

    private function promptForName(): string
    {
        do {
            $name = Str::squish((string) $this->ask('Name'));
            $validator = Validator::make(
                ['name' => $name],
                ['name' => $this->nameRules()],
            );
        } while ($this->displayErrorsAndShouldRetry($validator));

        return $name;
    }

    private function promptForEmail(): string
    {
        do {
            $email = User::normalizeEmail((string) $this->ask('Email'));
            $validator = Validator::make(
                ['email' => $email],
                ['email' => $this->emailRules()],
            );
        } while ($this->displayErrorsAndShouldRetry($validator));

        return $email;
    }

    private function promptForPassword(): string
    {
        do {
            $password = (string) $this->secret('Password');
            $validator = Validator::make([
                'password' => $password,
                'password_confirmation' => $password,
            ], [
                'password' => $this->passwordRules(),
            ]);
        } while ($this->displayErrorsAndShouldRetry($validator));

        return $password;
    }

    private function displayErrorsAndShouldRetry(ValidatorContract $validator): bool
    {
        if (! $validator->fails()) {
            return false;
        }

        foreach ($validator->errors()->all() as $error) {
            $this->components->error($error);
        }

        return true;
    }
}
