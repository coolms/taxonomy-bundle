<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\DependencyInjection\Compiler;

use CoolMS\Taxonomy\Repository\TaxonomyNodeRepositoryInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use CoolMS\Taxonomy\Doctrine\Repository\TaxonomyNodeRepository;
use CoolMS\Taxonomy\Doctrine\Repository\TaxonomyTreeRepository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Taxonomy Services Wiring Compiler Pass.
 *
 * Wires autowired repositories with the correct ManagerRegistry service,
 * which is only known after all bundles have loaded.
 *
 * Note: TaxonomyManager (Application layer) and DoctrineNestedSetOperator
 * are fully autowired via ManagerRegistry -- no explicit wiring needed.
 */
final class TaxonomyPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Wire node repositories with the correct ManagerRegistry
        foreach ([TaxonomyNodeRepository::class, TaxonomyNodeRepositoryInterface::class] as $id) {
            if ($container->has($id)) {
                $container->findDefinition($id)
                    ->setArgument('$registry', new Reference('doctrine'));
            }
        }

        // Wire tree repositories with the correct ManagerRegistry
        foreach ([TaxonomyTreeRepository::class, TaxonomyTreeRepositoryInterface::class] as $id) {
            if ($container->has($id)) {
                $container->findDefinition($id)
                    ->setArgument('$registry', new Reference('doctrine'));
            }
        }
    }
}
