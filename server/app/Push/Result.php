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
        /** Answer of the provider: HTTP status, ids (apns-id, apns-unique-id…), host. */
        public readonly array $response = [],
    ) {}

    /** Same result with the details of the answer of the provider. */
    public function withResponse(array $response): self
    {
        return new self($this->status, $this->error, $this->invalid, array_filter($response, fn ($value) => $value !== null && $value !== ''));
    }

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
