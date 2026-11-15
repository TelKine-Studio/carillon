<?php

namespace App\Push;

/** Outcome of sending to one device. */
final class Result
{
    public function __construct(
        /** sent, failed (refused for this device) or errored (provider/network problem). */
        public readonly string $status,
        public readonly ?string $error = null,
        /** The token is dead: the device no longer receives anything. */
        public readonly bool $invalid = false,
    ) {}

    public static function sent(): self
    {
        return new self('sent');
    }

    public static function invalid(string $error): self
    {
        return new self('failed', $error, true);
    }

    public static function failed(string $error): self
    {
        return new self('failed', $error);
    }

    public static function errored(string $error): self
    {
        return new self('errored', $error);
    }
}
