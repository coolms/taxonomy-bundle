<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\Field;

use CoolMS\Core\Field\FieldWidgetProviderInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The Taxonomy module's contribution to the field-widget registry: a field
 * declared `type: taxonomy` renders the admin category picker -- a multi-select
 * over a taxonomy tree's nodes (badge chips, search, inline create), storing an
 * array of node UUIDs.
 *
 * Auto-tagged `coolms.field.widget_provider`, so it exists -- and the `taxonomy`
 * widget is offered platform-wide -- only while the Taxonomy module is installed
 * (else a `type: taxonomy` field degrades to the built-in text input).
 *
 * Per-field tree selection: a field scopes the picker to a specific tree by
 * **code** via a `widget: { tree: <code> }` key in its YAML (carried through the
 * DB mirror like `group`/`appliesTo` and forwarded here by the field-panel
 * resolver). With no override the platform's primary `categories` tree is used,
 * so the content `categoryIds` field keeps working unchanged and a second
 * taxonomy field (e.g. "Regions") simply declares its own tree. The tree's id is
 * resolved here so the front-end can create nodes without a separate lookup.
 */
final readonly class TaxonomyFieldWidgetProvider implements FieldWidgetProviderInterface
{
    /**
     * The tree a `taxonomy` field falls back to when its YAML declares no
     * `widget: { tree: <code> }` override -- the platform's primary category tree.
     */
    private const string DEFAULT_TREE_CODE = 'categories';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TaxonomyTreeRepositoryInterface $treeRepository,
    ) {
    }

    public function fieldType(): string
    {
        return 'taxonomy';
    }

    public function widget(array $fieldConfig = []): array
    {
        $treeCode = $this->treeCode($fieldConfig);
        $tree = $this->treeRepository->findByCode($treeCode);

        return [
            'kind' => 'taxonomy',
            // The FE appends `?tree=<code>` for the scoped node list; values are node UUIDs.
            'optionsUrl' => $this->urlGenerator->generate('taxonomy_nodes_list'),
            'createUrl' => $this->urlGenerator->generate('taxonomy_nodes_create'),
            'tree' => $treeCode,
            // Resolved so the FE can POST a new node (needs treeId); null until seeded.
            'treeId' => $tree?->id->toRfc4122(),
            'multiple' => true,
        ];
    }

    /**
     * The tree code this field is scoped to: a per-field `widget: { tree: <code> }`
     * override from the field's schema config, or {@see self::DEFAULT_TREE_CODE}.
     *
     * @param array<string, mixed> $fieldConfig
     */
    private function treeCode(array $fieldConfig): string
    {
        $widget = $fieldConfig['widget'] ?? null;
        $tree = is_array($widget) ? ($widget['tree'] ?? null) : null;

        return is_string($tree) && '' !== $tree ? $tree : self::DEFAULT_TREE_CODE;
    }
}
