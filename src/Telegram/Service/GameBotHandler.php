<?php

namespace App\Telegram\Service;

use App\Entity\Game;
use App\Enum\GameStatus;
use App\Repository\GameRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final readonly class GameBotHandler
{
    private const GAMES_PAGE_CALLBACK_PREFIX = 'games_page';
    private const GAMES_CLOSE_CALLBACK_ACTION = 'close';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private GameMessageParser $gameMessageParser,
        private GameMessageFormatter $gameMessageFormatter,
        private GameTitleFinder $gameTitleFinder,
        private SteamStoreClient $steamStoreClient,
        private SteamMetadataUpdater $steamMetadataUpdater,
    ) {
    }

    public function help(Nutgram $bot): void
    {
        $bot->sendMessage(implode("\n", [
            'Доступные команды:',
            '/add_game {ссылка} [описание] - добавить одну игру',
            '/add_games + несколько блоков "ссылка + описание" через пустую строку - добавить несколько игр',
            '/get_games - список новых игр',
            '/get_done_games - список пройденных игр',
            '/get_dropped_games - список брошенных игр',
            '/find_game {название} - найти одну игру по названию, даже с небольшой опечаткой',
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
            $bot->sendMessage("Неверный формат.\nИспользуй:\n/add_game https://example.com [Описание игры]");
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
            $bot->sendMessage("Неверный формат.\nИспользуй:\n/add_games https://example.com/game-1 [Описание 1]\n\nhttps://example.com/game-2 [Описание 2]");
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
                ->setDescriptionEntities($parsedGame->descriptionEntities)
                ->setSteamAppId($this->steamStoreClient->extractAppId($parsedGame->url))
                ->setStatus(GameStatus::NEW);

            $steamMetadata = $this->steamMetadataUpdater->refresh($game);

            $descriptionText = $parsedGame->descriptionText ?? $steamMetadata?->shortDescription;

            if (null === $descriptionText) {
                $bot->sendMessage('Не удалось получить краткое описание из Steam. Добавь описание после ссылки.');
                return;
            }

            $game->setDescriptionText($descriptionText);

            $duplicateMessage = $this->buildSteamDuplicateMessage($game, $addedGames);

            if ($duplicateMessage !== null) {
                $bot->sendMessage($duplicateMessage);
                return;
            }

            $this->entityManager->persist($game);
            $addedGames[] = $game;
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $bot->sendMessage('Не удалось добавить игру: игра с таким Steam App ID уже есть в базе.');
            return;
        }

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

    /**
     * @param list<Game> $pendingGames
     */
    private function buildSteamDuplicateMessage(Game $game, array $pendingGames): ?string
    {
        $steamAppId = $game->getSteamAppId();

        if ($steamAppId === null) {
            return null;
        }

        foreach ($pendingGames as $pendingGame) {
            if ($pendingGame->getSteamAppId() !== $steamAppId) {
                continue;
            }

            return sprintf(
                'Игра со Steam App ID %d уже есть в текущем списке на добавление: %s',
                $steamAppId,
                $pendingGame->getUrl(),
            );
        }

        $existingGame = $this->gameRepository->findOneBySteamAppId($steamAppId);

        if (!$existingGame instanceof Game) {
            return null;
        }

        $existingId = $existingGame->getId();

        return $existingId === null
            ? sprintf('Игра со Steam App ID %d уже есть в базе.', $steamAppId)
            : sprintf('Игра со Steam App ID %d уже есть в базе как #%d.', $steamAppId, $existingId);
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

    public function findGame(Nutgram $bot): void
    {
        $query = $this->gameMessageParser->parseCommandArgument($bot->message()?->text, 'find_game');

        if ($query === null) {
            $bot->sendMessage("Неверный формат.\nИспользуй:\n/find_game Название игры");
            return;
        }

        $game = $this->gameTitleFinder->findBestMatch($query);

        if (!$game instanceof Game) {
            $bot->sendMessage(sprintf('Не удалось найти игру по запросу "%s".', $query));
            return;
        }

        $formatted = $this->gameMessageFormatter->format($game);
        $bot->sendMessage($formatted['text'], entities: $formatted['entities']);
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

        $firstPage = $chunks[0];

        $bot->sendMessage(
            $firstPage['text'],
            entities: $firstPage['entities'],
            reply_markup: $this->buildGamesPaginationKeyboard($status, 0, count($chunks)),
        );
    }

    public function handleGamesPagination(Nutgram $bot): void
    {
        $callbackQuery = $bot->callbackQuery();
        $callbackData = $callbackQuery?->data;

        if ($callbackData === null) {
            return;
        }

        $pageRequest = $this->parseGamesPageCallbackData($callbackData);

        if ($pageRequest === null) {
            return;
        }

        $bot->answerCallbackQuery();

        $message = $callbackQuery->message;

        if ($message === null) {
            return;
        }

        if ($pageRequest['action'] === self::GAMES_CLOSE_CALLBACK_ACTION) {
            $bot->deleteMessage(
                chat_id: $message->chat->id,
                message_id: $message->message_id,
            );
            return;
        }

        $games = $this->gameRepository->findByStatus($pageRequest['status']);

        if ($games === []) {
            $bot->editMessageText(
                sprintf('Список игр со статусом "%s" пуст.', $pageRequest['status']->label()),
                chat_id: $message->chat->id,
                message_id: $message->message_id,
            );
            return;
        }

        $pages = $this->gameMessageFormatter->formatList(
            $games,
            sprintf('Найдено игр со статусом "%s": %d', $pageRequest['status']->label(), count($games)),
        );

        $pageIndex = min($pageRequest['page'], count($pages) - 1);
        $page = $pages[$pageIndex];

        $bot->editMessageText(
            $page['text'],
            chat_id: $message->chat->id,
            message_id: $message->message_id,
            entities: $page['entities'],
            reply_markup: $this->buildGamesPaginationKeyboard($pageRequest['status'], $pageIndex, count($pages)),
        );
    }

    private function buildGamesPaginationKeyboard(GameStatus $status, int $pageIndex, int $pageCount): InlineKeyboardMarkup
    {
        $buttons = [];

        if ($pageIndex > 0) {
            $buttons[] = InlineKeyboardButton::make(
                text: 'Назад',
                callback_data: $this->buildGamesPageCallbackData($status, $pageIndex - 1),
            );
        }

        if ($pageIndex < $pageCount - 1) {
            $buttons[] = InlineKeyboardButton::make(
                text: 'Далее',
                callback_data: $this->buildGamesPageCallbackData($status, $pageIndex + 1),
            );
        }

        $buttons[] = InlineKeyboardButton::make(
            text: 'Закрыть',
            callback_data: $this->buildGamesCloseCallbackData($status),
        );

        return InlineKeyboardMarkup::make()->addRow(...$buttons);
    }

    private function buildGamesPageCallbackData(GameStatus $status, int $pageIndex): string
    {
        return sprintf('%s:%s:%d', self::GAMES_PAGE_CALLBACK_PREFIX, $status->value, $pageIndex);
    }

    private function buildGamesCloseCallbackData(GameStatus $status): string
    {
        return sprintf('%s:%s:%s', self::GAMES_PAGE_CALLBACK_PREFIX, $status->value, self::GAMES_CLOSE_CALLBACK_ACTION);
    }

    /**
     * @return array{status: GameStatus, page: int, action: 'page'|'close'}|null
     */
    private function parseGamesPageCallbackData(string $callbackData): ?array
    {
        if (!preg_match('/^' . self::GAMES_PAGE_CALLBACK_PREFIX . ':(new|done|dropped):(\d+|close)$/', $callbackData, $matches)) {
            return null;
        }

        $status = GameStatus::tryFrom($matches[1]);

        if (!$status instanceof GameStatus) {
            return null;
        }

        return [
            'status' => $status,
            'page' => $matches[2] === self::GAMES_CLOSE_CALLBACK_ACTION ? 0 : (int) $matches[2],
            'action' => $matches[2] === self::GAMES_CLOSE_CALLBACK_ACTION ? self::GAMES_CLOSE_CALLBACK_ACTION : 'page',
        ];
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
