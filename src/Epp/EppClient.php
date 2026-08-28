<?php

namespace AfricoreDev\FredPhp\Epp;

use AfricoreDev\FredPhp\Configuration\FredConfig;
use AfricoreDev\FredPhp\Exceptions\EppAuthenticationException;
use AfricoreDev\FredPhp\Exceptions\EppException;
use AfricoreDev\FredPhp\Xml\EppXmlBuilder;
use AfricoreDev\FredPhp\Xml\EppXmlParser;
use Throwable;

class EppClient
{
    protected ?string $lastGreeting = null;

    protected ?string $lastResponse = null;

    /** @var array<string, mixed>|null */
    protected ?array $lastParsedResponse = null;

    public function __construct(
        private EppTransport $transport,
        private EppFrameReader $frameReader,
        private EppXmlBuilder $xmlBuilder,
        private EppXmlParser $xmlParser,
        private ?FredConfig $config = null,
    ) {
    }

    public static function fromConfig(
        FredConfig $config,
        ?EppXmlBuilder $xmlBuilder = null,
        ?EppXmlParser $xmlParser = null,
    ): self {
        $transport = StreamSocketTransport::fromConfig($config);
        $frameReader = new EppFrameReader($transport);
        $builder = $xmlBuilder ?? new EppXmlBuilder();
        $parser = $xmlParser ?? new EppXmlParser();

        return new self(
            transport: $transport,
            frameReader: $frameReader,
            xmlBuilder: $builder,
            xmlParser: $parser,
            config: $config,
        );
    }

    public function connect(): string
    {
        $this->transport->connect();
        $this->lastGreeting = $this->frameReader->read();

        return $this->lastGreeting;
    }

    /**
     * @param array<string>|null $objURIs
     * @param array<string>|null $extURIs
     * @return array<string, mixed>
     */
    public function login(
        ?string $username = null,
        ?string $password = null,
        ?string $newPassword = null,
        ?array $objURIs = null,
        ?array $extURIs = null,
    ): array {
        $user = $username ?? $this->config?->username;
        $pass = $password ?? $this->config?->password;

        if ($user === null || $pass === null || $user === '' || $pass === '') {
            throw new EppAuthenticationException('Cannot login to EPP: username or password is not provided.');
        }

        // If greeting was captured and services not explicitly provided, extract announced services
        $activeObjURIs = $objURIs;
        $activeExtURIs = $extURIs;
        $version = '1.0';
        $lang = 'en';

        if ($activeObjURIs === null && $this->lastGreeting !== null && trim($this->lastGreeting) !== '') {
            try {
                $parsedGreeting = $this->xmlParser->parse($this->lastGreeting);
                $discovered = $this->xmlParser->getGreetingServices($parsedGreeting);
                if (! empty($discovered['objURIs'])) {
                    $activeObjURIs = $discovered['objURIs'];
                }
                if (! empty($discovered['extURIs'])) {
                    $activeExtURIs = $discovered['extURIs'];
                }
                if (! empty($discovered['version'])) {
                    $version = $discovered['version'];
                }
                if (! empty($discovered['lang'])) {
                    $lang = $discovered['lang'];
                }
            } catch (Throwable) {
                // Fallback to FRED defaults if greeting parsing fails
            }
        }

        $xml = $this->xmlBuilder->loginCommand(
            clID: $user,
            password: $pass,
            newPassword: $newPassword,
            version: $version,
            lang: $lang,
            objURIs: $activeObjURIs,
            extURIs: $activeExtURIs,
        );

        $response = $this->send($xml);
        $parsed = $this->xmlParser->parse($response);
        $this->lastParsedResponse = $parsed;

        $code = $this->xmlParser->getResultCode($parsed);

        if ($code === null) {
            throw new EppAuthenticationException('EPP Login response did not contain a result code.');
        }

        if ($code !== 1000 && $code !== 1001) {
            $msg = $this->xmlParser->getResultMessage($parsed) ?? 'Unknown authentication failure';
            throw new EppAuthenticationException("EPP login failed with code [{$code}]: {$msg}");
        }

        return $parsed;
    }

    public function send(string $xml): string
    {
        if (! $this->transport->isConnected()) {
            throw new EppException('EPP Transport is not connected.');
        }

        $frame = pack('N', strlen($xml) + 4).$xml;

        $this->transport->write($frame);
        $this->lastResponse = $this->frameReader->read();

        return $this->lastResponse;
    }

    /**
     * Sends an EPP XML request, parses the response, and throws an exception on EPP errors.
     *
     * @return array<string, mixed>
     */
    public function request(string $xml): array
    {
        $responseXml = $this->send($xml);
        $parsed = $this->xmlParser->parse($responseXml);
        $this->lastParsedResponse = $parsed;

        $this->xmlParser->throwIfError($parsed);

        return $parsed;
    }

    public function disconnect(): void
    {
        if (! $this->transport->isConnected()) {
            return;
        }

        try {
            $xml = $this->xmlBuilder->logoutCommand();
            $this->send($xml);
        } catch (Throwable) {
            // Ignore logout errors when disconnecting
        } finally {
            $this->transport->close();
        }
    }

    public function isConnected(): bool
    {
        return $this->transport->isConnected();
    }

    public function getGreeting(): ?string
    {
        return $this->lastGreeting;
    }

    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastParsedResponse(): ?array
    {
        return $this->lastParsedResponse;
    }

    public function getTransport(): EppTransport
    {
        return $this->transport;
    }

    public function getFrameReader(): EppFrameReader
    {
        return $this->frameReader;
    }

    public function getXmlBuilder(): EppXmlBuilder
    {
        return $this->xmlBuilder;
    }

    public function getXmlParser(): EppXmlParser
    {
        return $this->xmlParser;
    }

    public function getConfig(): ?FredConfig
    {
        return $this->config;
    }
}