<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use CoolMS\Taxonomy\Entity\TaxonomyTree;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Provider\TaxonomyTreeProvider;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\TaxonomyTreeResource;
use CoolMS\Core\Bundle\ApiPlatform\UriVariableUuidExtractorTrait;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TaxonomyTreeResource, TaxonomyTreeResource>
 */
final readonly class UpdateTaxonomyTreeProcessor implements ProcessorInterface
{
    use UriVariableUuidExtractorTrait;

    public function __construct(
        private TaxonomyTreeRepositoryInterface $repository,
        private TaxonomyTreeProvider $provider,
    ) {
    }

    /**
     * @param TaxonomyTreeResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxonomyTreeResource
    {
        $uuid = $this->extractUuid($uriVariables);
        /** @var TaxonomyTree|null $tree */
        $tree = $this->repository->find($uuid);
        if (null === $tree) {
            throw new NotFoundHttpException('TaxonomyTree not found.');
        }
        // Code is immutable after creation.
        if ('' !== trim($data->code) && $data->code !== $tree->code) {
            throw new BadRequestHttpException('code is immutable after creation.');
        }
        if ('' !== trim($data->label)) {
            $tree->label = $data->label;
        }
        $this->repository->save($tree);

        return $this->provider->toResource($tree);
    }
}
