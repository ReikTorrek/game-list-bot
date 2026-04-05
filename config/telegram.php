<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Service\GameBotHandler;
use SergiX44\Nutgram\Nutgram;

$handler = $bot->getConfig()->container?->get(GameBotHandler::class);

if (!$handler instanceof GameBotHandler) {
    throw new RuntimeException('GameBotHandler service is not available in the Symfony container.');
}

$bot->onCommand('start', [$handler, 'help'])
    ->description('Показать список команд');

$bot->onCommand('help', [$handler, 'help'])
    ->description('Показать список команд');

$bot->onCommand('add_game', [$handler, 'addGame']);

$bot->onText('/add_game{suffix}', [$handler, 'addGame'])
    ->where('suffix', '(?:@\\w+)(?:\\s+.+)?|(?:\\s+.+)');

$bot->onCommand('add_games', [$handler, 'addGames']);

$bot->onText('/add_games{suffix}', [$handler, 'addGames'])
    ->where('suffix', '(?:@\\w+)(?:\\s+.+)?|(?:\\s+.+)');

$bot->onCommand('get_games', [$handler, 'getGames'])
    ->description('Показать новые игры');

$bot->onCommand('get_done_games', [$handler, 'getDoneGames'])
    ->description('Показать пройденные игры');

$bot->onCommand('get_dropped_games', [$handler, 'getDroppedGames'])
    ->description('Показать брошенные игры');

$bot->onCommand('find_game', [$handler, 'findGame']);

$bot->onText('/find_game{suffix}', [$handler, 'findGame'])
    ->where('suffix', '(?:@\\w+)(?:\\s+.+)?|(?:\\s+.+)');

$bot->onCommand('done_game', [$handler, 'doneGame']);

$bot->onText('/done_game{suffix}', [$handler, 'doneGame'])
    ->where('suffix', '(?:@\\w+)(?:\\s+.+)?|(?:\\s+.+)');

$bot->onCommand('drop_game', [$handler, 'dropGame']);

$bot->onText('/drop_game{suffix}', [$handler, 'dropGame'])
    ->where('suffix', '(?:@\\w+)(?:\\s+.+)?|(?:\\s+.+)');

$bot->onCommand('delete_game', [$handler, 'deleteGame']);

$bot->onText('/delete_game{suffix}', [$handler, 'deleteGame'])
    ->where('suffix', '(?:@\\w+)(?:\\s+.+)?|(?:\\s+.+)');
