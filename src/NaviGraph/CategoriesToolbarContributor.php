<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\NaviGraph;

use CoolMS\Core\Navi\NaviGraphContributorInterface;

/**
 * Bootstraps the navi.toolbar.taxonomy.categories NaviTree so the seeder
 * processes the YAML-defined action bar for the Categories admin page
 * (config/modules/taxonomy/navigraph/navi.toolbar.taxonomy.categories.yaml).
 *
 * A thin shim -- it declares the tree slug so `NaviGraphSeeder::seed()` includes
 * the tree in its YAML-extras pass (the seeder only merges YAML for trees a
 * contributor touches). All actual nodes live in the YAML. Mirrors
 * the pages contributor a content module registers the same way.
 */
final class CategoriesToolbarContributor implements NaviGraphContributorInterface
{
    public function getTreeSlug(): string
    {
        return 'navi.toolbar.taxonomy.categories';
    }

    public function getModuleName(): string
    {
        return 'taxonomy';
    }

    public function getNodes(): array
    {
        return [];
    }
}
