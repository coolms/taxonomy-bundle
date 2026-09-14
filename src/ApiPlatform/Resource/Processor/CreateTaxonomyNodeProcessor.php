<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use CoolMS\Entity\Factory\EntityFactoryFactoryInterface;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Provider\TaxonomyNodeProvider;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\TaxonomyNodeResource;
use CoolMS\Taxonomy\Entity\TaxonomyNode;
use CoolMS\Taxonomy\Entity\TaxonomyNodeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyNodeRepositoryInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use CoolMS\Taxonomy\Service\TaxonomyTreeServiceInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/** @implements ProcessorInterface<TaxonomyNodeResource, TaxonomyNodeResource> */
final readonly class CreateTaxonomyNodeProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityFactoryFactoryInterface $entityFactoryFactory,
        private TaxonomyNodeRepositoryInterface $repository,
        private TaxonomyTreeRepositoryInterface $treeRepository,
        private TaxonomyTreeServiceInterface $taxonomyManager,
        private TaxonomyNodeProvider $provider,
    ) {
    }

    /**
     * @param TaxonomyNodeResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxonomyNodeResource
    {
        if ('' === trim($data->label)) {
            throw new BadRequestHttpException('label is required.');
        }

        if ('' === trim($data->slug)) {
            throw new BadRequestHttpException('slug is required.');
        }

        if (!TaxonomyNode::isValidSlug($data->slug)) {
            throw new BadRequestHttpException('slug must start with a letter and contain only lowercase letters, digits, or hyphens.');
        }

        if (null === $data->treeId || '' === trim($data->treeId)) {
            throw new BadRequestHttpException('treeId is required.');
        }

        // Resolve tree
        try {
            $treeUuid = Uuid::fromString($data->treeId);
        } catch (InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid treeId UUID.');
        }

        $tree = $this->treeRepository->find($treeUuid);
        if (null === $tree) {
            throw new NotFoundHttpException("Tree with id '$data->treeId' not found.");
        }

        /** @var TaxonomyNodeInterface $node */
        $node = $this->entityFactoryFactory->get(TaxonomyNodeInterface::class)->create([
            'label' => $data->label,
            'slug' => $data->slug,
            'tree' => $tree,
        ]);

        // Resolve optional parent
        $parent = null;
        if (null !== $data->parentId) {
            try {
                $parentUuid = Uuid::fromString($data->parentId);
            } catch (InvalidArgumentException) {
                throw new BadRequestHttpException('Invalid parentId UUID.');
            }

            /** @var TaxonomyNodeInterface|null $parent */
            $parent = $this->repository->find($parentUuid);
            if (null === $parent) {
                throw new NotFoundHttpException("Parent node with id '$data->parentId' not found.");
            }
        }

        $this->taxonomyManager->insertNode($node, $parent);

        return $this->provider->toResource($node);
    }
}
