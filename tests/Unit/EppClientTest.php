<?php

use AfricoreDev\FredPhp\Configuration\FredConfig;
use AfricoreDev\FredPhp\Epp\EppClient;
use AfricoreDev\FredPhp\Epp\EppFrameReader;
use AfricoreDev\FredPhp\Exceptions\EppAuthenticationException;
use AfricoreDev\FredPhp\Xml\EppXmlBuilder;
use AfricoreDev\FredPhp\Xml\EppXmlParser;
use Tests\Fakes\FakeEppTransport;

beforeEach(function () {
    $this->transport = new FakeEppTransport();
    $this->frameReader = new EppFrameReader($this->transport);
    $this->builder = new EppXmlBuilder();
    $this->parser = new EppXmlParser();
    $this->config = new FredConfig(
        host: 'mtanzania.tznic.or.tz',
        username: 'TEST_USER',
        password: 'TEST_PASSWORD',
    );

    $this->client = new EppClient(
        transport: $this->transport,
        frameReader: $this->frameReader,
        xmlBuilder: $this->builder,
        xmlParser: $this->parser,
        config: $this->config,
    );
});

test('connect connects transport and reads greeting frame', function () {
    $greetingXml = '<?xml version="1.0" encoding="UTF-8"?><epp><greeting><svID>tzNIC</svID></greeting></epp>';
    $this->transport->queueResponse($greetingXml);

    $greeting = $this->client->connect();

    expect($this->client->isConnected())->toBeTrue()
        ->and($greeting)->toBe($greetingXml)
        ->and($this->client->getGreeting())->toBe($greetingXml);
});

test('login succeeds when server returns 1000', function () {
    $this->transport->connect();

    $loginResponseXml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000">
          <msg>Command completed successfully</msg>
        </result>
      </response>
    </epp>';

    $this->transport->queueResponse($loginResponseXml);

    $response = $this->client->login();

    expect($response)->toBeArray()
        ->and($this->parser->getResultCode($response))->toBe(1000);
});

test('login throws EppAuthenticationException when server returns error code', function () {
    $this->transport->connect();

    $loginFailedXml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="2200">
          <msg>Authentication error</msg>
        </result>
      </response>
    </epp>';

    $this->transport->queueResponse($loginFailedXml);

    expect(fn () => $this->client->login())
        ->toThrow(EppAuthenticationException::class, 'EPP login failed with code [2200]: Authentication error');
});

test('disconnect sends logout and closes transport', function () {
    $this->transport->connect();

    $logoutResponseXml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1500">
          <msg>Command completed successfully; ending session</msg>
        </result>
      </response>
    </epp>';

    $this->transport->queueResponse($logoutResponseXml);

    $this->client->disconnect();

    expect($this->client->isConnected())->toBeFalse()
        ->and(count($this->transport->getWritten()))->toBeGreaterThan(0);
});
