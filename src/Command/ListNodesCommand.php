<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\Command;

use CoolMS\Taxonomy\Entity\TaxonomyNodeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyNodeRepositoryInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'coolms:taxonomy:node:list',
    description: 'List taxonomy nodes.',
)]
final class ListNodesCommand extends Command
{
    public function __construct(
        private readonly TaxonomyNodeRepositoryInterface $repository,
        private readonly TaxonomyTreeRepositoryInterface $treeRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filter by discriminator type (e.g. taxonomy_node, dynamic_entity_type)')
            ->addOption('tree', null, InputOption::VALUE_REQUIRED, 'Filter by tree code (e.g. dynamic_entity_types, default)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filterType = $input->getOption('type');
        $filterTree = $input->getOption('tree');

        // Resolve tree filter
        if (null !== $filterTree) {
            $tree = $this->treeRepository->findByCode($filterTree);
            if (null === $tree) {
                $io->error("No tree found with code '$filterTree'.");

                return Command::FAILURE;
            }
            $nodes = $this->repository->findByTree($tree);
        } else {
            $nodes = $this->repository->findAll();
        }

        // Apply type filter
        if (null !== $filterType) {
            $nodes = array_values(array_filter(
                $nodes,
                static fn (TaxonomyNodeInterface $n) => $n->type === $filterType,
            ));
        }

        $table = new Table($output);
        $table->setHeaders(['ID (short)', 'Tree', 'Type', 'Slug', 'Name', 'Level', 'lft', 'rgt', 'Parent slug']);

        foreach ($nodes as $node) {
            $id = substr($node->id->toRfc4122(), 0, 8) . '...';

            $treeCode = null !== $node->tree ? $node->tree->code : '–';
            $parentSlug = null !== $node->parent ? $node->parent->slug : '–';

            $table->addRow([
                $id,
                $treeCode,
                $node->type,
                $node->slug,
                $node->label,
                $node->level,
                $node->lft,
                $node->rgt,
                $parentSlug,
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
