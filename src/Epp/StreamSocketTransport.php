<?php

namespace AfricoreDev\FredPhp\Epp;

use AfricoreDev\FredPhp\Configuration\FredConfig;
use AfricoreDev\FredPhp\Exceptions\EppConnectionException;

class StreamSocketTransport implements EppTransport
{
    /** @var resource|null */
    protected $socket = null;

    protected string $host;

    protected int $port;

    protected ?string $certificate;

    protected ?string $privateKey;

    protected ?string $passphrase;

    protected int $timeout;

    protected bool $verifyPeer;

    protected bool $verifyPeerName;

    protected bool $allowSelfSigned;

    public function __construct(
        string $host,
        int $port = 700,
        ?string $certificate = null,
        ?string $privateKey = null,
        ?string $passphrase = null,
        int $timeout = 30,
        bool $verifyPeer = false,
        bool $verifyPeerName = false,
        bool $allowSelfSigned = true,
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->certificate = $certificate;
        $this->privateKey = $privateKey;
        $this->passphrase = $passphrase;
        $this->timeout = $timeout;
        $this->verifyPeer = $verifyPeer;
        $this->verifyPeerName = $verifyPeerName;
        $this->allowSelfSigned = $allowSelfSigned;
    }

    public static function fromConfig(FredConfig $config): self
    {
        return new self(
            host: $config->host,
            port: $config->port,
            certificate: $config->certificate,
            privateKey: $config->privateKey,
            passphrase: $config->passphrase,
            timeout: $config->timeout,
            verifyPeer: $config->verifyPeer,
            verifyPeerName: $config->verifyPeerName,
            allowSelfSigned: $config->allowSelfSigned,
        );
    }

    public function connect(): void
    {
        $sslOptions = [
            'verify_peer' => $this->verifyPeer,
            'verify_peer_name' => $this->verifyPeerName,
            'allow_self_signed' => $this->allowSelfSigned,
        ];

        if ($this->certificate !== null) {
            $sslOptions['local_cert'] = $this->certificate;
        }

        if ($this->privateKey !== null) {
            $sslOptions['local_pk'] = $this->privateKey;
        }

        if ($this->passphrase !== null) {
            $sslOptions['passphrase'] = $this->passphrase;
        }

        $context = stream_context_create([
            'ssl' => $sslOptions,
        ]);

        $remoteSocket = "ssl://{$this->host}:{$this->port}";
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $remoteSocket,
            $errno,
            $errstr,
            (float) $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $socket) {
            throw new EppConnectionException(
                "Failed to connect to EPP server {$remoteSocket}: [{$errno}] {$errstr}"
            );
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, $this->timeout);
    }

    public function write(string $data): void
    {
        if (! $this->isConnected()) {
            throw new EppConnectionException('Transport socket is not connected.');
        }

        $totalLength = strlen($data);
        $written = 0;

        while ($written < $totalLength) {
            /** @var resource $socket */
            $socket = $this->socket;
            $chunkWritten = @fwrite($socket, substr($data, $written));

            if ($chunkWritten === false || $chunkWritten === 0) {
                throw new EppConnectionException('Failed to write data to EPP socket.');
            }

            $written += $chunkWritten;
        }
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        if (! $this->isConnected()) {
            throw new EppConnectionException('Transport socket is not connected.');
        }

        /** @var resource $socket */
        $socket = $this->socket;
        $data = @fread($socket, $length);

        if ($data === false) {
            throw new EppConnectionException('Failed to read data from EPP socket.');
        }

        $meta = stream_get_meta_data($socket);
        if ($meta['timed_out']) {
            throw new EppConnectionException('EPP socket read timed out.');
        }

        return $data;
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket) && ! feof($this->socket);
    }
}
