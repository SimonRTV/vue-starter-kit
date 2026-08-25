<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ApplicationLogoPageTest extends TestCase
{
    public function test_all_branding_upload_buttons_use_a_consistent_save_label(): void
    {
        $page = file_get_contents(
            dirname(__DIR__, 2).'/resources/js/pages/settings/ApplicationLogo.vue',
        );

        $this->assertIsString($page);
        $this->assertSame(3, substr_count($page, ": 'Enregistrer'"));
        $this->assertStringNotContainsString('Enregistrer l’icône', $page);
        $this->assertStringNotContainsString('Enregistrer le logo complet', $page);
        $this->assertStringNotContainsString('Enregistrer le logo pour fond sombre', $page);
    }
}
