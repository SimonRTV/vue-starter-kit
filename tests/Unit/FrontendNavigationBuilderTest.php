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

    public function test_builder_supports_reordering_picking_destinations_and_previewing(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js';
        $builder = file_get_contents($root.'/components/navigation/FrontendNavigationBuilder.vue');
        $destinationField = file_get_contents($root.'/components/navigation/FrontendNavigationDestinationField.vue');
        $preview = file_get_contents($root.'/components/navigation/FrontendNavigationPreview.vue');
        $page = file_get_contents($root.'/pages/settings/FrontendNavigation.vue');

        $this->assertIsString($builder);
        $this->assertIsString($destinationField);
        $this->assertIsString($preview);
        $this->assertIsString($page);

        $this->assertStringContainsString('draggable="true"', $builder);
        $this->assertStringContainsString('@keydown.up.prevent', $builder);
        $this->assertStringContainsString('<FrontendNavigationDestinationField', $builder);
        $this->assertStringContainsString('<FrontendNavigationPreview', $builder);
        $this->assertStringContainsString('moveChildToGroup(', $builder);
        $this->assertStringContainsString('duplicateItem(index)', $builder);
        $this->assertStringContainsString("label: 'Annuler'", $builder);

        $this->assertStringContainsString('destinations.pages', $destinationField);
        $this->assertStringContainsString('isUnknownFrontendNavigationPage', $destinationField);
        $this->assertStringContainsString('data-test="navigation-preview"', $preview);

        $this->assertStringContainsString("'beforeunload'", $page);
        $this->assertStringContainsString("router.on('before'", $page);
        $this->assertStringContainsString('restoreDefaults', $page);
    }
}
