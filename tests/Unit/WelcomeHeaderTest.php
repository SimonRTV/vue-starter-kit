<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class WelcomeHeaderTest extends TestCase
{
    public function test_header_renders_managed_links_and_groups_on_desktop_and_mobile(): void
    {
        $welcome = file_get_contents(
            dirname(__DIR__, 2).'/resources/js/pages/Welcome.vue',
        );
        $header = file_get_contents(
            dirname(__DIR__, 2).'/resources/js/components/frontend/FrontendHeader.vue',
        );
        $navigationChild = file_get_contents(
            dirname(__DIR__, 2).'/resources/js/components/frontend/FrontendNavigationChildContent.vue',
        );

        $this->assertIsString($welcome);
        $this->assertIsString($header);
        $this->assertIsString($navigationChild);
        $this->assertStringContainsString('<FrontendHeader is-home-page', $welcome);
        $this->assertStringContainsString('<AppLogoFull', $header);
        $this->assertStringContainsString('<FrontendNavigationChildContent', $header);
        $this->assertStringNotContainsString('destinationIcon', $header);
        $this->assertStringContainsString("'desktop-navigation-group'", $header);
        $this->assertStringContainsString("'desktop-navigation-link'", $header);
        $this->assertStringContainsString('data-test="mobile-navigation-group"', $header);
        $this->assertStringContainsString('data-test="mobile-navigation-link"', $header);
        $this->assertStringContainsString('flex w-full flex-col justify-center gap-0.5', $navigationChild);
        $this->assertStringNotContainsString('<component', $navigationChild);
        $this->assertStringNotContainsString('icon:', $navigationChild);
        $this->assertStringContainsString('v-if="description"', $navigationChild);
    }

    public function test_full_logo_automatically_selects_the_dark_background_variant(): void
    {
        $logo = file_get_contents(
            dirname(__DIR__, 2).'/resources/js/components/AppLogoFull.vue',
        );
        $splitAuthLayout = file_get_contents(
            dirname(__DIR__, 2).'/resources/js/layouts/auth/AuthSplitLayout.vue',
        );

        $this->assertIsString($logo);
        $this->assertIsString($splitAuthLayout);
        $this->assertStringContainsString('page.props.branding.darkFullLogoUrl', $logo);
        $this->assertStringContainsString("background?: 'auto' | 'light' | 'dark'", $logo);
        $this->assertStringContainsString("'dark:hidden'", $logo);
        $this->assertStringContainsString("'hidden dark:block'", $logo);
        $this->assertStringContainsString('background="dark"', $splitAuthLayout);
    }
}
