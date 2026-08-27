<?php

use AfricoreDev\FredPhp\Configuration\FredConfig;
use AfricoreDev\FredPhp\Fred;
use AfricoreDev\FredPhp\Services\AccountService;
use AfricoreDev\FredPhp\Services\ContactService;
use AfricoreDev\FredPhp\Services\DnssecService;
use AfricoreDev\FredPhp\Services\DomainService;
use AfricoreDev\FredPhp\Services\NameserverService;
use AfricoreDev\FredPhp\Services\PollService;

test('Fred factory creates instances from config and provides service accessors', function () {
    $config = new FredConfig(
        host: 'mtanzania.tznic.or.tz',
        username: 'USER',
        password: 'PASSWORD',
    );

    $fred = Fred::create($config);

    expect($fred->domains())->toBeInstanceOf(DomainService::class)
        ->and($fred->contacts())->toBeInstanceOf(ContactService::class)
        ->and($fred->nameservers())->toBeInstanceOf(NameserverService::class)
        ->and($fred->nssets())->toBeInstanceOf(NameserverService::class)
        ->and($fred->dnssec())->toBeInstanceOf(DnssecService::class)
        ->and($fred->keysets())->toBeInstanceOf(DnssecService::class)
        ->and($fred->account())->toBeInstanceOf(AccountService::class)
        ->and($fred->poll())->toBeInstanceOf(PollService::class);
});

test('Fred can be created from array', function () {
    $fred = Fred::fromArray([
        'host' => 'mtanzania.tznic.or.tz',
        'username' => 'USER',
        'password' => 'PASSWORD',
    ]);

    expect($fred->getConfig()?->host)->toBe('mtanzania.tznic.or.tz')
        ->and($fred->domains())->toBeInstanceOf(DomainService::class);
});
