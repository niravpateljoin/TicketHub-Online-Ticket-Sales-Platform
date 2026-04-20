<?php

namespace App\Command;

use App\Entity\SeatReservation;
use App\Repository\SeatReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Expires all pending SeatReservations whose expiresAt has passed.
 *
 * Run every minute via cron:
 *   * * * * * php /var/www/html/.../bin/console app:expire-reservations
 *
 * This is the "release valve" for Challenge 2 (soft-lock seat hold):
 * seats held by abandoned carts are freed so other users can buy them.
 */
#[AsCommand(
    name: 'app:expire-reservations',
    description: 'Mark all overdue pending seat reservations as expired.',
)]
class ExpireSeatReservationsCommand extends Command
{
    public function __construct(
        private readonly SeatReservationRepository $seatReservationRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expired = $this->seatReservationRepository->findExpiredPending();

        if ($expired === []) {
            $io->success('No expired reservations found.');
            return Command::SUCCESS;
        }

        foreach ($expired as $reservation) {
            $reservation->setStatus(SeatReservation::STATUS_EXPIRED);
        }

        $this->em->flush();

        $io->success(sprintf('Expired %d reservation(s).', count($expired)));

        return Command::SUCCESS;
    }
}
