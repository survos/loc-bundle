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

#[AsCommand(name: 'loc:search', description: 'Search the Library of Congress API.')]
final class LocSearchCommand extends Command
{
    public function __construct(private readonly LocClientInterface $locClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('query', null, 'The search query (LOC "q" parameter).')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max results per page (LOC "c" parameter).', 20)
            ->addOption('facet', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Repeatable "fa" facet filter, e.g. original-format:sound+recording.', [])
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Dump the raw decoded response instead of a table.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $query = ['c' => (int) $input->getOption('limit')];
        if ($q = $input->getArgument('query')) {
            $query['q'] = $q;
        }
        if ($facets = $input->getOption('facet')) {
            $query['fa'] = implode(',', $facets);
        }

        $response = $this->locClient->search($query);

        if ($input->getOption('raw')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $rows = array_map(
            static fn (array $result) => [$result['id'] ?? '', $result['title'] ?? '', $result['date'] ?? ''],
            $response['results'] ?? [],
        );
        $io->table(['ID', 'Title', 'Date'], $rows);
        $io->comment(sprintf('%d of %s results shown.', count($rows), $response['pagination']['of'] ?? '?'));

        return Command::SUCCESS;
    }
}
