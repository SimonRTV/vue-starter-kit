<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AppLogoTest extends TestCase
{
    public function test_custom_sidebar_icon_omits_the_default_colored_background(): void
    {
        $logo = file_get_contents(
            dirname(__DIR__, 2).'/resources/js/components/AppLogo.vue',
        );

        $this->assertIsString($logo);
        $this->assertStringContainsString('const hasCustomIcon = computed(', $logo);
        $this->assertMatchesRegularExpression(
            "/'rounded-md bg-sidebar-primary text-sidebar-primary-foreground':\s*!hasCustomIcon/",
            $logo,
        );
        $this->assertMatchesRegularExpression("/'size-8':\s*hasCustomIcon/", $logo);
        $this->assertMatchesRegularExpression("/'size-5 fill-current':\s*!hasCustomIcon/", $logo);
        $this->assertStringNotContainsString('text-white dark:text-black', $logo);
    }
}
