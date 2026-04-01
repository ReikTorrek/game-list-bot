<?php

namespace App\Tests\Unit\Telegram;

use App\Entity\Game;
use App\Enum\GameStatus;
use App\Telegram\Service\GameMessageFormatter;
use App\Telegram\Service\Utf16String;
use PHPUnit\Framework\TestCase;

final class GameMessageFormatterTest extends TestCase
{
    public function testFormatShiftsEntitiesAfterPrefix(): void
    {
        $game = (new Game())
            ->setUrl('https://example.com/game')
            ->setDescriptionText("Описание\nсо второй строкой")
            ->setDescriptionEntities([
                [
                    'type' => 'italic',
                    'offset' => 0,
                    'length' => 8,
                ],
            ])
            ->setStatus(GameStatus::NEW);

        $reflection = new \ReflectionProperty($game, 'id');
        $reflection->setValue($game, 7);

        $formatter = new GameMessageFormatter(new Utf16String());
        $formatted = $formatter->format($game);

        self::assertStringContainsString('Игра #7', $formatted['text']);
        self::assertStringContainsString("Описание:\nОписание\nсо второй строкой", $formatted['text']);
        self::assertCount(2, $formatted['entities']);
        self::assertGreaterThan(0, $formatted['entities'][0]['offset']);
        self::assertSame('italic', $formatted['entities'][0]['type']);
        self::assertSame('blockquote', $formatted['entities'][1]['type']);
        self::assertSame(0, $formatted['entities'][1]['offset']);
    }

    public function testFormatListSplitsOnlyBetweenGames(): void
    {
        $formatter = new GameMessageFormatter(new Utf16String());

        $firstGame = (new Game())
            ->setUrl('https://example.com/game-1')
            ->setDescriptionText(str_repeat('A', 3200))
            ->setDescriptionEntities(null)
            ->setStatus(GameStatus::NEW);

        $secondGame = (new Game())
            ->setUrl('https://example.com/game-2')
            ->setDescriptionText(str_repeat('B', 1200))
            ->setDescriptionEntities(null)
            ->setStatus(GameStatus::NEW);

        (new \ReflectionProperty($firstGame, 'id'))->setValue($firstGame, 1);
        (new \ReflectionProperty($secondGame, 'id'))->setValue($secondGame, 2);

        $chunks = $formatter->formatList(
            [$firstGame, $secondGame],
            'Найдено игр со статусом "новая": 2'
        );

        self::assertCount(2, $chunks);
        self::assertStringContainsString('Игра #1', $chunks[0]['text']);
        self::assertStringNotContainsString('Игра #2', $chunks[0]['text']);
        self::assertStringContainsString('Игра #2', $chunks[1]['text']);
        self::assertSame('blockquote', $chunks[0]['entities'][0]['type']);
        self::assertSame('blockquote', $chunks[1]['entities'][0]['type']);
    }
}
