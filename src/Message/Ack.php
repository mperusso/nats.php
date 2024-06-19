<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Ack extends Prototype
{
    public $subject;
    public $command = '+ACK';

    public $payload = null;

    public function render(): string
    {
        $payload = ($this->payload ?: Payload::parse(''))->render();
        return "PUB $this->subject $this->command $payload";
    }
}
