<?php

namespace Tests\Fakes;

use AfricoreDev\FredPhp\Epp\EppTransport;
use AfricoreDev\FredPhp\Exceptions\EppConnectionException;

class FakeEppTransport implements EppTransport
{
    protected bool $connected = false;

    /** @var array<string> */
    protected array $written = [];

    /** @var array<string> */
    protected array $queuedResponses = [];

    protected string $readBuffer = '';

    public function queueResponse(string $xml): self
    {
        $frame = pack('N', strlen($xml) + 4).$xml;
        $this->queuedResponses[] = $frame;

        return $this;
    }

    public function queueRaw(string $bytes): self
    {
        $this->queuedResponses[] = $bytes;

        return $this;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function write(string $data): void
    {
        if (! $this->connected) {
            throw new EppConnectionException('Fake transport is not connected.');
        }

        $this->written[] = $data;
    }

    public function read(int $length): string
    {
        if (! $this->connected) {
            throw new EppConnectionException('Fake transport is not connected.');
        }

        while (strlen($this->readBuffer) < $length && ! empty($this->queuedResponses)) {
            $this->readBuffer .= array_shift($this->queuedResponses);
        }

        if (strlen($this->readBuffer) < $length) {
            $chunk = $this->readBuffer;
            $this->readBuffer = '';

            return $chunk;
        }

        $chunk = substr($this->readBuffer, 0, $length);
        $this->readBuffer = substr($this->readBuffer, $length);

        return $chunk;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * @return array<string>
     */
    public function getWritten(): array
    {
        return $this->written;
    }

    public function getLastWritten(): ?string
    {
        return ! empty($this->written) ? $this->written[count($this->written) - 1] : null;
    }
}
