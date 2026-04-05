<?php

namespace App\Telegram\Service;

use App\Telegram\Dto\ParsedGameMessage;
use SergiX44\Nutgram\Telegram\Types\Message\Message;
use SergiX44\Nutgram\Telegram\Types\Message\MessageEntity;

final readonly class GameMessageParser
{
    public function __construct(private Utf16String $utf16String)
    {
    }

    public function parseAddGame(Message $message): ?ParsedGameMessage
    {
        $text = $message->text;

        if (!is_string($text)) {
            return null;
        }

        if (!preg_match('/^\/add_game(?:@\w+)?\s+(?<body>[\s\S]+)$/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $body = $matches['body'][0];
        $bodyByteOffset = $matches['body'][1];

        if (trim($body) === '') {
            return null;
        }

        return $this->parseGameBlock(
            $text,
            $message->entities ?? [],
            $body,
            $bodyByteOffset,
        );
    }

    /**
     * @return list<ParsedGameMessage>|null
     */
    public function parseAddGames(Message $message): ?array
    {
        $text = $message->text;

        if (!is_string($text)) {
            return null;
        }

        if (!preg_match('/^\/add_games(?:@\w+)?\s+(?<body>[\s\S]+)$/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $body = $matches['body'][0];
        $bodyByteOffset = $matches['body'][1];

        if (trim($body) === '') {
            return null;
        }

        preg_match_all('/(?P<block>\S[\s\S]*?)(?:(?:\R[ \t]*){2,}|\z)/u', $body, $blocks, PREG_OFFSET_CAPTURE);

        $parsedGames = [];

        foreach ($blocks['block'] as [$blockText, $blockOffset]) {
            $parsedGame = $this->parseGameBlock(
                $text,
                $message->entities ?? [],
                $blockText,
                $bodyByteOffset + $blockOffset,
            );

            if ($parsedGame === null) {
                return null;
            }

            $parsedGames[] = $parsedGame;
        }

        return $parsedGames === [] ? null : $parsedGames;
    }

    public function parseGameIdArgument(?string $text, string $command): ?int
    {
        if (!is_string($text)) {
            return null;
        }

        $pattern = sprintf('/^\/%s(?:@\w+)?\s+(?<id>\d+)\s*$/u', preg_quote($command, '/'));

        if (!preg_match($pattern, $text, $matches)) {
            return null;
        }

        return (int) $matches['id'];
    }

    /**
     * @param list<MessageEntity> $entities
     * @return list<array<string, mixed>>
     */
    private function shiftEntities(array $entities, int $descriptionOffset, int $descriptionLength): array
    {
        $descriptionEnd = $descriptionOffset + $descriptionLength;
        $result = [];

        foreach ($entities as $entity) {
            $offset = $entity->offset ?? null;
            $length = $entity->length ?? null;

            if (!is_int($offset) || !is_int($length)) {
                continue;
            }

            $entityEnd = $offset + $length;

            if ($entityEnd <= $descriptionOffset || $offset >= $descriptionEnd) {
                continue;
            }

            if ($offset < $descriptionOffset || $entityEnd > $descriptionEnd) {
                continue;
            }

            $payload = $entity->toArray();
            $payload['offset'] = $offset - $descriptionOffset;
            $result[] = $payload;
        }

        return $result;
    }

    /**
     * @param list<MessageEntity> $entities
     */
    private function parseGameBlock(
        string $fullText,
        array $entities,
        string $blockText,
        int $blockByteOffsetInText,
    ): ?ParsedGameMessage {
        $blockText = trim($blockText);

        if ($blockText === '') {
            return null;
        }

        if (preg_match('/^(?<url>\S+)(?<separator>\s+)(?<description>[\s\S]+)$/u', $blockText, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $url = trim($matches['url'][0]);
        $descriptionText = $matches['description'][0];
        $descriptionStartInBlock = $matches['description'][1];

        if ($url === '' || trim($descriptionText) === '') {
            return null;
        }

        $descriptionByteOffsetInText = $blockByteOffsetInText + $descriptionStartInBlock;
        $descriptionOffset = $this->utf16String->length(substr($fullText, 0, $descriptionByteOffsetInText));
        $descriptionLength = $this->utf16String->length($descriptionText);

        return new ParsedGameMessage(
            url: $url,
            descriptionText: $descriptionText,
            descriptionEntities: $this->shiftEntities($entities, $descriptionOffset, $descriptionLength),
        );
    }
}
