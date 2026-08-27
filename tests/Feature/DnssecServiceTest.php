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

test('keyset create, info, update, and delete', function () {
    // 1. Create KeySet
    $xmlCreate = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <creData>
            <id>KEY-ALPHA</id>
            <crDate>2026-08-28T00:00:00Z</crDate>
          </creData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xmlCreate);

    $createResult = $this->fred->dnssec()->create('KEY-ALPHA', [
        ['flags' => 257, 'protocol' => 3, 'alg' => 13, 'pubKey' => 'cHVibGljS2V5'],
    ], ['CNT-TECH']);

    expect($createResult['created'])->toBeTrue()
        ->and($createResult['keysetId'])->toBe('KEY-ALPHA');

    // 2. Info KeySet
    $xmlInfo = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <infData>
            <id>KEY-ALPHA</id>
            <dnskey>
              <flags>257</flags>
              <protocol>3</protocol>
              <alg>13</alg>
              <pubKey>cHVibGljS2V5</pubKey>
            </dnskey>
            <tech>CNT-TECH</tech>
          </infData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xmlInfo);

    $info = $this->fred->dnssec()->info('KEY-ALPHA');
    expect($info['keysetId'])->toBe('KEY-ALPHA')
        ->and(count($info['dnskeys']))->toBe(1)
        ->and($info['dnskeys'][0]['pubKey'])->toBe('cHVibGljS2V5');

    // 3. Update KeySet
    $xmlSuccess = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
      </response>
    </epp>';
    $this->transport->queueResponse($xmlSuccess);

    $updateResult = $this->fred->dnssec()->update('KEY-ALPHA', addDnskeys: [
        ['flags' => 256, 'protocol' => 3, 'alg' => 13, 'pubKey' => 'YW5vdGhlcktleQ=='],
    ]);
    expect($updateResult['updated'])->toBeTrue();

    // 4. Delete KeySet
    $this->transport->queueResponse($xmlSuccess);
    $deleteResult = $this->fred->dnssec()->delete('KEY-ALPHA');
    expect($deleteResult['deleted'])->toBeTrue();
});
