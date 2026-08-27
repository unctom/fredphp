<?php

use AfricoreDev\FredPhp\Exceptions\EppCommandException;
use AfricoreDev\FredPhp\Xml\EppXmlParser;

beforeEach(function () {
    $this->parser = new EppXmlParser();
});

test('parse parses XML response into array and extracts result code and trID', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000">
          <msg>Command completed successfully</msg>
        </result>
        <trID>
          <clTRID>REQ-12345</clTRID>
          <svTRID>SRV-67890</svTRID>
        </trID>
      </response>
    </epp>';

    $parsed = $this->parser->parse($xml);

    expect($this->parser->getResultCode($parsed))->toBe(1000)
        ->and($this->parser->getResultMessage($parsed))->toBe('Command completed successfully')
        ->and($this->parser->getClTRID($parsed))->toBe('REQ-12345')
        ->and($this->parser->getSvTRID($parsed))->toBe('SRV-67890')
        ->and($this->parser->isSuccess($parsed))->toBeTrue();
});

test('throwIfError throws EppCommandException when result code >= 2000', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="2302">
          <msg>Object exists</msg>
        </result>
      </response>
    </epp>';

    $parsed = $this->parser->parse($xml);

    expect($this->parser->isSuccess($parsed))->toBeFalse();

    expect(fn () => $this->parser->throwIfError($parsed))
        ->toThrow(EppCommandException::class, 'EPP Error [2302]: Object exists');
});

test('parse correctly extracts resData for domain check and info', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
      <response>
        <result code="1000">
          <msg>Command completed successfully</msg>
        </result>
        <resData>
          <domain:chkData xmlns:domain="http://www.nic.cz/xml/epp/domain-1.4">
            <domain:cd>
              <domain:name avail="1">available.tz</domain:name>
            </domain:cd>
            <domain:cd>
              <domain:name avail="0">taken.tz</domain:name>
              <domain:reason>Object exists</domain:reason>
            </domain:cd>
          </domain:chkData>
        </resData>
      </response>
    </epp>';

    $parsed = $this->parser->parse($xml);
    $resData = $this->parser->getResData($parsed);

    expect($resData)->toBeArray()
        ->and(isset($resData['chkData']['cd']))->toBeTrue();
});
