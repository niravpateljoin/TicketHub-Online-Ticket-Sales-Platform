<?php

namespace App\Command;

use App\Repository\CategoryRepository;
use App\Repository\EventRepository;
use App\Repository\TicketTierRepository;
use App\Service\ApiDataTransformer;
use App\Service\Cache\CacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cache:warm',
    description: 'Pre-populate Redis caches for public categories and the first page of events.',
)]
class WarmCacheCommand extends Command
{
    public function __construct(
        private readonly CacheService $cache,
        private readonly CategoryRepository $categoryRepository,
        private readonly EventRepository $eventRepository,
        private readonly TicketTierRepository $tierRepository,
        private readonly ApiDataTransformer $transformer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Categories
        $this->cache->invalidateCategories();
        $this->cache->getCategories(function (): array {
            return array_map(
                fn ($cat): array => $this->transformer->category($cat),
                $this->categoryRepository->findBy([], ['name' => 'ASC'])
            );
        });
        $io->writeln('  [OK] categories');

        // Events list — page 1, no filters (most common cold-start request)
        $perPage   = 10;
        $filterKey = 'all';

        $this->cache->getEventsList($filterKey, 1, $perPage, function () use ($perPage): array {
            $qb = $this->eventRepository->createQueryBuilder('event')
                ->join('event.category', 'category')
                ->addSelect('category')
                ->orderBy('event.dateTime', 'ASC')
                ->setMaxResults($perPage);

            $events = $qb->getQuery()->getResult();

            return array_map(
                fn ($event): array => $this->transformer->eventSummary($event),
                $events
            );
        });
        $io->writeln('  [OK] events list (page 1)');

        $io->success('Cache warm-up complete.');

        return Command::SUCCESS;
    }
}
