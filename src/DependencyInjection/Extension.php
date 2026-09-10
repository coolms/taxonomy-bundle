<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\DependencyInjection;

use CoolMS\Taxonomy\Entity\TaxonomyNode;
use CoolMS\Taxonomy\Entity\TaxonomyNodeInterface;
use CoolMS\Taxonomy\Entity\TaxonomyTree;
use CoolMS\Taxonomy\Entity\TaxonomyTreeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyNodeRepositoryInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use CoolMS\Taxonomy\Service\TaxonomyTreeService;
use CoolMS\Taxonomy\Service\TaxonomyTreeServiceInterface;
use CoolMS\Taxonomy\Doctrine\Repository\TaxonomyNodeRepository;
use CoolMS\Taxonomy\Doctrine\Repository\TaxonomyTreeRepository;
use CoolMS\Core\Hierarchy\NestedSetOperatorInterface;
use CoolMS\Core\Bundle\DependencyInjection\AbstractExtension;
use CoolMS\Entity\Doctrine\Tree\DoctrineNestedSetOperator as CoreDoctrineNestedSetOperator;
use CoolMS\Entity\Bundle\DependencyInjection\EntityFactoryRegistrationTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class Extension extends AbstractExtension implements PrependExtensionInterface
{
    use EntityFactoryRegistrationTrait;

    private const array RESOLVE_TARGET_ENTITIES = [
        TaxonomyNodeInterface::class => TaxonomyNode::class,
        TaxonomyTreeInterface::class => TaxonomyTree::class,
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->setResolveTargetEntities($container, self::RESOLVE_TARGET_ENTITIES);
        $this->registerEntityFactory($container, array_keys(self::RESOLVE_TARGET_ENTITIES));
        $this->registerServices($container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'resolve_target_entities' => self::RESOLVE_TARGET_ENTITIES,
                'entity_managers' => [
                    'central' => [
                        'mappings' => [
                            'TaxonomyBundle' => [
                                // !! `is_bundle` false, because the entities no longer
                                // live under this bundle: they ship in coolms/taxonomy.
                                // With `is_bundle` true, `dir` resolves against the bundle
                                // directory -- which is how this pointed at a path that had
                                // stopped existing while the `prefix` beside it was already
                                // correct, so the block read as consistent and was not.
                                // The path is vendor-relative for the same reason
                                // field-bundle's is project-relative: it has to hold
                                // wherever the package is installed from.
                                'is_bundle' => false,
                                // XML, and in a package of its own: the domain
                                // carries no Doctrine attributes at all, so the
                                // mapping cannot live beside the entities.
                                'type' => 'xml',
                                'dir' => '%kernel.project_dir%/vendor/coolms/taxonomy-doctrine/src/mapping',
                                'prefix' => 'CoolMS\Taxonomy\Entity',
                                'alias' => 'TaxonomyBundle',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function getAlias(): string
    {
        return 'taxonomy';
    }

    private function registerServices(ContainerBuilder $container): void
    {
        // ---- API surface, console and contributors ----
        // !! REGISTERED HERE BECAUSE THE SCAN NO LONGER SEES THEM. These classes
        // used to be picked up by the application's `App\:` prototype scan. They
        // ship in this package now, so the scan cannot reach them and every one
        // of them would silently cease to exist as a service -- which is exactly
        // what happened: seven tests failed asking the container for them, and
        // nothing else reported it. coolms/field-bundle registers its own for
        // the same reason.
        foreach ([
            \CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Provider\TaxonomyNodeProvider::class,
            \CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Provider\TaxonomyTreeProvider::class,
            \CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor\CreateTaxonomyNodeProcessor::class,
            \CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor\UpdateTaxonomyNodeProcessor::class,
            \CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor\DeleteTaxonomyNodeProcessor::class,
            \CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor\CreateTaxonomyTreeProcessor::class,
            \CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor\UpdateTaxonomyTreeProcessor::class,
            \CoolMS\Taxonomy\Bundle\ApiPlatform\Resource\Processor\DeleteTaxonomyTreeProcessor::class,
        ] as $apiService) {
            $container->autowire($apiService)->setPublic(true);
        }

        // Console commands and tagged contributors: autoconfiguration attaches
        // the tags, but only for services the container actually knows about.
        foreach ([
            \CoolMS\Taxonomy\Bundle\Command\CreateTreeCommand::class,
            \CoolMS\Taxonomy\Bundle\Command\ListNodesCommand::class,
            \CoolMS\Taxonomy\Bundle\Field\TaxonomyFieldWidgetProvider::class,
            \CoolMS\Taxonomy\Bundle\NaviGraph\CategoriesToolbarContributor::class,
        ] as $service) {
            $container->autowire($service)
                ->setAutoconfigured(true)
                ->setPublic(false);
        }

        // ---- Node repository ----
        // !! REGISTERED HERE, NOT SCANNED. These classes ship in
        // coolms/taxonomy-doctrine, so a consuming application service
        // scan no longer reaches them and the alias below would point at a
        // service nobody defined. Same shape as coolms/field-bundle, which
        // autowires its own repository for the same reason.
        $container->autowire(TaxonomyNodeRepository::class)
            ->addTag('doctrine.repository_service');
        $container->setAlias(TaxonomyNodeRepositoryInterface::class, TaxonomyNodeRepository::class)
            ->setPublic(false);

        // ---- Tree repository ----
        $container->autowire(TaxonomyTreeRepository::class)
            ->addTag('doctrine.repository_service');
        $container->setAlias(TaxonomyTreeRepositoryInterface::class, TaxonomyTreeRepository::class)
            ->setPublic(false);

        // ---- Nested-set operator -- Core's generic implementation wired for TaxonomyNode ----
        // $entityClass: the concrete entity class targeted by all DQL queries
        // $treeFkExpr:  DQL expression that resolves to the tree's UUID for alias 'n';
        //               IDENTITY(n.tree) is Doctrine shorthand for the FK column value
        // $entityClass MUST be the concrete Doctrine entity class, not an interface.
        // ManagerRegistry::getManagerForClass() cannot resolve interfaces; it needs
        // a class that has Doctrine ORM mapping metadata (via #[ORM\Entity] attribute).
        // TaxonomyNode is the concrete CTI root; TaxonomyNodeInterface is wired via
        // RESOLVE_TARGET_ENTITIES for association resolution only, not for EM lookup.
        $container->register(NestedSetOperatorInterface::class, CoreDoctrineNestedSetOperator::class)
            ->setAutowired(true)
            ->setArgument('$entityClass', TaxonomyNode::class)
            ->setArgument('$treeFkExpr', 'IDENTITY(n.tree)')
            ->setAutoconfigured(false)
            ->setPublic(false);

        // ---- Application TaxonomyManager ----
        $container->register(TaxonomyTreeServiceInterface::class, TaxonomyTreeService::class)
            ->setAutowired(true)
            ->setAutoconfigured(false)
            ->setPublic(false);
    }
}
