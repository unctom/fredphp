<?php

namespace AfricoreDev\FredPhp\Exceptions;

class EppCommandException extends EppException
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(
        string $message,
        private int $resultCode = 0,
        private ?string $eppMessage = null,
        private array $response = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $resultCode, $previous);
    }

    public function getResultCode(): int
    {
        return $this->resultCode;
    }

    public function getEppMessage(): ?string
    {
        return $this->eppMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}
