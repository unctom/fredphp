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

test('nsset create, info, update, and delete', function () {
    // 1. Create NSSet
    $xmlCreate = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <creData>
            <id>NSS-ALPHA</id>
            <crDate>2026-08-28T00:00:00Z</crDate>
          </creData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xmlCreate);

    $createResult = $this->fred->nameservers()->create('NSS-ALPHA', ['ns1.example.tz', 'ns2.example.tz'], ['CNT-TECH']);
    expect($createResult['created'])->toBeTrue()
        ->and($createResult['nssetId'])->toBe('NSS-ALPHA');

    // 2. Info NSSet
    $xmlInfo = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <infData>
            <id>NSS-ALPHA</id>
            <ns><name>ns1.example.tz</name></ns>
            <ns><name>ns2.example.tz</name></ns>
            <tech>CNT-TECH</tech>
          </infData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xmlInfo);

    $info = $this->fred->nameservers()->info('NSS-ALPHA');
    expect($info['nssetId'])->toBe('NSS-ALPHA')
        ->and(count($info['nameservers']))->toBe(2)
        ->and($info['technicalContacts'])->toBe(['CNT-TECH']);

    // 3. Update NSSet
    $xmlUpdate = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
      </response>
    </epp>';
    $this->transport->queueResponse($xmlUpdate);

    $updateResult = $this->fred->nameservers()->update('NSS-ALPHA', addNameservers: ['ns3.example.tz']);
    expect($updateResult['updated'])->toBeTrue();

    // 4. Delete NSSet
    $this->transport->queueResponse($xmlUpdate);
    $deleteResult = $this->fred->nameservers()->delete('NSS-ALPHA');
    expect($deleteResult['deleted'])->toBeTrue();
});
