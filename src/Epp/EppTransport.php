<?php

namespace AfricoreDev\FredPhp\Epp;

interface EppTransport
{
    public function connect(): void;

    public function write(string $data): void;

    public function read(int $length): string;

    public function close(): void;

    public function isConnected(): bool;
}