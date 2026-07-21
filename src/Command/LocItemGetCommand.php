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

#[AsCommand(name: 'loc:item:get', description: 'Fetch a single Library of Congress item.')]
final class LocItemGetCommand extends Command
{
    public function __construct(private readonly LocClientInterface $locClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', null, 'Item id or canonical loc.gov/item/{id}/ URL.')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Dump the raw decoded response.')
            ->addOption('resources', null, InputOption::VALUE_NONE, 'List the top-level `resources` entries found on the item.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $idOrUrl = (string) $input->getArgument('id');

        $item = $this->locClient->getItemDto($idOrUrl);

        if ($input->getOption('raw')) {
            $io->writeln(json_encode($item->raw, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->definitionList(
            ['ID' => $item->id],
            ['URL' => $item->url],
            ['Title' => $item->title ?? '(none)'],
            ['Resources' => (string) count($item->resources)],
        );

        if ($input->getOption('resources')) {
            $io->writeln(json_encode($item->resources, JSON_PRETTY_PRINT));
        }

        return Command::SUCCESS;
    }
}
