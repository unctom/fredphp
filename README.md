# FRED PHP SDK (`africore-dev/fred-php`)

A pure PHP SDK for managing `.tz` domain names, contacts, nameservers (NSSets), DNSSEC (KeySets), and registrar accounts on registries running the **FRED** (*Free Registry ENUM Domain*) registry platform via EPP (*Extensible Provisioning Protocol*).

---

## Requirements

* **PHP 8.3+**
* Extensions: `ext-openssl`, `ext-dom`, `ext-libxml`

---

## Installation

```bash
composer require africore-dev/fred-php
```

---

## Quick Start

```php
use AfricoreDev\FredPhp\Fred;
use AfricoreDev\FredPhp\Configuration\FredConfig;

$config = new FredConfig(
    host: 'mtanzania.tznic.or.tz',
    username: 'YOUR_REGISTRAR_HANDLE',
    password: 'YOUR_EPP_PASSWORD',
    certificate: '/path/to/epp-client-cert.pem',
    privateKey: '/path/to/epp-client-key.pem',
    port: 700,
    verifyPeer: false,
);

// Initialize SDK
$fred = Fred::create($config);

// Connect and login to the registry
$greeting = $fred->connect();
$fred->login();

// Perform operations...
$availability = $fred->domains()->check('example.tz');

// Disconnect when done
$fred->disconnect();
```

Or instantiate from an associative array:

```php
$fred = Fred::fromArray([
    'host' => 'mtanzania.tznic.or.tz',
    'username' => 'YOUR_HANDLE',
    'password' => 'SECRET_PASS',
    'cert' => '/path/to/cert.pem',
    'key' => '/path/to/key.pem',
]);
```

---

## Features & Usage

### 1. Domain Management (`$fred->domains()`)

#### Check Domain Availability
```php
// Single domain check
$result = $fred->domains()->check('example.tz');
// ['domainName' => 'example.tz', 'available' => true, 'reason' => null]

$isAvailable = $fred->domains()->isAvailable('example.tz'); // bool

// Multiple domains check
$results = $fred->domains()->check(['domain1.tz', 'domain2.tz']);
```

#### Get Domain Details
```php
$info = $fred->domains()->info('example.tz');
/*
[
    'domainName' => 'example.tz',
    'status' => ['ok'],
    'registrant' => 'REG-12345',
    'admin' => ['ADM-12345'],
    'nsset' => 'NSS-12345',
    'nameservers' => ['ns1.example.tz', 'ns2.example.tz'],
    'keyset' => 'KEY-12345',
    'expires' => '2028-08-28T00:00:00Z',
    'crDate' => '2026-08-28T00:00:00Z',
]
*/
```

#### Register Domain
```php
$result = $fred->domains()->register([
    'domainName' => 'mynewdomain.tz',
    'period' => 1, // in years
    'registrant' => 'REG-12345', // Existing contact ID
    'admin' => ['ADM-12345'],
    'nameservers' => ['ns1.hosting.tz', 'ns2.hosting.tz'], // Auto-creates NSSet if not provided
    'authInfo' => 'DomainPassword123!',
]);
```

*Auto-provisioning contacts during registration:*
```php
$result = $fred->domains()->register([
    'domainName' => 'quicksetup.tz',
    'registrantInfo' => [
        'name' => 'John Doe',
        'email' => 'john@quicksetup.tz',
        'street' => '123 Main St',
        'city' => 'Dar es Salaam',
        'pc' => '11101',
        'cc' => 'TZ',
        'phone' => '+255.712345678',
    ],
    'nameservers' => ['ns1.quicksetup.tz', 'ns2.quicksetup.tz'],
]);
```

#### Renew Domain
```php
$result = $fred->domains()->renew('example.tz', period: 1);
```

#### Transfer Domain
```php
$result = $fred->domains()->transfer('example.tz', authInfo: 'TRANSFER_AUTH_CODE');
```

#### Lock / Unlock Domain
```php
$fred->domains()->lock('example.tz');   // Sets clientTransfer/Update/DeleteProhibited
$fred->domains()->unlock('example.tz'); // Removes prohibitions
```

#### Update Nameservers (Smart Diffing)
```php
$fred->domains()->updateNameservers('example.tz', [
    'ns1.newhost.com',
    'ns2.newhost.com',
]);
```

#### Request EPP Auth Code via Registry Email
```php
$fred->domains()->requestAuthCode('example.tz');
```

---

### 2. Contact Management (`$fred->contacts()`)

#### Create Contact
```php
$contact = $fred->contacts()->create([
    'id' => 'CNT-JOHN-DOE', // Optional, auto-generated if omitted
    'name' => 'John Doe',
    'org' => 'Company Ltd',
    'street' => 'Posta Road',
    'city' => 'Dar es Salaam',
    'pc' => '11101',
    'cc' => 'TZ',
    'phone' => '+255.712345678',
    'email' => 'john@example.tz',
    'disclose' => ['email', 'voice'], // Privacy disclosure flags
]);
```

#### Get Contact Info
```php
$info = $fred->contacts()->info('CNT-JOHN-DOE');
```

#### Update Contact
```php
$fred->contacts()->update('CNT-JOHN-DOE', chg: [
    'email' => 'newemail@example.tz',
    'city' => 'Arusha',
]);
```

#### Delete Contact
```php
$fred->contacts()->delete('CNT-JOHN-DOE');
```

---

### 3. Nameserver Sets (`$fred->nameservers()`)

```php
// Create NSSet
$fred->nameservers()->create(
    nssetId: 'NSS-MYHOSTING',
    nameservers: [
        ['name' => 'ns1.myhosting.tz', 'addresses' => ['192.0.2.1', '2001:db8::1']],
        'ns2.myhosting.tz',
    ],
    techContacts: ['CNT-TECH-1']
);

// Get NSSet Info
$nssetInfo = $fred->nameservers()->info('NSS-MYHOSTING');

// Update NSSet
$fred->nameservers()->update('NSS-MYHOSTING', addNameservers: ['ns3.myhosting.tz']);

// Delete NSSet
$fred->nameservers()->delete('NSS-MYHOSTING');
```

---

### 4. DNSSEC KeySets (`$fred->dnssec()`)

```php
// Create KeySet
$fred->dnssec()->create(
    keysetId: 'KEY-MYDOMAIN',
    dnskeys: [
        [
            'flags' => 257,
            'protocol' => 3,
            'alg' => 13,
            'pubKey' => 'cHVibGljS2V5RGF0YQ==',
        ],
    ],
    techContacts: ['CNT-TECH-1']
);

// Get KeySet Info
$keysetInfo = $fred->dnssec()->info('KEY-MYDOMAIN');
```

---

### 5. Account & Registrar Balance (`$fred->account()`)

```php
$account = $fred->account()->getBalance();
/*
[
    'balance' => [
        'TZ' => ['available' => 250000.0, 'currency' => 'TZS'],
        'CO.TZ' => ['available' => 150000.0, 'currency' => 'TZS'],
    ]
]
*/
```

---

### 6. EPP Poll Message Queue (`$fred->poll()`)

```php
$poll = $fred->poll()->request();

if ($poll['hasMessages']) {
    echo "Message [{$poll['msgId']}]: {$poll['msg']}\n";

    // Acknowledge and dequeue
    $fred->poll()->ack($poll['msgId']);
}
```

---

## Error Handling

All EPP protocol errors throw typed exceptions containing error codes and server messages:

```php
use AfricoreDev\FredPhp\Exceptions\EppCommandException;
use AfricoreDev\FredPhp\Exceptions\EppAuthenticationException;
use AfricoreDev\FredPhp\Exceptions\EppConnectionException;

try {
    $fred->domains()->register([...]);
} catch (EppAuthenticationException $e) {
    // Authentication / Certificate failed
} catch (EppCommandException $e) {
    echo "EPP Error Code: " . $e->getResultCode() . "\n";
    echo "EPP Message: " . $e->getEppMessage() . "\n";
    print_r($e->getResponse());
} catch (EppConnectionException $e) {
    // Socket / TLS connection failure
}
```

---

## Running Tests

```bash
composer test
# or
./vendor/bin/pest
./vendor/bin/phpstan analyse src --level=8
```

---

## License

The MIT License (MIT).
