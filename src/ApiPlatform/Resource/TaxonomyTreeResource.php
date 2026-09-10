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
    shortName: 'TaxonomyTree',
    operations: [
        new GetCollection(
            uriTemplate: '/taxonomy/trees',
            name: 'taxonomy_trees_list',
            provider: Provider\TaxonomyTreeProvider::class,
        ),
        new Get(
            uriTemplate: '/taxonomy/trees/{id}',
            name: 'taxonomy_trees_get',
            provider: Provider\TaxonomyTreeProvider::class,
        ),
        new Post(
            uriTemplate: '/taxonomy/trees',
            status: 201,
            security: "is_granted('ROLE_ADMIN')",
            name: 'taxonomy_trees_create',
            processor: Processor\CreateTaxonomyTreeProcessor::class,
        ),
        new Put(
            uriTemplate: '/taxonomy/trees/{id}',
            security: "is_granted('ROLE_ADMIN')",
            name: 'taxonomy_trees_update',
            provider: Provider\TaxonomyTreeProvider::class,
            processor: Processor\UpdateTaxonomyTreeProcessor::class,
        ),
        new Delete(
            uriTemplate: '/taxonomy/trees/{id}',
            security: "is_granted('ROLE_ADMIN')",
            output: false,
            name: 'taxonomy_trees_delete',
            provider: Provider\TaxonomyTreeProvider::class,
            processor: Processor\DeleteTaxonomyTreeProcessor::class,
        ),
    ],
)]
final class TaxonomyTreeResource
{
    public function __construct(
        public ?string $id = null,
        public string $label = '',
        public string $code = '',
    ) {
    }
}
