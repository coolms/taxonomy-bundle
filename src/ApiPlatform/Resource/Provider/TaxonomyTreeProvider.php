<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use CoolMS\Core\DataGrid\DataGridConfig;
use CoolMS\Core\DataGrid\DataGridConfigProviderInterface;
use CoolMS\Taxonomy\Entity\TaxonomyTreeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\TaxonomyTreeResource;
use CoolMS\Core\Bundle\ApiPlatform\UriVariableUuidExtractorTrait;
use CoolMS\Core\Bundle\Rql\RequestRqlParser;
use CoolMS\Rql\RqlContext;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use CoolMS\Taxonomy\Entity\TaxonomyTree;

/** @implements ProviderInterface<TaxonomyTreeResource> */
final readonly class TaxonomyTreeProvider implements ProviderInterface
{
    use UriVariableUuidExtractorTrait;

    /**
     * @param iterable<DataGridConfigProviderInterface> $gridConfigProviders
     */
    public function __construct(
        private TaxonomyTreeRepositoryInterface $repository,
        private RequestRqlParser $rqlParser,
        private RequestStack $requestStack,
        #[AutowireIterator('coolms.datagrid.config_provider')]
        private iterable $gridConfigProviders = [],
    ) {
    }

    /**
     * @return TaxonomyTreeResource|TaxonomyTreeResource[]|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $query = $this->rqlParser->parseFromRequest($this->requestStack->getCurrentRequest());

            if ($query->isEmpty()) {
                return array_map(
                    fn (TaxonomyTreeInterface $tree) => $this->toResource($tree),
                    iterator_to_array($this->repository->findAll()),
                );
            }

            $ctx = $this->buildRqlContext();
            $result = $this->repository->findByRql($query, $ctx);

            /** @var TaxonomyTreeInterface[] $items */
            $items = $result->items;

            return array_map(fn (TaxonomyTreeInterface $tree) => $this->toResource($tree), $items);
        }

        $uuid = $this->tryExtractUuid($uriVariables);
        if (null === $uuid) {
            return null;
        }

        /** @var TaxonomyTreeInterface|null $tree */
        $tree = $this->repository->find($uuid);
        if (null === $tree) {
            throw new NotFoundHttpException('TaxonomyTree not found.');
        }

        return $this->toResource($tree);
    }

    public function toResource(TaxonomyTreeInterface $tree): TaxonomyTreeResource
    {
        return new TaxonomyTreeResource(
            $tree->id->toRfc4122(),
            $tree->label,
            $tree->code,
        );
    }

    /**
     * Derive the RQL whitelist from the `entity:CoolMS\Taxonomy\Entity\TaxonomyTree` grid config.
     *
     * allowedFields -- every column declaring a filterOp.
     * fieldMap      -- columns declaring a filterField override.
     */
    private function buildRqlContext(): RqlContext
    {
        $cfg = $this->resolveGridConfig('entity:'.TaxonomyTree::class);

        $allowedFields = [];
        $fieldMap = [];

        if (null !== $cfg) {
            foreach ($cfg->columns as $col) {
                if (null === $col->filterOp) {
                    continue;
                }
                $allowedFields[] = $col->field;
                if (null !== $col->filterField) {
                    $fieldMap[$col->field] = $col->filterField;
                }
            }
        }

        return new RqlContext(entityAlias: 't', allowedFields: $allowedFields, fieldMap: $fieldMap);
    }

    private function resolveGridConfig(string $id): ?DataGridConfig
    {
        foreach ($this->gridConfigProviders as $provider) {
            $cfg = $provider->provide($id);
            if (null !== $cfg) {
                return $cfg;
            }
        }

        return null;
    }
}
