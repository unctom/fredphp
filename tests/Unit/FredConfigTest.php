<?php

use AfricoreDev\FredPhp\Configuration\FredConfig;

test('FredConfig can be instantiated with default values', function () {
    $config = new FredConfig(
        host: 'mtanzania.tznic.or.tz',
        username: 'REG-USER',
        password: 'SECRET_PASSWORD'
    );

    expect($config->host)->toBe('mtanzania.tznic.or.tz')
        ->and($config->username)->toBe('REG-USER')
        ->and($config->password)->toBe('SECRET_PASSWORD')
        ->and($config->port)->toBe(700)
        ->and($config->timeout)->toBe(30);
});

test('FredConfig can be instantiated from array', function () {
    $config = FredConfig::fromArray([
        'host' => 'epp.example.tz',
        'username' => 'TEST_USER',
        'password' => 'TEST_PASS',
        'cert' => '/path/to/cert.pem',
        'key' => '/path/to/key.pem',
        'passphrase' => 'KEY_PASS',
        'port' => 7000,
        'timeout' => 60,
    ]);

    expect($config->host)->toBe('epp.example.tz')
        ->and($config->username)->toBe('TEST_USER')
        ->and($config->password)->toBe('TEST_PASS')
        ->and($config->certificate)->toBe('/path/to/cert.pem')
        ->and($config->privateKey)->toBe('/path/to/key.pem')
        ->and($config->passphrase)->toBe('KEY_PASS')
        ->and($config->port)->toBe(7000)
        ->and($config->timeout)->toBe(60);
});
