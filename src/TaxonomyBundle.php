<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle;

use CoolMS\Core\Bundle\AbstractCoolmsBundle;
use CoolMS\Taxonomy\Bundle\DependencyInjection\Compiler\TaxonomyPass;
use CoolMS\Taxonomy\Bundle\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TaxonomyBundle extends AbstractCoolmsBundle
{
    public const string COMPONENT_NAME = 'taxonomy';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TaxonomyPass());
    }

    public function getContainerExtension(): Extension
    {
        return new Extension();
    }
}
