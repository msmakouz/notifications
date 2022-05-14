<?php

declare(strict_types=1);

namespace Spiral\Notifications;

use Spiral\Core\Container;
use Spiral\Notifications\Config\NotificationsConfig;
use Spiral\SendIt\Config\MailerConfig;
use Symfony\Component\Mailer\Transport\RoundRobinTransport as MailerRoundRobinTransport;
use Symfony\Component\Mailer\Transport\TransportInterface as MailerTransportInterface;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Channel\EmailChannel;
use Symfony\Component\Notifier\Transport;

final class ChannelManager
{
    /** @var array<non-empty-string, ChannelInterface> */
    private array $channels = [];

    public function __construct(
        private Container $container,
        private NotificationsConfig $config,
        private MailerConfig $mailerConfig,
    ) {
    }

    public function getChannel(string $name): ?ChannelInterface
    {
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        $channel = $this->config->getChannel($name);
        $dsns = $channel['transport'];

        if ($channel['type'] === EmailChannel::class) {
            if (\count($dsns) === 1) {
                $transport = $this->resolveMailerTransport(new Transport\Dsn($dsns[0]));
            } else {
                $transport = new MailerRoundRobinTransport(
                    \array_map(function (string $dsn): MailerTransportInterface {
                        return $this->resolveMailerTransport(new Transport\Dsn($dsn));
                    }, $dsns)
                );
            }

            return $this->container->make($channel['type'], [
                'transport' => $transport,
                'from' => $this->mailerConfig->getFromAddress(),
            ]);
        }

        if (\count($dsns) === 1) {
            $transport = $this->resolveTransport($dsns[0]);
        } else {
            $transport = new Transport\RoundRobinTransport(
                \array_map(function (Transport\Dsn $dsn): Transport\TransportInterface {
                    return $this->resolveTransport($dsn);
                }, $dsns)
            );
        }

        return $this->container->make($channel['type'], [
            'transport' => $transport,
        ]);
    }

    private function resolveTransport(Transport\Dsn $dsn): Transport\TransportInterface
    {
        return Transport::fromDsn($dsn->getOriginalDsn());
    }

    private function resolveMailerTransport(Transport\Dsn $dsn): MailerTransportInterface
    {
        return \Symfony\Component\Mailer\Transport::fromDsn(
            $dsn->getOriginalDsn()
        );
    }
}
