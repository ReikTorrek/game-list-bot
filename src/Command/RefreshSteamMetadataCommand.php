<?php

namespace App\Command;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Telegram\Service\SteamMetadataUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:refresh-steam-metadata',
    description: 'Refresh Steam title, price and reviews for stored games.',
)]
final class RefreshSteamMetadataCommand extends Command
{
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly SteamMetadataUpdater $steamMetadataUpdater,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Refresh only one game by ID')
            ->addOption('all-steam', null, InputOption::VALUE_NONE, 'Refresh all games with Steam URLs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getOption('id');
        $refreshAllSteam = (bool) $input->getOption('all-steam');

        if ($id === null && !$refreshAllSteam) {
            $io->error('Укажи либо --id=123, либо --all-steam.');

            return Command::INVALID;
        }

        if ($id !== null && $refreshAllSteam) {
            $io->error('Используй либо --id, либо --all-steam, но не вместе.');

            return Command::INVALID;
        }

        $games = $id !== null
            ? $this->loadSingleGame((int) $id, $io)
            : $this->loadSteamGames();

        if ($games === []) {
            if ($id === null) {
                $io->warning('Steam-игры для обновления не найдены.');
            }

            return Command::SUCCESS;
        }

        $updated = 0;

        foreach ($games as $game) {
            if ($this->steamMetadataUpdater->refresh($game)) {
                ++$updated;
                $io->writeln(sprintf(
                    'Обновлена игра #%d%s',
                    $game->getId(),
                    $game->getSteamName() !== null ? sprintf(' (%s)', $game->getSteamName()) : ''
                ));
            } else {
                $io->writeln(sprintf('Пропущена игра #%d: не удалось получить Steam-метаданные.', $game->getId()));
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Обновлено игр: %d из %d.', $updated, count($games)));

        return Command::SUCCESS;
    }

    /**
     * @return list<Game>
     */
    private function loadSingleGame(int $id, SymfonyStyle $io): array
    {
        $game = $this->gameRepository->find($id);

        if (!$game instanceof Game) {
            $io->error(sprintf('Игра с ID %d не найдена.', $id));

            return [];
        }

        return [$game];
    }

    /**
     * @return list<Game>
     */
    private function loadSteamGames(): array
    {
        return $this->gameRepository->createQueryBuilder('g')
            ->andWhere('g.url LIKE :steamHost')
            ->setParameter('steamHost', '%store.steampowered.com/app/%')
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
