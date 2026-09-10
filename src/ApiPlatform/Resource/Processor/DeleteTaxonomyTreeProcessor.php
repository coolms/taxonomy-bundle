<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use CoolMS\Taxonomy\Entity\TaxonomyTree;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use CoolMS\Core\Bundle\ApiPlatform\UriVariableUuidExtractorTrait;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<mixed, null> */
final readonly class DeleteTaxonomyTreeProcessor implements ProcessorInterface
{
    use UriVariableUuidExtractorTrait;

    public function __construct(
        private TaxonomyTreeRepositoryInterface $repository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $uuid = $this->extractUuid($uriVariables);

        /** @var TaxonomyTree|null $tree */
        $tree = $this->repository->find($uuid);
        if (null === $tree) {
            throw new NotFoundHttpException('TaxonomyTree not found.');
        }

        // Guard: cannot delete a tree that still contains nodes. Use the
        // Domain repository's count helper rather than touching the
        // ORM-mapped Collection directly -- Doctrine types stay confined
        // to Infrastructure\Doctrine\.
        $nodeCount = $this->repository->countNodes($tree);
        if ($nodeCount > 0) {
            throw new ConflictHttpException(sprintf("Cannot delete tree '%s': it still contains %d node(s). Remove all nodes first.", $tree->code, $nodeCount));
        }

        $this->repository->delete($tree);

        return null;
    }
}
