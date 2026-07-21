<?php

declare(strict_types=1);

namespace Survos\LocBundle\Command;

use Survos\LocBundle\Client\LocClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'loc:collection:list', description: 'List items in a Library of Congress collection.')]
final class LocCollectionListCommand extends Command
{
    public function __construct(private readonly LocClientInterface $locClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('collection', null, 'Collection slug, e.g. voices-remembering-slavery.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max results to list (across pages, following pagination.next).', 20);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $collection = (string) $input->getArgument('collection');
        $limit = (int) $input->getOption('limit');

        $rows = [];
        foreach ($this->locClient->iterateCollection($collection) as $result) {
            $rows[] = [$result->id, $result->title, $result->date, implode(', ', $result->originalFormat), implode(', ', $result->onlineFormat)];
            if (count($rows) >= $limit) {
                break;
            }
        }

        $io->table(['ID', 'Title', 'Date', 'Original format', 'Online formats'], $rows);

        return Command::SUCCESS;
    }
}
