<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FrontendNavigationBuilderTest extends TestCase
{
    public function test_builder_uses_compact_collapsible_item_editors(): void
    {
        $builder = file_get_contents(
            dirname(__DIR__, 2).'/resources/js/components/navigation/FrontendNavigationBuilder.vue',
        );
        $page = file_get_contents(
            dirname(__DIR__, 2).'/resources/js/pages/settings/FrontendNavigation.vue',
        );

        $this->assertIsString($builder);
        $this->assertIsString($page);
        $this->assertStringContainsString('<Collapsible', $builder);
        $this->assertStringContainsString('data-test="navigation-item-summary"', $builder);
        $this->assertStringContainsString('data-test="navigation-item-editor"', $builder);
        $this->assertStringContainsString('Tout développer', $builder);
        $this->assertStringContainsString('sm:grid sm:grid-cols-2', $builder);
        $this->assertStringContainsString('itemHasErrors(index)', $builder);
        $this->assertStringContainsString(':key="builderKey"', $page);
    }
}
