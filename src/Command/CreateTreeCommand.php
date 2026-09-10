<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\Command;

use CoolMS\Taxonomy\Entity\TaxonomyTreeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use CoolMS\Entity\Factory\EntityFactoryFactoryInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'coolms:taxonomy:tree:create',
    description: 'Create a new TaxonomyTree (named tree scope).',
)]
final class CreateTreeCommand extends Command
{
    public function __construct(
        private readonly EntityFactoryFactoryInterface $entityFactoryFactory,
        private readonly TaxonomyTreeRepositoryInterface $repository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Unique machine code (e.g. catalog, products)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Human-readable name (e.g. "Product Catalog")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');
        assert($helper instanceof QuestionHelper);

        // --- code ---
        $code = $input->getOption('code');
        if (null === $code) {
            $q = new Question('Tree code (e.g. <info>catalog</info>): ');
            $q->setValidator(static function ($v) {
                $v = trim((string) $v);
                if (!preg_match('/^[a-z0-9_]+$/', $v)) {
                    throw new InvalidArgumentException('Code must match /^[a-z0-9_]+$/.');
                }

                return $v;
            });
            $code = $helper->ask($input, $output, $q);
        }

        // Check uniqueness
        if (null !== $this->repository->findByCode($code)) {
            $io->error("A TaxonomyTree with code '$code' already exists.");

            return Command::FAILURE;
        }

        // --- name ---
        $name = $input->getOption('name');
        if (null === $name) {
            $defaultName = ucwords(str_replace('_', ' ', $code));
            $q = new Question(sprintf('Human-readable name [<info>%s</info>]: ', $defaultName), $defaultName);
            $name = trim((string) $helper->ask($input, $output, $q));
            if ('' === $name) {
                $name = $defaultName;
            }
        }

        /** @var TaxonomyTreeInterface $tree */
        $tree = $this->entityFactoryFactory->get(TaxonomyTreeInterface::class)->create([
            'name' => $name,
            'code' => $code,
        ]);
        $this->repository->save($tree);

        $io->success(sprintf(
            'TaxonomyTree created: [%s] "%s" -- id: %s',
            $code,
            $name,
            $tree->id->toRfc4122(),
        ));

        return Command::SUCCESS;
    }
}
