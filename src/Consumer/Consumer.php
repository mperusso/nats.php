<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use Basis\Nats\Client;
use Basis\Nats\Queue;
use Basis\Nats\Message\Payload;
use Basis\Nats\Message\Publish;
use Closure;
use Throwable;

class Consumer
{
    private  $exists = null;
    private $interrupt = false;
    private $delay = 1;
    private $expires = 0.1;
    private $batch = 1;
    private $iterations = PHP_INT_MAX;

    public $client;
    public $configuration;

    public function __construct(
        Client $client,
        Configuration $configuration
    ) {
        $this->client = $client;
        $this->configuration = $configuration;
    }

    public function create($ifNotExists = true): self
    {
        if ($this->shouldCreateConsumer($ifNotExists)) {
            if ($this->configuration->isEphemeral()) {
                $command = 'CONSUMER.CREATE.' . $this->getStream();
            } else {
                $command = 'CONSUMER.DURABLE.CREATE.' . $this->getStream() . '.' . $this->getName();
            }

            $result = $this->client->api($command, $this->configuration->toArray());

            if ($this->configuration->isEphemeral()) {
                $this->configuration->setName($result->name);
            }

            $this->exists = true;
        }

        return $this;
    }

    public function delete(): self
    {
        $this->client->api('CONSUMER.DELETE.' . $this->getStream() . '.' . $this->getName());
        $this->exists = false;

        return $this;
    }

    public function exists(): bool
    {
        if ($this->exists !== null) {
            return $this->exists;
        }
        $consumers = $this->client->getApi()->getStream($this->getStream())->getConsumerNames();
        return $this->exists = in_array($this->getName(), $consumers);
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getName(): string
    {
        return $this->getConfiguration()->getName();
    }

    public function getStream(): string
    {
        return $this->getConfiguration()->getStream();
    }

    public function getBatching(): int
    {
        return $this->batch;
    }

    public function getDelay(): float
    {
        return $this->delay;
    }

    public function getExpires(): float
    {
        return $this->expires;
    }

    public function getIterations(): int
    {
        return $this->iterations;
    }

    public function getQueue(): Queue
    {
        $queueSubject = 'handler.' . bin2hex(random_bytes(4));
        $queue = $this->client->subscribe($queueSubject);

        $args = [
            'batch' => $this->getBatching(),
        ];

        // convert to nanoseconds
        $expires = intval(1000000000 * $this->getExpires());
        if ($expires) {
            $args['expires'] = $expires;
        } else {
            $args['no_wait'] = true;
        }

        $launcher = new Publish([
            'payload' => Payload::parse($args),
            'replyTo' => $queue->subject,
            'subject' => '$JS.API.CONSUMER.MSG.NEXT.' . $this->getStream() . '.' . $this->getName(),
        ]);

        $queue->setLauncher($launcher);
        return $queue;
    }

    public function handle(Closure $messageHandler, Closure $emptyHandler = null, bool $ack = true): int
    {
        $queue = $this->create()->getQueue();
        $iterations = $this->getIterations();
        $processed = 0;

        while (!$this->interrupt && $iterations--) {
            $messages = $queue->fetchAll($this->getBatching());
            foreach ($messages as $message) {
                $processed++;
                $payload = $message->payload;
                if ($payload->isEmpty()) {
                    if ($emptyHandler && !in_array($payload->getHeader('KV-Operation'), ['DEL', 'PURGE'])) {
                        $emptyHandler($payload, $message->replyTo);
                    }
                    continue;
                }
                try {
                    $messageHandler($payload, $message->replyTo);
                    if ($ack) {
                        $message->ack();
                    }
                } catch (Throwable $e) {
                    if ($ack) {
                        $message->nack();
                    }
                    throw $e;
                }
                if ($this->interrupt) {
                    $this->interrupt = false;
                    break 2;
                }
            }
            if (!count($messages) && $emptyHandler) {
                $emptyHandler();
                if ($iterations) {
                    usleep((int) floor($this->getDelay() * 1000000));
                }
            }
        }

        $this->client->unsubscribe($queue);
        return $processed;
    }

    public function info()
    {
        return $this->client->api("CONSUMER.INFO." . $this->getStream() . '.' . $this->getName());
    }

    public function interrupt()
    {
        $this->interrupt = true;
    }

    public function setBatching(int $batch): self
    {
        $this->batch = $batch;

        return $this;
    }

    public function setDelay(float $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    public function setExpires(float $expires): self
    {
        $this->expires = $expires;

        return $this;
    }

    public function setIterations(int $iterations): self
    {
        $this->iterations = $iterations;

        return $this;
    }

    private function shouldCreateConsumer(bool $ifNotExists): bool
    {
        return ($this->configuration->isEphemeral() && $this->configuration->getName() === null)
            || !$this->exists();
    }
}
