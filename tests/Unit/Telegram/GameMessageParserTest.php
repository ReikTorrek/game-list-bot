<?php

namespace App\Tests\Unit\Telegram;

use App\Telegram\Service\GameMessageParser;
use App\Telegram\Service\Utf16String;
use PHPUnit\Framework\TestCase;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

final class GameMessageParserTest extends TestCase
{
    public function testParseAddGameKeepsDescriptionEntities(): void
    {
        $parser = new GameMessageParser(new Utf16String());

        $message = Message::fromArray([
            'message_id' => 1,
            'date' => time(),
            'chat' => ['id' => 1, 'type' => 'private'],
            'text' => "/add_game https://store.example/game Первая строка\nВторая строка",
            'entities' => [
                [
                    'type' => 'bot_command',
                    'offset' => 0,
                    'length' => 9,
                ],
                [
                    'type' => 'bold',
                    'offset' => 37,
                    'length' => 13,
                ],
            ],
        ]);

        $parsed = $parser->parseAddGames($message);

        self::assertNotNull($parsed);
        self::assertCount(1, $parsed);
        self::assertSame('https://store.example/game', $parsed[0]->url);
        self::assertSame("Первая строка\nВторая строка", $parsed[0]->descriptionText);
        self::assertSame(
            [
                [
                    'type' => 'bold',
                    'offset' => 0,
                    'length' => 13,
                ],
            ],
            $parsed[0]->descriptionEntities
        );
    }

    public function testParseAddGamesSupportsBulkBlocks(): void
    {
        $parser = new GameMessageParser(new Utf16String());

        $message = Message::fromArray([
            'message_id' => 1,
            'date' => time(),
            'chat' => ['id' => 1, 'type' => 'private'],
            'text' => "/add_game https://store.example/game-1\nОписание первой игры\n\nhttps://store.example/game-2\nОписание второй игры",
            'entities' => [
                [
                    'type' => 'bot_command',
                    'offset' => 0,
                    'length' => 9,
                ],
            ],
        ]);

        $parsed = $parser->parseAddGames($message);

        self::assertNotNull($parsed);
        self::assertCount(2, $parsed);
        self::assertSame('https://store.example/game-1', $parsed[0]->url);
        self::assertSame('Описание первой игры', $parsed[0]->descriptionText);
        self::assertSame('https://store.example/game-2', $parsed[1]->url);
        self::assertSame('Описание второй игры', $parsed[1]->descriptionText);
    }

    public function testParseGameIdArgument(): void
    {
        $parser = new GameMessageParser(new Utf16String());

        self::assertSame(42, $parser->parseGameIdArgument('/done_game 42', 'done_game'));
        self::assertSame(42, $parser->parseGameIdArgument('/done_game@anythingForImperrorGameListBot 42', 'done_game'));
        self::assertSame(42, $parser->parseGameIdArgument('/delete_game 42', 'delete_game'));
        self::assertSame(42, $parser->parseGameIdArgument('/delete_game@anythingForImperrorGameListBot 42', 'delete_game'));
        self::assertNull($parser->parseGameIdArgument('/done_game abc', 'done_game'));
        self::assertNull($parser->parseGameIdArgument('/done_game 12 extra', 'done_game'));
    }
}
