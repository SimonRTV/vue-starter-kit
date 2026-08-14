<?php

namespace Database\Factories;

use App\Models\ApplicationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationSetting>
 */
class ApplicationSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
        ];
    }

    public function icon(string $path = 'application/logos/icon.png'): static
    {
        return $this->state(fn (): array => [
            'key' => ApplicationSetting::ICON_PATH,
            'value' => $path,
        ]);
    }

    public function fullLogo(string $path = 'application/logos/full-logo.png'): static
    {
        return $this->state(fn (): array => [
            'key' => ApplicationSetting::FULL_LOGO_PATH,
            'value' => $path,
        ]);
    }

    /**
     * @param  list<array{title: string, url: string}>  $links
     */
    public function sidebarFooterLinks(
        array $links = ApplicationSetting::DEFAULT_SIDEBAR_FOOTER_LINKS,
    ): static {
        return $this->state(fn (): array => [
            'key' => ApplicationSetting::SIDEBAR_FOOTER_LINKS,
            'value' => json_encode(
                $links,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        ]);
    }
}
