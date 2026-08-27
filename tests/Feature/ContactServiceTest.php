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

test('check returns availability for contacts', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <chkData>
            <cd>
              <id avail="1">CNT-AVAILABLE</id>
            </cd>
          </chkData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $result = $this->fred->contacts()->check('CNT-AVAILABLE');

    expect($result['contactId'])->toBe('CNT-AVAILABLE')
        ->and($result['available'])->toBeTrue();
});

test('create creates a new contact in FRED registry', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <creData>
            <id>CNT-12345</id>
            <crDate>2026-08-28T00:00:00Z</crDate>
          </creData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $result = $this->fred->contacts()->create([
        'id' => 'CNT-12345',
        'name' => 'Jane Smith',
        'email' => 'jane@example.tz',
        'street' => 'Posta St',
        'city' => 'Dar es Salaam',
        'pc' => '11101',
        'cc' => 'TZ',
    ]);

    expect($result['created'])->toBeTrue()
        ->and($result['contactId'])->toBe('CNT-12345');
});

test('info retrieves contact details', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000"><msg>Command completed successfully</msg></result>
        <resData>
          <infData>
            <id>CNT-12345</id>
            <email>jane@example.tz</email>
            <voice>+255.712345678</voice>
          </infData>
        </resData>
      </response>
    </epp>';
    $this->transport->queueResponse($xml);

    $info = $this->fred->contacts()->info('CNT-12345');

    expect($info['contactId'])->toBe('CNT-12345')
        ->and($info['email'])->toBe('jane@example.tz')
        ->and($info['voice'])->toBe('+255.712345678');
});
