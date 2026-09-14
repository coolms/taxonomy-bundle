<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use CoolMS\Core\Bundle\ApiPlatform\UriVariableUuidExtractorTrait;
use CoolMS\Taxonomy\Entity\TaxonomyNode;
use CoolMS\Taxonomy\Repository\TaxonomyNodeRepositoryInterface;
use CoolMS\Taxonomy\Service\TaxonomyTreeServiceInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<mixed, null> */
final readonly class DeleteTaxonomyNodeProcessor implements ProcessorInterface
{
    use UriVariableUuidExtractorTrait;

    public function __construct(
        private TaxonomyNodeRepositoryInterface $repository,
        private TaxonomyTreeServiceInterface $taxonomyManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $uuid = $this->extractUuid($uriVariables);

        /** @var TaxonomyNode|null $node */
        $node = $this->repository->find($uuid);
        if (null === $node) {
            throw new NotFoundHttpException('TaxonomyNode not found.');
        }

        // TaxonomyManager handles nested-set gap closure and ORM delete.
        $this->taxonomyManager->removeNode($node);

        return null;
    }
}
