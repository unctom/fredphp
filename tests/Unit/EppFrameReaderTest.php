<?php

use AfricoreDev\FredPhp\Epp\EppFrameReader;
use AfricoreDev\FredPhp\Exceptions\EppException;
use Tests\Fakes\FakeEppTransport;

test('EppFrameReader unmarshals 4-byte header and reads full frame payload', function () {
    $transport = new FakeEppTransport();
    $transport->connect();

    $xmlPayload = '<epp><hello/></epp>';
    $transport->queueResponse($xmlPayload);

    $reader = new EppFrameReader($transport);
    $readXml = $reader->read();

    expect($readXml)->toBe($xmlPayload);
});

test('EppFrameReader throws exception on invalid frame length', function () {
    $transport = new FakeEppTransport();
    $transport->connect();

    // Frame length 2 is invalid (< 4)
    $header = pack('N', 2);
    $transport->queueRaw($header);

    $reader = new EppFrameReader($transport);

    expect(fn () => $reader->read())->toThrow(EppException::class, 'Invalid EPP frame length.');
});
