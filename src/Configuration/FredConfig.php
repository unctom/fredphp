<?php

namespace AfricoreDev\FredPhp\Configuration;

final readonly class FredConfig
{
    public function __construct(
        public string $host,
        public string $username,
        public string $password,
        public ?string $certificate = null,
        public ?string $privateKey = null,
        public ?string $passphrase = null,
        public int $port = 700,
        public int $timeout = 30,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            host: (string) ($data['host'] ?? ''),
            username: (string) ($data['username'] ?? ''),
            password: (string) ($data['password'] ?? ''),
            certificate: $data['certificate'] ?? $data['cert'] ?? null,
            privateKey: $data['privateKey'] ?? $data['key'] ?? null,
            passphrase: $data['passphrase'] ?? null,
            port: (int) ($data['port'] ?? 700),
            timeout: (int) ($data['timeout'] ?? 30),
        );
    }
}