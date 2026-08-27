<?php

namespace AfricoreDev\FredPhp\Xml;

use DOMDocument;
use DOMElement;
use RuntimeException;

final class EppXmlBuilder
{
    private const EPP_NAMESPACE = 'urn:ietf:params:xml:ns:epp-1.0';

    private const NAMESPACES = [
        'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        'xmlns:domain' => 'http://www.nic.cz/xml/epp/domain-1.4',
        'xmlns:contact' => 'http://www.nic.cz/xml/epp/contact-1.6',
        'xmlns:nsset' => 'http://www.nic.cz/xml/epp/nsset-1.2',
        'xmlns:keyset' => 'http://www.nic.cz/xml/epp/keyset-1.3',
        'xmlns:fred' => 'http://www.nic.cz/xml/epp/fred-1.5',
        'xmlns:enumval' => 'http://www.nic.cz/xml/epp/enumval-1.2',
    ];

    public function generateTrid(string $prefix = 'REQ'): string
    {
        return sprintf('%s-%s-%s', $prefix, bin2hex(random_bytes(4)), dechex((int) (microtime(true) * 1000)));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function build(
        array $data,
        string $rootElement = 'epp',
    ): string {
        // Auto-inject clTRID if not explicitly provided
        if (isset($data['command']) && is_array($data['command']) && ! isset($data['command']['clTRID'])) {
            $data['command']['clTRID'] = $this->generateTrid('REQ');
        }

        if (
            isset($data['extension']['fred:extcommand']) &&
            is_array($data['extension']['fred:extcommand']) &&
            ! isset($data['extension']['fred:extcommand']['fred:clTRID'])
        ) {
            $data['extension']['fred:extcommand']['fred:clTRID'] = $this->generateTrid('REQ');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;

        $root = $document->createElementNS(
            self::EPP_NAMESPACE,
            $rootElement,
        );

        foreach (self::NAMESPACES as $name => $value) {
            $root->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                $name,
                $value,
            );
        }

        $root->setAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation',
            'urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd',
        );

        $document->appendChild($root);

        $this->appendArray(
            $document,
            $root,
            $data,
        );

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new RuntimeException('Failed to generate EPP XML document.');
        }

        return $xml;
    }

    // ==========================================
    // Session & System Commands
    // ==========================================

    public function loginCommand(
        string $clID,
        string $password,
        ?string $clTRID = null,
    ): string {
        $loginData = [
            'command' => [
                'login' => [
                    'clID' => $clID,
                    'pw' => $password,
                    'options' => [
                        'version' => '1.0',
                        'lang' => 'en',
                    ],
                    'svcs' => [
                        'objURI' => [
                            'http://www.nic.cz/xml/epp/domain-1.4',
                            'http://www.nic.cz/xml/epp/contact-1.6',
                            'http://www.nic.cz/xml/epp/nsset-1.2',
                            'http://www.nic.cz/xml/epp/keyset-1.3',
                        ],
                        'svcExtension' => [
                            'extURI' => [
                                'http://www.nic.cz/xml/epp/fred-1.5',
                                'http://www.nic.cz/xml/epp/enumval-1.2',
                            ],
                        ],
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('LGN'),
            ],
        ];

        return $this->build($loginData);
    }

    public function logoutCommand(?string $clTRID = null): string
    {
        return $this->build([
            'command' => [
                'logout' => [],
                'clTRID' => $clTRID ?? $this->generateTrid('LGO'),
            ],
        ]);
    }

    public function helloCommand(): string
    {
        return $this->build([
            'hello' => [],
        ]);
    }

    public function pollRequestCommand(?string $clTRID = null): string
    {
        return $this->build([
            'command' => [
                'poll' => [
                    '_attributes' => ['op' => 'req'],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('POL'),
            ],
        ]);
    }

    public function pollAckCommand(string $msgId, ?string $clTRID = null): string
    {
        return $this->build([
            'command' => [
                'poll' => [
                    '_attributes' => ['op' => 'ack', 'msgID' => $msgId],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('ACK'),
            ],
        ]);
    }

    // ==========================================
    // FRED Extensions (Account & AuthInfo)
    // ==========================================

    public function creditInfoCommand(?string $clTRID = null): string
    {
        return $this->build([
            'extension' => [
                'fred:extcommand' => [
                    'fred:creditInfo' => '',
                    'fred:clTRID' => $clTRID ?? $this->generateTrid('CRD'),
                ],
            ],
        ]);
    }

    public function sendAuthInfoCommand(string $domainName, ?string $clTRID = null): string
    {
        return $this->build([
            'extension' => [
                'fred:extcommand' => [
                    'fred:sendAuthInfo' => [
                        'domain:sendAuthInfo' => [
                            'domain:name' => $domainName,
                        ],
                    ],
                    'fred:clTRID' => $clTRID ?? $this->generateTrid('AUT'),
                ],
            ],
        ]);
    }

    // ==========================================
    // Domain Commands
    // ==========================================

    /**
     * @param string|array<string> $names
     */
    public function domainCheckCommand(string|array $names, ?string $clTRID = null): string
    {
        $nameList = is_array($names) ? $names : [$names];

        return $this->build([
            'command' => [
                'check' => [
                    'domain:check' => [
                        'domain:name' => $nameList,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('CHK'),
            ],
        ]);
    }

    public function domainInfoCommand(string $name, ?string $authInfo = null, ?string $clTRID = null): string
    {
        $infoData = [
            'domain:name' => $name,
        ];

        if ($authInfo !== null && $authInfo !== '') {
            $infoData['domain:authInfo'] = $authInfo;
        }

        return $this->build([
            'command' => [
                'info' => [
                    'domain:info' => $infoData,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('INF'),
            ],
        ]);
    }

    /**
     * @param array{
     *     name: string,
     *     period?: int,
     *     periodUnit?: string,
     *     registrant?: string,
     *     admin?: string|array<string>,
     *     nsset?: string,
     *     keyset?: string,
     *     authInfo?: string,
     * } $data
     */
    public function domainCreateCommand(array $data, ?string $clTRID = null): string
    {
        $createData = [
            'domain:name' => $data['name'],
        ];

        if (isset($data['period']) && (int) $data['period'] > 0) {
            $createData['domain:period'] = [
                '_attributes' => ['unit' => strtolower($data['periodUnit'] ?? 'y')],
                '_value' => (string) $data['period'],
            ];
        }

        if (! empty($data['nsset'])) {
            $createData['domain:nsset'] = (string) $data['nsset'];
        }

        if (! empty($data['keyset'])) {
            $createData['domain:keyset'] = (string) $data['keyset'];
        }

        if (! empty($data['registrant'])) {
            $createData['domain:registrant'] = (string) $data['registrant'];
        }

        if (! empty($data['admin'])) {
            $createData['domain:admin'] = is_array($data['admin']) ? $data['admin'] : [$data['admin']];
        }

        if (! empty($data['authInfo'])) {
            $createData['domain:authInfo'] = (string) $data['authInfo'];
        }

        return $this->build([
            'command' => [
                'create' => [
                    'domain:create' => $createData,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('CRE'),
            ],
        ]);
    }

    public function domainRenewCommand(
        string $name,
        string $curExpDate,
        int $period = 1,
        string $periodUnit = 'y',
        ?string $clTRID = null,
    ): string {
        $renewData = [
            'domain:name' => $name,
            'domain:curExpDate' => $curExpDate,
        ];

        if ($period > 0) {
            $renewData['domain:period'] = [
                '_attributes' => ['unit' => strtolower($periodUnit)],
                '_value' => (string) $period,
            ];
        }

        return $this->build([
            'command' => [
                'renew' => [
                    'domain:renew' => $renewData,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('RNW'),
            ],
        ]);
    }

    public function domainTransferCommand(
        string $name,
        string $authInfo,
        string $op = 'request',
        ?string $clTRID = null,
    ): string {
        return $this->build([
            'command' => [
                'transfer' => [
                    '_attributes' => ['op' => $op],
                    'domain:transfer' => [
                        'domain:name' => $name,
                        'domain:authInfo' => $authInfo,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('TRN'),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $add
     * @param array<string, mixed> $rem
     * @param array<string, mixed> $chg
     */
    public function domainUpdateCommand(
        string $name,
        array $add = [],
        array $rem = [],
        array $chg = [],
        ?string $clTRID = null,
    ): string {
        $updateData = [
            'domain:name' => $name,
        ];

        if (! empty($add)) {
            $updateData['domain:add'] = $add;
        }

        if (! empty($rem)) {
            $updateData['domain:rem'] = $rem;
        }

        if (! empty($chg)) {
            $updateData['domain:chg'] = $chg;
        }

        return $this->build([
            'command' => [
                'update' => [
                    'domain:update' => $updateData,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('UPD'),
            ],
        ]);
    }

    public function domainDeleteCommand(string $name, ?string $clTRID = null): string
    {
        return $this->build([
            'command' => [
                'delete' => [
                    'domain:delete' => [
                        'domain:name' => $name,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('DEL'),
            ],
        ]);
    }

    // ==========================================
    // Contact Commands
    // ==========================================

    /**
     * @param string|array<string> $ids
     */
    public function contactCheckCommand(string|array $ids, ?string $clTRID = null): string
    {
        $idList = is_array($ids) ? $ids : [$ids];

        return $this->build([
            'command' => [
                'check' => [
                    'contact:check' => [
                        'contact:id' => $idList,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('CHK'),
            ],
        ]);
    }

    public function contactInfoCommand(string $id, ?string $authInfo = null, ?string $clTRID = null): string
    {
        $infoData = [
            'contact:id' => $id,
        ];

        if ($authInfo !== null && $authInfo !== '') {
            $infoData['contact:authInfo'] = $authInfo;
        }

        return $this->build([
            'command' => [
                'info' => [
                    'contact:info' => $infoData,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('INF'),
            ],
        ]);
    }

    /**
     * @param array{
     *     id: string,
     *     name: string,
     *     org?: string|null,
     *     street: string|array<string>,
     *     city: string,
     *     sp?: string|null,
     *     pc: string,
     *     cc: string,
     *     voice?: string|null,
     *     fax?: string|null,
     *     email: string,
     *     notifyEmail?: string|null,
     *     vat?: string|null,
     *     ident?: string|null,
     *     authInfo?: string|null,
     *     disclose?: array<string>|null,
     * } $data
     */
    public function contactCreateCommand(array $data, ?string $clTRID = null): string
    {
        $addr = [];
        $street = is_array($data['street']) ? $data['street'] : [$data['street']];
        $addr['contact:street'] = $street;
        $addr['contact:city'] = $data['city'];

        if (! empty($data['sp'])) {
            $addr['contact:sp'] = $data['sp'];
        }

        $addr['contact:pc'] = $data['pc'];
        $addr['contact:cc'] = strtoupper($data['cc']);

        $postalInfo = [
            'contact:name' => $data['name'],
        ];

        if (! empty($data['org'])) {
            $postalInfo['contact:org'] = $data['org'];
        }

        $postalInfo['contact:addr'] = $addr;

        $eppContact = [
            'contact:id' => $data['id'],
            'contact:postalInfo' => $postalInfo,
        ];

        if (! empty($data['voice'])) {
            $eppContact['contact:voice'] = $data['voice'];
        }

        if (! empty($data['fax'])) {
            $eppContact['contact:fax'] = $data['fax'];
        }

        $eppContact['contact:email'] = $data['email'];

        if (! empty($data['notifyEmail'])) {
            $eppContact['contact:notifyEmail'] = $data['notifyEmail'];
        }

        if (! empty($data['vat'])) {
            $eppContact['contact:vat'] = $data['vat'];
        }

        if (! empty($data['ident'])) {
            $eppContact['contact:ident'] = $data['ident'];
        }

        if (! empty($data['authInfo'])) {
            $eppContact['contact:authInfo'] = $data['authInfo'];
        }

        if (! empty($data['disclose'])) {
            $discloseElements = [];
            foreach ($data['disclose'] as $field) {
                if (in_array($field, ['addr', 'voice', 'fax', 'email', 'vat', 'ident', 'notifyEmail'], true)) {
                    $discloseElements['contact:'.$field] = '';
                }
            }
            $discloseElements['_attributes'] = ['flag' => '1'];
            $eppContact['contact:disclose'] = $discloseElements;
        }

        return $this->build([
            'command' => [
                'create' => [
                    'contact:create' => $eppContact,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('CRE'),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $chg
     * @param array<string>|null $disclose
     */
    public function contactUpdateCommand(
        string $id,
        array $chg = [],
        ?array $disclose = null,
        ?string $clTRID = null,
    ): string {
        $contactChg = [];

        if (
            isset($chg['name']) ||
            isset($chg['org']) ||
            isset($chg['street']) ||
            isset($chg['city']) ||
            isset($chg['sp']) ||
            isset($chg['pc']) ||
            isset($chg['cc'])
        ) {
            $postalInfo = [];
            if (isset($chg['name'])) {
                $postalInfo['contact:name'] = $chg['name'];
            }
            if (isset($chg['org'])) {
                $postalInfo['contact:org'] = $chg['org'];
            }

            if (isset($chg['street']) || isset($chg['city']) || isset($chg['sp']) || isset($chg['pc']) || isset($chg['cc'])) {
                $addr = [];
                if (isset($chg['street'])) {
                    $addr['contact:street'] = is_array($chg['street']) ? $chg['street'] : [$chg['street']];
                }
                if (isset($chg['city'])) {
                    $addr['contact:city'] = $chg['city'];
                }
                if (isset($chg['sp'])) {
                    $addr['contact:sp'] = $chg['sp'];
                }
                if (isset($chg['pc'])) {
                    $addr['contact:pc'] = $chg['pc'];
                }
                if (isset($chg['cc'])) {
                    $addr['contact:cc'] = strtoupper($chg['cc']);
                }
                $postalInfo['contact:addr'] = $addr;
            }
            $contactChg['contact:postalInfo'] = $postalInfo;
        }

        if (isset($chg['voice'])) {
            $contactChg['contact:voice'] = $chg['voice'];
        }

        if (isset($chg['fax'])) {
            $contactChg['contact:fax'] = $chg['fax'];
        }

        if (isset($chg['email'])) {
            $contactChg['contact:email'] = $chg['email'];
        }

        if (isset($chg['notifyEmail'])) {
            $contactChg['contact:notifyEmail'] = $chg['notifyEmail'];
        }

        if (isset($chg['vat'])) {
            $contactChg['contact:vat'] = $chg['vat'];
        }

        if (isset($chg['ident'])) {
            $contactChg['contact:ident'] = $chg['ident'];
        }

        if (isset($chg['authInfo'])) {
            $contactChg['contact:authInfo'] = $chg['authInfo'];
        }

        if ($disclose !== null) {
            $discloseElements = [];
            foreach ($disclose as $field) {
                if (in_array($field, ['addr', 'voice', 'fax', 'email', 'vat', 'ident', 'notifyEmail'], true)) {
                    $discloseElements['contact:'.$field] = '';
                }
            }
            $discloseElements['_attributes'] = ['flag' => '1'];
            $contactChg['contact:disclose'] = $discloseElements;
        }

        return $this->build([
            'command' => [
                'update' => [
                    'contact:update' => [
                        'contact:id' => $id,
                        'contact:chg' => $contactChg,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('UPD'),
            ],
        ]);
    }

    public function contactDeleteCommand(string $id, ?string $clTRID = null): string
    {
        return $this->build([
            'command' => [
                'delete' => [
                    'contact:delete' => [
                        'contact:id' => $id,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('DEL'),
            ],
        ]);
    }

    // ==========================================
    // NSSet (Nameserver Group) Commands
    // ==========================================

    /**
     * @param string|array<string> $ids
     */
    public function nssetCheckCommand(string|array $ids, ?string $clTRID = null): string
    {
        $idList = is_array($ids) ? $ids : [$ids];

        return $this->build([
            'command' => [
                'check' => [
                    'nsset:check' => [
                        'nsset:id' => $idList,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('CHK'),
            ],
        ]);
    }

    public function nssetInfoCommand(string $id, ?string $authInfo = null, ?string $clTRID = null): string
    {
        $infoData = [
            'nsset:id' => $id,
        ];

        if ($authInfo !== null && $authInfo !== '') {
            $infoData['nsset:authInfo'] = $authInfo;
        }

        return $this->build([
            'command' => [
                'info' => [
                    'nsset:info' => $infoData,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('INF'),
            ],
        ]);
    }

    /**
     * @param array<array{name: string, addresses?: array<string>}>|array<string> $nameservers
     * @param array<string> $techContacts
     */
    public function nssetCreateCommand(
        string $id,
        array $nameservers,
        array $techContacts = [],
        ?string $authInfo = null,
        ?string $clTRID = null,
    ): string {
        $nsData = [];
        foreach ($nameservers as $ns) {
            if (is_string($ns)) {
                $nsData[] = ['nsset:name' => $ns];
            } else {
                $nsItem = ['nsset:name' => $ns['name']];
                if (! empty($ns['addresses'])) {
                    $nsItem['nsset:addr'] = $ns['addresses'];
                }
                $nsData[] = $nsItem;
            }
        }

        $nssetCreate = [
            'nsset:id' => $id,
            'nsset:ns' => $nsData,
        ];

        if (! empty($techContacts)) {
            $nssetCreate['nsset:tech'] = $techContacts;
        }

        if (! empty($authInfo)) {
            $nssetCreate['nsset:authInfo'] = $authInfo;
        }

        return $this->build([
            'command' => [
                'create' => [
                    'nsset:create' => $nssetCreate,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('CRE'),
            ],
        ]);
    }

    /**
     * @param array<array{name: string, addresses?: array<string>}>|array<string> $addNameservers
     * @param array<string> $remNameservers
     * @param array<string> $addTech
     * @param array<string> $remTech
     */
    public function nssetUpdateCommand(
        string $id,
        array $addNameservers = [],
        array $remNameservers = [],
        array $addTech = [],
        array $remTech = [],
        ?string $authInfo = null,
        ?string $clTRID = null,
    ): string {
        $nssetUpdate = [
            'nsset:id' => $id,
        ];

        $add = [];
        if (! empty($addNameservers)) {
            $addNsData = [];
            foreach ($addNameservers as $ns) {
                if (is_string($ns)) {
                    $addNsData[] = ['nsset:name' => $ns];
                } else {
                    $nsItem = ['nsset:name' => $ns['name']];
                    if (! empty($ns['addresses'])) {
                        $nsItem['nsset:addr'] = $ns['addresses'];
                    }
                    $addNsData[] = $nsItem;
                }
            }
            $add['nsset:ns'] = $addNsData;
        }
        if (! empty($addTech)) {
            $add['nsset:tech'] = $addTech;
        }
        if (! empty($add)) {
            $nssetUpdate['nsset:add'] = $add;
        }

        $rem = [];
        if (! empty($remNameservers)) {
            $rem['nsset:name'] = $remNameservers;
        }
        if (! empty($remTech)) {
            $rem['nsset:tech'] = $remTech;
        }
        if (! empty($rem)) {
            $nssetUpdate['nsset:rem'] = $rem;
        }

        if (! empty($authInfo)) {
            $nssetUpdate['nsset:chg'] = [
                'nsset:authInfo' => $authInfo,
            ];
        }

        return $this->build([
            'command' => [
                'update' => [
                    'nsset:update' => $nssetUpdate,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('UPD'),
            ],
        ]);
    }

    public function nssetDeleteCommand(string $id, ?string $clTRID = null): string
    {
        return $this->build([
            'command' => [
                'delete' => [
                    'nsset:delete' => [
                        'nsset:id' => $id,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('DEL'),
            ],
        ]);
    }

    // ==========================================
    // KeySet (DNSSEC Group) Commands
    // ==========================================

    /**
     * @param string|array<string> $ids
     */
    public function keysetCheckCommand(string|array $ids, ?string $clTRID = null): string
    {
        $idList = is_array($ids) ? $ids : [$ids];

        return $this->build([
            'command' => [
                'check' => [
                    'keyset:check' => [
                        'keyset:id' => $idList,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('CHK'),
            ],
        ]);
    }

    public function keysetInfoCommand(string $id, ?string $authInfo = null, ?string $clTRID = null): string
    {
        $infoData = [
            'keyset:id' => $id,
        ];

        if ($authInfo !== null && $authInfo !== '') {
            $infoData['keyset:authInfo'] = $authInfo;
        }

        return $this->build([
            'command' => [
                'info' => [
                    'keyset:info' => $infoData,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('INF'),
            ],
        ]);
    }

    /**
     * @param array<array{flags: int|string, protocol: int|string, alg?: int|string, algorithm?: int|string, pubKey?: string, publicKey?: string}> $dnskeys
     * @param array<string> $techContacts
     */
    public function keysetCreateCommand(
        string $id,
        array $dnskeys,
        array $techContacts = [],
        ?string $authInfo = null,
        ?string $clTRID = null,
    ): string {
        $dnskeyData = [];
        foreach ($dnskeys as $dk) {
            $dnskeyData[] = [
                'keyset:flags' => (string) $dk['flags'],
                'keyset:protocol' => (string) $dk['protocol'],
                'keyset:alg' => (string) ($dk['alg'] ?? $dk['algorithm'] ?? ''),
                'keyset:pubKey' => (string) ($dk['pubKey'] ?? $dk['publicKey'] ?? ''),
            ];
        }

        $keysetCreate = [
            'keyset:id' => $id,
            'keyset:dnskey' => $dnskeyData,
        ];

        if (! empty($techContacts)) {
            $keysetCreate['keyset:tech'] = $techContacts;
        }

        if (! empty($authInfo)) {
            $keysetCreate['keyset:authInfo'] = $authInfo;
        }

        return $this->build([
            'command' => [
                'create' => [
                    'keyset:create' => $keysetCreate,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('CRE'),
            ],
        ]);
    }

    /**
     * @param array<array{flags: int|string, protocol: int|string, alg?: int|string, algorithm?: int|string, pubKey?: string, publicKey?: string}> $addDnskeys
     * @param array<array{flags: int|string, protocol: int|string, alg?: int|string, algorithm?: int|string, pubKey?: string, publicKey?: string}> $remDnskeys
     * @param array<string> $addTech
     * @param array<string> $remTech
     */
    public function keysetUpdateCommand(
        string $id,
        array $addDnskeys = [],
        array $remDnskeys = [],
        array $addTech = [],
        array $remTech = [],
        ?string $authInfo = null,
        ?string $clTRID = null,
    ): string {
        $keysetUpdate = [
            'keyset:id' => $id,
        ];

        $add = [];
        if (! empty($addDnskeys)) {
            $addDnskeyData = [];
            foreach ($addDnskeys as $dk) {
                $addDnskeyData[] = [
                    'keyset:flags' => (string) $dk['flags'],
                    'keyset:protocol' => (string) $dk['protocol'],
                    'keyset:alg' => (string) ($dk['alg'] ?? $dk['algorithm'] ?? ''),
                    'keyset:pubKey' => (string) ($dk['pubKey'] ?? $dk['publicKey'] ?? ''),
                ];
            }
            $add['keyset:dnskey'] = $addDnskeyData;
        }
        if (! empty($addTech)) {
            $add['keyset:tech'] = $addTech;
        }
        if (! empty($add)) {
            $keysetUpdate['keyset:add'] = $add;
        }

        $rem = [];
        if (! empty($remDnskeys)) {
            $remDnskeyData = [];
            foreach ($remDnskeys as $dk) {
                $remDnskeyData[] = [
                    'keyset:flags' => (string) $dk['flags'],
                    'keyset:protocol' => (string) $dk['protocol'],
                    'keyset:alg' => (string) ($dk['alg'] ?? $dk['algorithm'] ?? ''),
                    'keyset:pubKey' => (string) ($dk['pubKey'] ?? $dk['publicKey'] ?? ''),
                ];
            }
            $rem['keyset:dnskey'] = $remDnskeyData;
        }
        if (! empty($remTech)) {
            $rem['keyset:tech'] = $remTech;
        }
        if (! empty($rem)) {
            $keysetUpdate['keyset:rem'] = $rem;
        }

        if (! empty($authInfo)) {
            $keysetUpdate['keyset:chg'] = [
                'keyset:authInfo' => $authInfo,
            ];
        }

        return $this->build([
            'command' => [
                'update' => [
                    'keyset:update' => $keysetUpdate,
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('UPD'),
            ],
        ]);
    }

    public function keysetDeleteCommand(string $id, ?string $clTRID = null): string
    {
        return $this->build([
            'command' => [
                'delete' => [
                    'keyset:delete' => [
                        'keyset:id' => $id,
                    ],
                ],
                'clTRID' => $clTRID ?? $this->generateTrid('DEL'),
            ],
        ]);
    }

    // ==========================================
    // DOM Array Building Internals
    // ==========================================

    /**
     * @param array<string|int, mixed> $data
     */
    private function appendArray(
        DOMDocument $document,
        DOMElement $parent,
        array $data,
    ): void {
        foreach ($data as $key => $value) {
            if ($key === '_attributes' || $key === '@attributes') {
                foreach ($value as $attribute => $attributeValue) {
                    $parent->setAttribute(
                        $attribute,
                        (string) $attributeValue,
                    );
                }

                continue;
            }

            $key = str_replace('\\:', ':', (string) $key);

            if (is_array($value) && $value !== [] && array_is_list($value)) {
                foreach ($value as $item) {
                    $this->appendElement(
                        $document,
                        $parent,
                        $key,
                        $item,
                    );
                }

                continue;
            }

            $this->appendElement(
                $document,
                $parent,
                $key,
                $value,
            );
        }
    }

    private function appendElement(
        DOMDocument $document,
        DOMElement $parent,
        string $name,
        mixed $value,
    ): void {
        [$prefix, $localName] = $this->splitName($name);

        if ($prefix !== null) {
            $element = $document->createElementNS(
                $this->namespaceForPrefix($prefix),
                $name,
            );
        } else {
            $element = $document->createElement($localName);
        }

        if (is_array($value)) {
            $attributesKey = isset($value['_attributes']) ? '_attributes' : (isset($value['@attributes']) ? '@attributes' : null);

            if ($attributesKey !== null) {
                foreach ($value[$attributesKey] as $attribute => $attributeValue) {
                    $element->setAttribute(
                        $attribute,
                        (string) $attributeValue,
                    );
                }

                unset($value[$attributesKey]);
            }

            if (isset($value['_value']) || isset($value['@value'])) {
                $textValue = (string) ($value['_value'] ?? $value['@value']);
                $element->appendChild($document->createTextNode($textValue));
                unset($value['_value'], $value['@value']);
            }

            $this->appendArray(
                $document,
                $element,
                $value,
            );
        } elseif ($value !== null) {
            $element->appendChild(
                $document->createTextNode((string) $value)
            );
        }

        $parent->appendChild($element);
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function splitName(string $name): array
    {
        if (! str_contains($name, ':')) {
            return [null, $name];
        }

        $parts = explode(':', $name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function namespaceForPrefix(string $prefix): string
    {
        return match ($prefix) {
            'domain' => 'http://www.nic.cz/xml/epp/domain-1.4',
            'contact' => 'http://www.nic.cz/xml/epp/contact-1.6',
            'nsset' => 'http://www.nic.cz/xml/epp/nsset-1.2',
            'keyset' => 'http://www.nic.cz/xml/epp/keyset-1.3',
            'fred' => 'http://www.nic.cz/xml/epp/fred-1.5',
            'enumval' => 'http://www.nic.cz/xml/epp/enumval-1.2',
            'xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            default => throw new RuntimeException(
                "Unknown XML namespace prefix: {$prefix}"
            ),
        };
    }
}