<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use CoolMS\Core\Bundle\ApiPlatform\UriVariableUuidExtractorTrait;
use CoolMS\Core\Bundle\Rql\RequestRqlParser;
use CoolMS\Core\DataGrid\DataGridConfig;
use CoolMS\Core\DataGrid\DataGridConfigProviderInterface;
use CoolMS\Rql\RqlContext;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\TaxonomyNodeResource;
use CoolMS\Taxonomy\Entity\TaxonomyNode;
use CoolMS\Taxonomy\Entity\TaxonomyNodeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyNodeRepositoryInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<TaxonomyNodeResource> */
final readonly class TaxonomyNodeProvider implements ProviderInterface
{
    use UriVariableUuidExtractorTrait;

    /**
     * @param iterable<DataGridConfigProviderInterface> $gridConfigProviders
     */
    public function __construct(
        private TaxonomyNodeRepositoryInterface $repository,
        private TaxonomyTreeRepositoryInterface $treeRepository,
        private RequestRqlParser $rqlParser,
        private RequestStack $requestStack,
        #[AutowireIterator('coolms.datagrid.config_provider')]
        private iterable $gridConfigProviders = [],
    ) {
    }

    /**
     * @return TaxonomyNodeResource|TaxonomyNodeResource[]|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            // Tree-scoped list: `?tree=<code>` returns only that tree's nodes
            // (e.g. the field-widget category picker scopes to `categories`).
            $treeCode = $this->requestStack->getCurrentRequest()?->query->get('tree');
            if (is_string($treeCode) && '' !== $treeCode) {
                $tree = $this->treeRepository->findByCode($treeCode);
                if (null === $tree) {
                    return [];
                }

                return array_map(
                    fn (TaxonomyNodeInterface $node) => $this->toResource($node),
                    $this->repository->findByTree($tree),
                );
            }

            $query = $this->rqlParser->parseFromRequest($this->requestStack->getCurrentRequest());

            if ($query->isEmpty()) {
                return array_map(
                    fn (TaxonomyNodeInterface $node) => $this->toResource($node),
                    iterator_to_array($this->repository->findAll()),
                );
            }

            $ctx = $this->buildRqlContext();
            $result = $this->repository->findByRql($query, $ctx);

            /** @var TaxonomyNodeInterface[] $items */
            $items = $result->items;

            return array_map(fn (TaxonomyNodeInterface $node) => $this->toResource($node), $items);
        }

        $uuid = $this->tryExtractUuid($uriVariables);
        if (null === $uuid) {
            return null;
        }

        /** @var TaxonomyNodeInterface|null $node */
        $node = $this->repository->find($uuid);
        if (null === $node) {
            throw new NotFoundHttpException('TaxonomyNode not found.');
        }

        return $this->toResource($node);
    }

    public function toResource(TaxonomyNodeInterface $node): TaxonomyNodeResource
    {
        return new TaxonomyNodeResource(
            $node->id->toRfc4122(),
            $node->label,
            '',
            $node->slug,
            $node->tree?->id->toRfc4122(),
            $node->parent?->id->toRfc4122(),
            $node->level,
            $node->lft,
            $node->rgt,
            $node->type,
        );
    }

    /**
     * Derive the RQL whitelist from the `entity:CoolMS\Taxonomy\Entity\TaxonomyNode` grid config.
     *
     * allowedFields -- every column declaring a filterOp.
     * fieldMap      -- columns declaring a filterField override.
     */
    private function buildRqlContext(): RqlContext
    {
        $cfg = $this->resolveGridConfig('entity:' . TaxonomyNode::class);

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

        return new RqlContext(entityAlias: 'n', allowedFields: $allowedFields, fieldMap: $fieldMap);
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
