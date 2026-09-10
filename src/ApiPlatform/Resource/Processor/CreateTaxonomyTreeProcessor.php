<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use CoolMS\Taxonomy\Entity\TaxonomyTreeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Provider\TaxonomyTreeProvider;
use CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\TaxonomyTreeResource;
use CoolMS\Entity\Factory\EntityFactoryFactoryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @implements ProcessorInterface<TaxonomyTreeResource, TaxonomyTreeResource> */
final readonly class CreateTaxonomyTreeProcessor implements ProcessorInterface
{
    /**
     * Codes under this namespace are minted only by the Navi--Taxonomy mirror
     * (the navigation sync listener a consuming application registers), which creates a
     * mirror TaxonomyTree coded after the owning NaviTree slug (`navi.*`) and
     * later REUSES any pre-existing tree found via `findByCode()`. Letting an
     * admin pre-create a `navi.*` tree here would let the mirror silently
     * attach to an admin-shaped tree, blurring the 1:1 mirror invariant
     * (#1163). The prefix is reserved for the mirror's internal use; a user
     * has no legitimate reason to author one directly.
     */
    private const string RESERVED_CODE_PREFIX = 'navi.';

    public function __construct(
        private EntityFactoryFactoryInterface $entityFactoryFactory,
        private TaxonomyTreeRepositoryInterface $repository,
        private TaxonomyTreeProvider $provider,
    ) {
    }

    /**
     * @param TaxonomyTreeResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxonomyTreeResource
    {
        if ('' === trim($data->label)) {
            throw new BadRequestHttpException('label is required.');
        }

        if ('' === trim($data->code)) {
            throw new BadRequestHttpException('code is required.');
        }

        if (str_starts_with(trim($data->code), self::RESERVED_CODE_PREFIX)) {
            throw new ConflictHttpException('The `navi.*` namespace is reserved for the Navi mirror.');
        }

        $existing = $this->repository->findByCode($data->code);
        if (null !== $existing) {
            throw new ConflictHttpException("A TaxonomyTree with code '$data->code' already exists.");
        }

        /** @var TaxonomyTreeInterface $tree */
        $tree = $this->entityFactoryFactory->get(TaxonomyTreeInterface::class)->create([
            'label' => $data->label,
            'code' => $data->code,
        ]);
        $this->repository->save($tree);

        return $this->provider->toResource($tree);
    }
}
