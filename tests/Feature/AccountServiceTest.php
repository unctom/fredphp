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

test('getBalance returns registrar credit breakdown for zones', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <fred:resCreditInfo xmlns:fred="http://www.nic.cz/xml/epp/fred-1.5">
            <fred:zoneCredit>
              <fred:zone>TZ</fred:zone>
              <fred:credit>250000.00</fred:credit>
            </fred:zoneCredit>
            <fred:zoneCredit>
              <fred:zone>CO.TZ</fred:zone>
              <fred:credit>150000.00</fred:credit>
            </fred:zoneCredit>
          </fred:resCreditInfo>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $result = $this->fred->account()->getBalance();

    expect($result['balance'])->toBeArray()
        ->and($result['balance']['TZ']['available'])->toBe(250000.0)
        ->and($result['balance']['TZ']['currency'])->toBe('TZS')
        ->and($result['balance']['CO.TZ']['available'])->toBe(150000.0);
});
