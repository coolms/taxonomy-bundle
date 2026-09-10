<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use CoolMS\Taxonomy\Entity\TaxonomyNode;
use CoolMS\Taxonomy\Entity\TaxonomyNodeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyNodeRepositoryInterface;
use CoolMS\Taxonomy\Service\TaxonomyTreeServiceInterface;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Provider\TaxonomyNodeProvider;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\TaxonomyNodeResource;
use CoolMS\Core\Bundle\ApiPlatform\UriVariableUuidExtractorTrait;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/** @implements ProcessorInterface<TaxonomyNodeResource, TaxonomyNodeResource> */
final readonly class UpdateTaxonomyNodeProcessor implements ProcessorInterface
{
    use UriVariableUuidExtractorTrait;

    public function __construct(
        private TaxonomyNodeRepositoryInterface $repository,
        private TaxonomyTreeServiceInterface $taxonomyManager,
        private TaxonomyNodeProvider $provider,
    ) {
    }

    /**
     * @param TaxonomyNodeResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxonomyNodeResource
    {
        // A provided (non-empty) slug must be well-formed; an empty slug means
        // "leave it unchanged" (partial update). Checked up front, before any DB
        // lookup, so a malformed slug 400s without touching persistence.
        if ('' !== trim($data->slug) && !TaxonomyNode::isValidSlug($data->slug)) {
            throw new BadRequestHttpException('slug must start with a letter and contain only lowercase letters, digits, or hyphens.');
        }

        $uuid = $this->extractUuid($uriVariables);
        /** @var TaxonomyNode|null $node */
        $node = $this->repository->find($uuid);
        if (null === $node) {
            throw new NotFoundHttpException('TaxonomyNode not found.');
        }
        // Guard: a tree is immutable after creation.
        if (null !== $data->treeId && $data->treeId !== $node->tree?->id?->toRfc4122()) {
            throw new BadRequestHttpException('treeId is immutable after node creation.');
        }
        if ('' !== trim($data->label)) {
            $node->label = $data->label;
        }
        if ('' !== trim($data->slug)) {
            $node->slug = $data->slug;
        }
        // Handle parent change.
        $currentParentId = $node->parent instanceof TaxonomyNode ? $node->parent->id->toRfc4122() : null;
        $newParentId = $data->parentId;
        if ($currentParentId !== $newParentId) {
            $newParent = null;
            if (null !== $newParentId) {
                try {
                    $parentUuid = Uuid::fromString($newParentId);
                } catch (InvalidArgumentException) {
                    throw new BadRequestHttpException('Invalid parentId UUID.');
                }
                /** @var TaxonomyNodeInterface|null $newParent */
                $newParent = $this->repository->find($parentUuid);
                if (null === $newParent) {
                    throw new NotFoundHttpException("Parent node with id '$newParentId' not found.");
                }
            }
            $this->taxonomyManager->moveNode($node, $newParent);
        } else {
            $this->repository->save($node);
        }

        return $this->provider->toResource($node);
    }
}
