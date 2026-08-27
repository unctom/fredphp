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
        public bool $verifyPeer = false,
        public bool $verifyPeerName = false,
        public bool $allowSelfSigned = true,
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
            verifyPeer: (bool) ($data['verifyPeer'] ?? $data['verify_peer'] ?? false),
            verifyPeerName: (bool) ($data['verifyPeerName'] ?? $data['verify_peer_name'] ?? false),
            allowSelfSigned: (bool) ($data['allowSelfSigned'] ?? $data['allow_self_signed'] ?? true),
        );
    }
}