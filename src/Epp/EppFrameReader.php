<?php

namespace AfricoreDev\FredPhp\Epp;

use AfricoreDev\FredPhp\Exceptions\EppException;

final class EppFrameReader
{
    public function __construct(
        private EppTransport $transport,
    ) {
    }

    public function read(): string
    {
        $header = $this->readExactly(4);

        $unpacked = unpack('Nlength', $header);

        $length = $unpacked['length'] ?? 0;

        if ($length < 4) {
            throw new EppException('Invalid EPP frame length.');
        }

        return $this->readExactly($length - 4);
    }

    private function readExactly(int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = $this->transport->read(
                $length - strlen($data)
            );

            if ($chunk === '') {
                throw new EppException(
                    'Unexpected end of EPP frame.'
                );
            }

            $data .= $chunk;
        }

        return $data;
    }
}
