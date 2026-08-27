<?php

use AfricoreDev\FredPhp\Configuration\FredConfig;
use AfricoreDev\FredPhp\Epp\EppClient;
use AfricoreDev\FredPhp\Epp\EppFrameReader;
use AfricoreDev\FredPhp\Fred;
use AfricoreDev\FredPhp\Xml\EppXmlBuilder;
use AfricoreDev\FredPhp\Xml\EppXmlParser;
use Tests\Fakes\FakeEppTransport;

beforeEach(function () {
    $this->transport = new FakeEppTransport();
    $this->transport->connect();

    $this->client = new EppClient(
        $this->transport,
        new EppFrameReader($this->transport),
        new EppXmlBuilder(),
        new EppXmlParser(),
        new FredConfig('mtanzania.tznic.or.tz', 'USER', 'PASS')
    );

    $this->fred = new Fred($this->client);
});

test('poll request returns messages when available', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1301"><msg>Command completed successfully; ack to dequeue</msg></result>
        <msgQ count="5" id="12345">
          <qDate>2026-08-28T00:00:00Z</qDate>
          <msg>Domain example.tz was transferred.</msg>
        </msgQ>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $pollResult = $this->fred->poll()->request();

    expect($pollResult['hasMessages'])->toBeTrue()
        ->and($pollResult['msgId'])->toBe('12345')
        ->and($pollResult['count'])->toBe(5)
        ->and($pollResult['msg'])->toBe('Domain example.tz was transferred.');
});

test('poll request returns empty when no messages in queue', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1300"><msg>Command completed successfully; no messages</msg></result>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $pollResult = $this->fred->poll()->request();

    expect($pollResult['hasMessages'])->toBeFalse()
        ->and($pollResult['count'])->toBe(0);
});

test('poll ack acknowledges message', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <msgQ count="4" id="12345"/>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $ackResult = $this->fred->poll()->ack('12345');

    expect($ackResult['acknowledged'])->toBeTrue()
        ->and($ackResult['msgId'])->toBe('12345')
        ->and($ackResult['count'])->toBe(4);
});
