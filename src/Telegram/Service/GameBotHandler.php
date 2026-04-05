<?php

namespace App\Telegram\Service;

use App\Entity\Game;
use App\Enum\GameStatus;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;

final readonly class GameBotHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private GameMessageParser $gameMessageParser,
        private GameMessageFormatter $gameMessageFormatter,
        private SteamMetadataUpdater $steamMetadataUpdater,
    ) {
    }

    public function help(Nutgram $bot): void
    {
        $bot->sendMessage(implode("\n", [
            'Доступные команды:',
            '/add_game {ссылка} {описание} - добавить одну игру',
            '/add_games + несколько блоков "ссылка + описание" через пустую строку - добавить несколько игр',
            '/get_games - список новых игр',
            '/get_done_games - список пройденных игр',
            '/get_dropped_games - список брошенных игр',
            '/done_game {game-id} - отметить игру как пройденную',
            '/drop_game {game-id} - отметить игру как брошенную',
            '/delete_game {game-id} - удалить игру из базы',
            'Если ссылка ведёт на Steam, бот попытается сам сохранить цену и недавние/все отзывы.',
            'Команды с параметрами не регистрируются в меню Telegram специально, чтобы не отправляться случайно без параметров.',
        ]));
    }

    public function addGame(Nutgram $bot): void
    {
        $message = $bot->message();

        if ($message === null) {
            return;
        }

        $parsedGame = $this->gameMessageParser->parseAddGame($message);

        if ($parsedGame === null) {
            $bot->sendMessage("Неверный формат.\nИспользуй:\n/add_game https://example.com Описание игры");
            return;
        }

        $this->addParsedGames($bot, [$parsedGame]);
    }

    public function addGames(Nutgram $bot): void
    {
        $message = $bot->message();

        if ($message === null) {
            return;
        }

        $parsedGames = $this->gameMessageParser->parseAddGames($message);

        if ($parsedGames === null) {
            $bot->sendMessage("Неверный формат.\nИспользуй:\n/add_games https://example.com/game-1 Описание 1\n\nhttps://example.com/game-2 Описание 2");
            return;
        }

        $this->addParsedGames($bot, $parsedGames);
    }

    /**
     * @param list<\App\Telegram\Dto\ParsedGameMessage> $parsedGames
     */
    private function addParsedGames(Nutgram $bot, array $parsedGames): void
    {
        $addedGames = [];

        foreach ($parsedGames as $parsedGame) {
            if (filter_var($parsedGame->url, FILTER_VALIDATE_URL) === false) {
                $bot->sendMessage(sprintf('Ссылка "%s" выглядит некорректной. Укажи полный URL, например https://example.com.', $parsedGame->url));
                return;
            }

            $game = (new Game())
                ->setUrl($parsedGame->url)
                ->setDescriptionText($parsedGame->descriptionText)
                ->setDescriptionEntities($parsedGame->descriptionEntities)
                ->setStatus(GameStatus::NEW);

            $this->steamMetadataUpdater->refresh($game);

            $this->entityManager->persist($game);
            $addedGames[] = $game;
        }

        $this->entityManager->flush();

        if (count($addedGames) === 1) {
            $bot->sendMessage(sprintf('Игра добавлена с ID %d.', $addedGames[0]->getId()));
            return;
        }

        $lines = [sprintf('Добавлено игр: %d', count($addedGames))];

        foreach ($addedGames as $game) {
            $lines[] = sprintf('#%d %s', $game->getId(), $game->getUrl());
        }

        $bot->sendMessage(implode("\n", $lines));
    }

    public function getGames(Nutgram $bot): void
    {
        $this->sendGamesByStatus($bot, GameStatus::NEW);
    }

    public function getDoneGames(Nutgram $bot): void
    {
        $this->sendGamesByStatus($bot, GameStatus::DONE);
    }

    public function getDroppedGames(Nutgram $bot): void
    {
        $this->sendGamesByStatus($bot, GameStatus::DROPPED);
    }

    public function doneGame(Nutgram $bot): void
    {
        $gameId = $this->gameMessageParser->parseGameIdArgument($bot->message()?->text, 'done_game');

        if ($gameId === null) {
            $bot->sendMessage("Неверный формат.\nИспользуй:\n/done_game {game-id}");
            return;
        }

        $this->changeStatusById($bot, $gameId, GameStatus::DONE);
    }

    public function dropGame(Nutgram $bot): void
    {
        $gameId = $this->gameMessageParser->parseGameIdArgument($bot->message()?->text, 'drop_game');

        if ($gameId === null) {
            $bot->sendMessage("Неверный формат.\nИспользуй:\n/drop_game {game-id}");
            return;
        }

        $this->changeStatusById($bot, $gameId, GameStatus::DROPPED);
    }

    public function deleteGame(Nutgram $bot): void
    {
        $gameId = $this->gameMessageParser->parseGameIdArgument($bot->message()?->text, 'delete_game');

        if ($gameId === null) {
            $bot->sendMessage("Неверный формат.\nИспользуй:\n/delete_game {game-id}");
            return;
        }

        $this->deleteGameById($bot, $gameId);
    }

    private function sendGamesByStatus(Nutgram $bot, GameStatus $status): void
    {
        $games = $this->gameRepository->findByStatus($status);

        if ($games === []) {
            $bot->sendMessage(sprintf('Список игр со статусом "%s" пуст.', $status->label()));
            return;
        }

        $chunks = $this->gameMessageFormatter->formatList(
            $games,
            sprintf('Найдено игр со статусом "%s": %d', $status->label(), count($games)),
        );

        foreach ($chunks as $chunk) {
            $bot->sendMessage($chunk['text'], entities: $chunk['entities']);
        }
    }

    public function changeStatusById(Nutgram $bot, int $gameId, GameStatus $newStatus): void
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game instanceof Game) {
            $bot->sendMessage(sprintf('Игра с ID %d не найдена.', $gameId));
            return;
        }

        if ($game->getStatus() === $newStatus) {
            $bot->sendMessage(sprintf('У игры #%d уже стоит статус "%s".', $gameId, $newStatus->label()));
            return;
        }

        $game->setStatus($newStatus);
        $this->entityManager->flush();

        $bot->sendMessage(sprintf('Статус игры #%d изменён на "%s".', $gameId, $newStatus->label()));
    }

    public function deleteGameById(Nutgram $bot, int $gameId): void
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game instanceof Game) {
            $bot->sendMessage(sprintf('Игра с ID %d не найдена.', $gameId));
            return;
        }

        $this->entityManager->remove($game);
        $this->entityManager->flush();

        $bot->sendMessage(sprintf('Игра #%d удалена из базы.', $gameId));
    }
}
