<?php

namespace App\Telegram\RunningMode;

use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;
use SergiX44\Nutgram\Telegram\Types\Common\Update;
use Throwable;

final class ResilientPolling extends Polling
{
    private const RETRY_DELAY_SECONDS = 2;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
        parent::__construct();
    }

    public function processUpdates(Nutgram $bot): void
    {
        $this->listenForSignals();

        $config = $bot->getConfig();
        $offset = 1;

        print("Listening...\n");

        while (self::$FOREVER) {
            try {
                $updates = $bot->getUpdates(
                    offset: $offset,
                    limit: $config->pollingLimit,
                    timeout: $config->pollingTimeout,
                    allowed_updates: $config->pollingAllowedUpdates,
                );
            } catch (Throwable $exception) {
                $message = sprintf('Polling request failed: %s', $exception->getMessage());

                $this->logger?->warning($message, [
                    'exception' => $exception,
                ]);

                fwrite(self::$STDERR ?? STDERR, $message.PHP_EOL);
                sleep(self::RETRY_DELAY_SECONDS);

                continue;
            }

            if ($offset === 1) {
                /** @var Update|false $last */
                $last = end($updates);
                if ($last instanceof Update) {
                    $offset = $last->update_id;
                }

                continue;
            }

            $offset += count($updates);

            $this->fire($bot, $updates);
        }
    }
}
