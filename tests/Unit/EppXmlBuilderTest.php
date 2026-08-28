<?php

use AfricoreDev\FredPhp\Xml\EppXmlBuilder;

beforeEach(function () {
    $this->builder = new EppXmlBuilder();
});

test('loginCommand generates correct XML structure with FRED namespaces', function () {
    $xml = $this->builder->loginCommand('REG-USER', 'SECRET_PASS', clTRID: 'LGN-1234');

    expect($xml)->toContain('<clID>REG-USER</clID>')
        ->and($xml)->toContain('<pw>SECRET_PASS</pw>')
        ->and($xml)->toContain('urn:ietf:params:xml:ns:epp-1.0')
        ->and($xml)->toContain('http://www.nic.cz/xml/epp/domain-1.4')
        ->and($xml)->toContain('http://www.nic.cz/xml/epp/contact-1.6')
        ->and($xml)->toContain('http://www.nic.cz/xml/epp/enumval-1.2')
        ->and($xml)->not->toContain('http://www.nic.cz/xml/epp/fred-1.5')
        ->and($xml)->toContain('<clTRID>LGN-1234</clTRID>');
});

test('logoutCommand generates correct XML structure', function () {
    $xml = $this->builder->logoutCommand('LGO-1234');

    expect($xml)->toContain('<logout')
        ->and($xml)->toContain('<clTRID>LGO-1234</clTRID>');
});

test('domainCheckCommand generates check XML for single or multiple domains', function () {
    $xmlSingle = $this->builder->domainCheckCommand('example.tz', 'CHK-1');
    expect($xmlSingle)->toContain('<domain:check')
        ->and($xmlSingle)->toContain('<domain:name>example.tz</domain:name>')
        ->and($xmlSingle)->toContain('<clTRID>CHK-1</clTRID>');

    $xmlMulti = $this->builder->domainCheckCommand(['foo.tz', 'bar.tz']);
    expect($xmlMulti)->toContain('<domain:name>foo.tz</domain:name>')
        ->and($xmlMulti)->toContain('<domain:name>bar.tz</domain:name>');
});

test('domainCreateCommand generates create XML with registrant and nsset', function () {
    $xml = $this->builder->domainCreateCommand([
        'name' => 'testdomain.tz',
        'period' => 2,
        'periodUnit' => 'y',
        'registrant' => 'REG-123',
        'admin' => ['ADM-1', 'ADM-2'],
        'nsset' => 'NSS-999',
        'keyset' => 'KEY-888',
        'authInfo' => 'SECRET_AUTH',
    ], 'CRE-1');

    expect($xml)->toContain('<domain:name>testdomain.tz</domain:name>')
        ->and($xml)->toContain('<domain:period unit="y">2</domain:period>')
        ->and($xml)->toContain('<domain:registrant>REG-123</domain:registrant>')
        ->and($xml)->toContain('<domain:admin>ADM-1</domain:admin>')
        ->and($xml)->toContain('<domain:admin>ADM-2</domain:admin>')
        ->and($xml)->toContain('<domain:nsset>NSS-999</domain:nsset>')
        ->and($xml)->toContain('<domain:keyset>KEY-888</domain:keyset>')
        ->and($xml)->toContain('<domain:authInfo>SECRET_AUTH</domain:authInfo>');
});

test('domainRenewCommand generates renew XML', function () {
    $xml = $this->builder->domainRenewCommand('example.tz', '2027-01-15', 1, 'y', 'RNW-1');

    expect($xml)->toContain('<domain:name>example.tz</domain:name>')
        ->and($xml)->toContain('<domain:curExpDate>2027-01-15</domain:curExpDate>')
        ->and($xml)->toContain('<domain:period unit="y">1</domain:period>');
});

test('domainTransferCommand generates transfer XML', function () {
    $xml = $this->builder->domainTransferCommand('example.tz', 'TRANSFER_SECRET', 'request', 'TRN-1');

    expect($xml)->toContain('<transfer')
        ->and($xml)->toContain('op="request"')
        ->and($xml)->toContain('<domain:name>example.tz</domain:name>')
        ->and($xml)->toContain('<domain:authInfo>TRANSFER_SECRET</domain:authInfo>');
});

test('contactCreateCommand generates contact XML with disclose flags', function () {
    $xml = $this->builder->contactCreateCommand([
        'id' => 'CNT-001',
        'name' => 'John Doe',
        'org' => 'Example Ltd',
        'street' => ['123 Main St', 'Suite 4B'],
        'city' => 'Dar es Salaam',
        'sp' => 'Ilala',
        'pc' => '11101',
        'cc' => 'TZ',
        'voice' => '+255.712345678',
        'email' => 'john@example.tz',
        'disclose' => ['email', 'voice'],
    ], 'CRE-CNT');

    expect($xml)->toContain('<contact:id>CNT-001</contact:id>')
        ->and($xml)->toContain('<contact:name>John Doe</contact:name>')
        ->and($xml)->toContain('<contact:org>Example Ltd</contact:org>')
        ->and($xml)->toContain('<contact:street>123 Main St</contact:street>')
        ->and($xml)->toContain('<contact:street>Suite 4B</contact:street>')
        ->and($xml)->toContain('<contact:city>Dar es Salaam</contact:city>')
        ->and($xml)->toContain('<contact:pc>11101</contact:pc>')
        ->and($xml)->toContain('<contact:cc>TZ</contact:cc>')
        ->and($xml)->toContain('<contact:voice>+255.712345678</contact:voice>')
        ->and($xml)->toContain('<contact:email>john@example.tz</contact:email>')
        ->and($xml)->toContain('<contact:disclose flag="1">')
        ->and($xml)->toContain('<contact:email')
        ->and($xml)->toContain('<contact:voice');
});

test('nssetCreateCommand generates NSSet XML with glue addresses', function () {
    $xml = $this->builder->nssetCreateCommand(
        'NSS-TEST',
        [
            ['name' => 'ns1.example.tz', 'addresses' => ['192.0.2.1', '2001:db8::1']],
            'ns2.example.tz',
        ],
        ['CNT-TECH-1'],
        'NSSET_AUTH',
        'CRE-NSS'
    );

    expect($xml)->toContain('<nsset:id>NSS-TEST</nsset:id>')
        ->and($xml)->toContain('<nsset:name>ns1.example.tz</nsset:name>')
        ->and($xml)->toContain('<nsset:addr>192.0.2.1</nsset:addr>')
        ->and($xml)->toContain('<nsset:addr>2001:db8::1</nsset:addr>')
        ->and($xml)->toContain('<nsset:name>ns2.example.tz</nsset:name>')
        ->and($xml)->toContain('<nsset:tech>CNT-TECH-1</nsset:tech>')
        ->and($xml)->toContain('<nsset:authInfo>NSSET_AUTH</nsset:authInfo>');
});

test('keysetCreateCommand generates KeySet XML with DNSKEY records', function () {
    $xml = $this->builder->keysetCreateCommand(
        'KEY-TEST',
        [
            [
                'flags' => 257,
                'protocol' => 3,
                'alg' => 13,
                'pubKey' => 'cHVibGljS2V5RGF0YQ==',
            ],
        ],
        ['CNT-TECH-1'],
        null,
        'CRE-KEY'
    );

    expect($xml)->toContain('<keyset:id>KEY-TEST</keyset:id>')
        ->and($xml)->toContain('<keyset:flags>257</keyset:flags>')
        ->and($xml)->toContain('<keyset:protocol>3</keyset:protocol>')
        ->and($xml)->toContain('<keyset:alg>13</keyset:alg>')
        ->and($xml)->toContain('<keyset:pubKey>cHVibGljS2V5RGF0YQ==</keyset:pubKey>')
        ->and($xml)->toContain('<keyset:tech>CNT-TECH-1</keyset:tech>');
});

test('FRED extension creditInfo and sendAuthInfo commands generate proper XML', function () {
    $creditXml = $this->builder->creditInfoCommand('CRD-1');
    expect($creditXml)->toContain('fred:creditInfo')
        ->and($creditXml)->toContain('fred:clTRID');

    $authXml = $this->builder->sendAuthInfoCommand('example.tz', 'AUT-1');
    expect($authXml)->toContain('fred:sendAuthInfo')
        ->and($authXml)->toContain('<domain:name>example.tz</domain:name>')
        ->and($authXml)->toContain('fred:clTRID');
});
