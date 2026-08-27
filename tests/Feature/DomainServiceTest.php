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

    $this->frameReader = new EppFrameReader($this->transport);
    $this->builder = new EppXmlBuilder();
    $this->parser = new EppXmlParser();
    $this->config = new FredConfig('mtanzania.tznic.or.tz', 'USER', 'PASS');

    $this->client = new EppClient(
        $this->transport,
        $this->frameReader,
        $this->builder,
        $this->parser,
        $this->config
    );

    $this->fred = new Fred($this->client);
});

test('check returns availability status for single domain', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <chkData>
            <cd>
              <name avail="1">available.tz</name>
            </cd>
          </chkData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);
    $this->transport->queueResponse($xml);

    $result = $this->fred->domains()->check('available.tz');

    expect($result)->toBe([
        'domainName' => 'available.tz',
        'available' => true,
        'reason' => null,
    ])->and($this->fred->domains()->isAvailable('available.tz'))->toBeTrue();
});

test('info parses domain details correctly', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <infData>
            <name>example.tz</name>
            <status s="ok"/>
            <registrant>REG-001</registrant>
            <admin>ADM-001</admin>
            <nsset>NSS-001</nsset>
            <keyset>KEY-001</keyset>
            <exDate>2028-08-28T00:00:00Z</exDate>
            <crDate>2026-08-28T00:00:00Z</crDate>
          </infData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $info = $this->fred->domains()->info('example.tz');

    expect($info['domainName'])->toBe('example.tz')
        ->and($info['registrant'])->toBe('REG-001')
        ->and($info['admin'])->toBe('ADM-001')
        ->and($info['nsset'])->toBe('NSS-001')
        ->and($info['keyset'])->toBe('KEY-001')
        ->and($info['expires'])->toBe('2028-08-28T00:00:00Z');
});

test('register creates domain with contacts and nsset', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <creData>
            <name>newdomain.tz</name>
            <crDate>2026-08-28T00:00:00Z</crDate>
            <exDate>2027-08-28T00:00:00Z</exDate>
          </creData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $result = $this->fred->domains()->register([
        'domainName' => 'newdomain.tz',
        'registrant' => 'REG-123',
        'admin' => 'ADM-123',
        'nsset' => 'NSS-123',
        'period' => 1,
    ]);

    expect($result['registered'])->toBeTrue()
        ->and($result['domainName'])->toBe('newdomain.tz')
        ->and($result['expirationDate'])->toBe('2027-08-28T00:00:00Z');
});

test('renew renews domain for specified period', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <renData>
            <name>example.tz</name>
            <exDate>2028-08-28T00:00:00Z</exDate>
          </renData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $result = $this->fred->domains()->renew('example.tz', '2027-08-28', 1);

    expect($result['renewed'])->toBeTrue()
        ->and($result['domainName'])->toBe('example.tz')
        ->and($result['expirationDate'])->toBe('2028-08-28T00:00:00Z');
});

test('lock and unlock domain updates statuses', function () {
    $xmlSuccess = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
      </response>
    </epp>';
    $this->transport->queueResponse($xmlSuccess);

    $lockResult = $this->fred->domains()->lock('example.tz');
    expect($lockResult['updated'])->toBeTrue()
        ->and($lockResult['lockStatus'])->toBeTrue();

    $this->transport->queueResponse($xmlSuccess);
    $unlockResult = $this->fred->domains()->unlock('example.tz');
    expect($unlockResult['updated'])->toBeTrue()
        ->and($unlockResult['lockStatus'])->toBeFalse();
});

test('requestAuthCode triggers fred:sendAuthInfo extension', function () {
    $xmlSuccess = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
      </response>
    </epp>';
    $this->transport->queueResponse($xmlSuccess);

    $result = $this->fred->domains()->requestAuthCode('example.tz');

    expect($result['sent'])->toBeTrue()
        ->and($result['domainName'])->toBe('example.tz');
});
