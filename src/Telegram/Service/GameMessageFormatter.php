<?php

namespace App\Telegram\Service;

use App\Entity\Game;
use SergiX44\Nutgram\Telegram\Properties\MessageEntityType;

final readonly class GameMessageFormatter
{
    private const TELEGRAM_TEXT_LIMIT = 4096;

    public function __construct(private Utf16String $utf16String)
    {
    }

    /**
     * @return array{text: string, entities: list<array<string, mixed>>}
     */
    public function format(Game $game): array
    {
        $id = $game->getId();

        if ($id === null) {
            throw new \LogicException('Game ID must be available before formatting.');
        }

        $prefix = sprintf(
            "Игра #%d\nСтатус: %s\nСсылка: %s\n\nОписание:\n",
            $id,
            $game->getStatus()->label(),
            $game->getUrl(),
        );

        $shift = $this->utf16String->length($prefix);
        $entities = [];

        foreach ($game->getDescriptionEntities() ?? [] as $entity) {
            if (!is_array($entity)) {
                continue;
            }

            $offset = $entity['offset'] ?? null;
            $length = $entity['length'] ?? null;

            if (!is_int($offset) || !is_int($length)) {
                continue;
            }

            $entity['offset'] = $offset + $shift;
            $entities[] = $entity;
        }

        $text = $prefix . $game->getDescriptionText();
        $entities[] = [
            'type' => MessageEntityType::BLOCKQUOTE->value,
            'offset' => 0,
            'length' => $this->utf16String->length($text),
        ];

        return [
            'text' => $text,
            'entities' => $entities,
        ];
    }

    /**
     * @param list<Game> $games
     * @return list<array{text: string, entities: list<array<string, mixed>>}>
     */
    public function formatList(array $games, string $header): array
    {
        $chunks = [];
        $currentText = $header;
        $currentEntities = [];
        $currentLength = $this->utf16String->length($currentText);
        $hasGamesInCurrentChunk = false;

        foreach ($games as $game) {
            $formattedGame = $this->format($game);
            $separator = $hasGamesInCurrentChunk || $currentText !== '' ? "\n\n" : '';
            $separatorLength = $this->utf16String->length($separator);
            $gameLength = $this->utf16String->length($formattedGame['text']);
            $candidateLength = $currentLength + $separatorLength + $gameLength;

            if ($candidateLength > self::TELEGRAM_TEXT_LIMIT) {
                if ($hasGamesInCurrentChunk) {
                    $chunks[] = [
                        'text' => $currentText,
                        'entities' => $currentEntities,
                    ];

                    $currentText = $formattedGame['text'];
                    $currentEntities = $formattedGame['entities'];
                    $currentLength = $gameLength;
                    $hasGamesInCurrentChunk = true;
                    continue;
                }

                if ($currentText !== '') {
                    $chunks[] = [
                        'text' => $currentText,
                        'entities' => $currentEntities,
                    ];
                }

                $currentText = $formattedGame['text'];
                $currentEntities = $formattedGame['entities'];
                $currentLength = $gameLength;
                $hasGamesInCurrentChunk = true;
                continue;
            }

            $shift = $currentLength + $separatorLength;

            foreach ($formattedGame['entities'] as $entity) {
                $entity['offset'] += $shift;
                $currentEntities[] = $entity;
            }

            $currentText .= $separator . $formattedGame['text'];
            $currentLength = $candidateLength;
            $hasGamesInCurrentChunk = true;
        }

        if ($currentText !== '') {
            $chunks[] = [
                'text' => $currentText,
                'entities' => $currentEntities,
            ];
        }

        return $chunks;
    }
}
