<?php

namespace App\Telegram\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Telegram\RunningMode\ResilientPolling;
use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class NutgramFactory
{
    public function __construct(
        private ?string $proxyUrl = null,
    ) {
    }

    public function createNutgram(
        array $config,
        ContainerInterface $container,
        RequestStack $requestStack,
        KernelInterface $kernel,
        ?CacheItemPoolInterface $nutgramCache,
        ?LoggerInterface $nutgramLogger,
        ?LoggerInterface $nutgramConsoleLogger,
    ): Nutgram {
        $isCli = \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';

        $clientOptions = $config['config']['clientOptions'] ?? [];

        if (is_string($this->proxyUrl) && $this->proxyUrl !== '') {
            $clientOptions['proxy'] = $this->proxyUrl;
        }

        $configuration = new Configuration(
            apiUrl: $config['config']['apiUrl'],
            botId: $config['config']['botId'],
            botName: $config['config']['botName'],
            testEnv: $config['config']['testEnv'],
            clientTimeout: $config['config']['clientTimeout'],
            clientOptions: $clientOptions,
            container: $container,
            cache: new Psr16Cache($nutgramCache),
            logger: $isCli ? $nutgramConsoleLogger : $nutgramLogger,
            localPathTransformer: $config['config']['localPathTransformer'],
            pollingTimeout: $config['config']['pollingTimeout'],
            pollingLimit: $config['config']['pollingLimit'],
            enableHttp2: $config['config']['enableHttp2'],
        );

        if ($kernel->getEnvironment() === 'test') {
            return Nutgram::fake(config: $configuration);
        }

        $bot = new Nutgram($config['token'], $configuration);

        if ($isCli) {
            $bot->setRunningMode(ResilientPolling::class);
        } else {
            $webhook = Webhook::class;

            if ($config['webhook_secret']) {
                $webhook = new Webhook(secretToken: $config['webhook_secret']);
                $webhook->setSafeMode(true);
            }

            $bot->setRunningMode($webhook);
        }

        return $bot;
    }
}
