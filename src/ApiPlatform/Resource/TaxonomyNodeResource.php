<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;

#[ApiResource(
    shortName: 'TaxonomyNode',
    operations: [
        new GetCollection(
            uriTemplate: '/taxonomy/nodes',
            name: 'taxonomy_nodes_list',
            provider: Provider\TaxonomyNodeProvider::class,
        ),
        new Get(
            uriTemplate: '/taxonomy/nodes/{id}',
            name: 'taxonomy_nodes_get',
            provider: Provider\TaxonomyNodeProvider::class,
        ),
        new Post(
            uriTemplate: '/taxonomy/nodes',
            status: 201,
            security: "is_granted('ROLE_ADMIN')",
            name: 'taxonomy_nodes_create',
            processor: Processor\CreateTaxonomyNodeProcessor::class,
        ),
        new Put(
            uriTemplate: '/taxonomy/nodes/{id}',
            security: "is_granted('ROLE_ADMIN')",
            name: 'taxonomy_nodes_update',
            provider: Provider\TaxonomyNodeProvider::class,
            processor: Processor\UpdateTaxonomyNodeProcessor::class,
        ),
        new Delete(
            uriTemplate: '/taxonomy/nodes/{id}',
            security: "is_granted('ROLE_ADMIN')",
            output: false,
            name: 'taxonomy_nodes_delete',
            provider: Provider\TaxonomyNodeProvider::class,
            processor: Processor\DeleteTaxonomyNodeProcessor::class,
        ),
    ],
)]
final class TaxonomyNodeResource
{
    public function __construct(
        public ?string $id = null,
        public string $label = '',
        public string $name = '',
        public string $slug = '',
        public ?string $treeId = null,
        public ?string $parentId = null,
        public int $level = 0,
        public int $lft = 0,
        public int $rgt = 0,
        public string $type = 'taxonomy_node',
    ) {
    }
}
