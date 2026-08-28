<?php

namespace AfricoreDev\FredPhp;

use AfricoreDev\FredPhp\Configuration\FredConfig;
use AfricoreDev\FredPhp\Epp\EppClient;
use AfricoreDev\FredPhp\Services\AccountService;
use AfricoreDev\FredPhp\Services\ContactService;
use AfricoreDev\FredPhp\Services\DnssecService;
use AfricoreDev\FredPhp\Services\DomainService;
use AfricoreDev\FredPhp\Services\NameserverService;
use AfricoreDev\FredPhp\Services\PollService;

final class Fred
{
    private ?DomainService $domains = null;

    private ?ContactService $contacts = null;

    private ?NameserverService $nameservers = null;

    private ?DnssecService $dnssec = null;

    private ?AccountService $account = null;

    private ?PollService $poll = null;

    public function __construct(
        private EppClient $client,
    ) {
    }

    public static function create(FredConfig $config): self
    {
        $client = EppClient::fromConfig($config);

        return new self($client);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return self::create(FredConfig::fromArray($config));
    }

    public function connect(): string
    {
        return $this->client->connect();
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
        return $this->client->login(
            username: $username,
            password: $password,
            newPassword: $newPassword,
            objURIs: $objURIs,
            extURIs: $extURIs,
        );
    }

    public function send(string $xml): string
    {
        return $this->client->send($xml);
    }

    /**
     * @return array<string, mixed>
     */
    public function request(string $xml): array
    {
        return $this->client->request($xml);
    }

    public function disconnect(): void
    {
        $this->client->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }

    public function getGreeting(): ?string
    {
        return $this->client->getGreeting();
    }

    public function getClient(): EppClient
    {
        return $this->client;
    }

    public function getConfig(): ?FredConfig
    {
        return $this->client->getConfig();
    }

    // ==========================================
    // Domain Services
    // ==========================================

    public function domains(): DomainService
    {
        if ($this->domains === null) {
            $this->domains = new DomainService(
                client: $this->client,
                contactService: $this->contacts(),
                nameserverService: $this->nameservers(),
                dnssecService: $this->dnssec(),
            );
        }

        return $this->domains;
    }

    public function contacts(): ContactService
    {
        if ($this->contacts === null) {
            $this->contacts = new ContactService($this->client);
        }

        return $this->contacts;
    }

    public function nameservers(): NameserverService
    {
        if ($this->nameservers === null) {
            $this->nameservers = new NameserverService($this->client);
        }

        return $this->nameservers;
    }

    public function nssets(): NameserverService
    {
        return $this->nameservers();
    }

    public function dnssec(): DnssecService
    {
        if ($this->dnssec === null) {
            $this->dnssec = new DnssecService($this->client);
        }

        return $this->dnssec;
    }

    public function keysets(): DnssecService
    {
        return $this->dnssec();
    }

    public function account(): AccountService
    {
        if ($this->account === null) {
            $this->account = new AccountService($this->client);
        }

        return $this->account;
    }

    public function poll(): PollService
    {
        if ($this->poll === null) {
            $this->poll = new PollService($this->client);
        }

        return $this->poll;
    }
}