<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

use LogicException;

final class Factory
{
    private function __construct()
    {
    }

    public static function create(string $line): Prototype
    {
        $message = null;
        switch ($line) {
            case '+OK':
                $message = new Ok();
                break;
            case 'PING':
                $message = new Ping();
                break;
            case 'PONG':
                $message = new Pong();
                break;
        }

        if ($message == null) {
            if (!function_exists('str_contains')) {
                function str_contains(string $haystack, string $needle): bool {
                    return $needle === '' || strpos($haystack, $needle) !== false;
                }
            }

            if (!str_contains($line, ' ')) {
                throw new LogicException("Parse message failure: $line");
            }

            [$type, $body] = explode(' ', $line, 2);

            if ($type == '-ERR') {
                $message = trim($body, "'");
                throw new LogicException($message);
            }

            $message = null;
            switch ($type) {
                case 'CONNECT':
                    $message = Connect::create($body);
                    break;
                case 'INFO':
                    $message = Info::create($body);
                    break;
                case 'PUBLISH':
                    $message = Publish::create($body);
                    break;
                case 'SUBSCRIBE':
                    $message = Subscribe::create($body);
                    break;
                case 'UNSUBSCRIBE':
                    $message = Unsubscribe::create($body);
                    break;
                case 'HMSG':
                case 'MSG':
                    $message = Msg::create($body);
                    break;
            }
        }

        return $message;
    }
}
