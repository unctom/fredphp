<?php

namespace AfricoreDev\FredPhp\Services;

use AfricoreDev\FredPhp\Epp\EppClient;
use AfricoreDev\FredPhp\Exceptions\EppException;
use InvalidArgumentException;
use Throwable;

class DomainService
{
    public function __construct(
        protected EppClient $client,
        protected ContactService $contactService,
        protected NameserverService $nameserverService,
        protected DnssecService $dnssecService,
    ) {
    }

    /**
     * @param string|array<string> $domainNames
     * @return array<string, array{domainName: string, available: bool, reason: ?string}>|array{domainName: string, available: bool, reason: ?string}
     */
    public function check(string|array $domainNames): array
    {
        $isSingle = is_string($domainNames);
        $names = is_array($domainNames) ? $domainNames : [$domainNames];

        $xml = $this->client->getXmlBuilder()->domainCheckCommand($names);
        $parsed = $this->client->request($xml);

        $chkData = $parsed['epp']['response']['resData']['chkData']['cd'] ?? [];
        $cdList = isset($chkData['name']) || isset($chkData[0]) ? (isset($chkData[0]) ? $chkData : [$chkData]) : [];

        $results = [];
        foreach ($cdList as $cd) {
            $nameVal = is_array($cd['name'] ?? null) ? ($cd['name']['@value'] ?? '') : ($cd['name'] ?? '');
            $availAttr = $cd['name']['@attributes']['avail'] ?? ($cd['@attributes']['avail'] ?? null);
            $available = filter_var($availAttr, FILTER_VALIDATE_BOOLEAN);
            $reason = is_array($cd['reason'] ?? null) ? ($cd['reason']['@value'] ?? null) : ($cd['reason'] ?? null);

            $results[$nameVal] = [
                'domainName' => (string) $nameVal,
                'available' => $available,
                'reason' => $reason,
            ];
        }

        if ($isSingle) {
            return $results[$names[0]] ?? [
                'domainName' => $names[0],
                'available' => false,
                'reason' => null,
            ];
        }

        return $results;
    }

    public function isAvailable(string $domainName): bool
    {
        $result = $this->check($domainName);

        return (bool) ($result['available'] ?? false);
    }

    /**
     * @return array{
     *     domainName: string,
     *     status: array<string>,
     *     registrant: ?string,
     *     admin: array<string>|string|null,
     *     nsset: ?string,
     *     nameservers: array<string>,
     *     keyset: ?string,
     *     expires: ?string,
     *     crDate: ?string,
     *     upDate: ?string,
     *     trDate: ?string,
     *     authInfo: ?string,
     *     clID: ?string,
     *     crID: ?string,
     *     upID: ?string,
     *     raw: array<string, mixed>,
     * }
     */
    public function info(string $domainName, ?string $authInfo = null): array
    {
        if (trim($domainName) === '') {
            throw new InvalidArgumentException('domainName is required');
        }

        $xml = $this->client->getXmlBuilder()->domainInfoCommand($domainName, $authInfo);
        $parsed = $this->client->request($xml);

        $infData = $parsed['epp']['response']['resData']['infData'] ?? [];

        $statuses = [];
        if (isset($infData['status'])) {
            $statusData = is_array($infData['status']) && ! isset($infData['status']['@attributes'])
                ? $infData['status']
                : [$infData['status']];

            foreach ($statusData as $st) {
                if (isset($st['@attributes']['s'])) {
                    $statuses[] = $st['@attributes']['s'];
                } elseif (is_string($st)) {
                    $statuses[] = $st;
                }
            }
        }

        $nameservers = [];
        $nsset = null;
        if (isset($infData['nsset'])) {
            $nsset = is_array($infData['nsset']) ? ($infData['nsset']['@value'] ?? '') : (string) $infData['nsset'];
            $nameservers[] = $nsset;
        } elseif (isset($infData['ns']['hostObj'])) {
            $nsData = is_array($infData['ns']['hostObj']) ? $infData['ns']['hostObj'] : [$infData['ns']['hostObj']];
            $nameservers = array_map(fn ($ns) => is_string($ns) ? $ns : (string) ($ns['@value'] ?? ''), $nsData);
        }

        $admin = null;
        if (isset($infData['admin'])) {
            if (is_array($infData['admin'])) {
                if (isset($infData['admin']['@value'])) {
                    $admin = (string) $infData['admin']['@value'];
                } else {
                    $admin = array_map(fn ($adm) => is_string($adm) ? $adm : (string) ($adm['@value'] ?? ''), $infData['admin']);
                }
            } else {
                $admin = (string) $infData['admin'];
            }
        }

        $registrant = is_array($infData['registrant'] ?? null)
            ? ($infData['registrant']['@value'] ?? null)
            : ($infData['registrant'] ?? null);

        $exDate = is_array($infData['exDate'] ?? null)
            ? ($infData['exDate']['@value'] ?? null)
            : ($infData['exDate'] ?? null);

        $keyset = is_array($infData['keyset'] ?? null)
            ? ($infData['keyset']['@value'] ?? null)
            : ($infData['keyset'] ?? null);

        return [
            'domainName' => $domainName,
            'status' => $statuses,
            'registrant' => $registrant,
            'admin' => $admin,
            'nsset' => $nsset,
            'nameservers' => $nameservers,
            'keyset' => $keyset,
            'expires' => $exDate,
            'crDate' => is_array($infData['crDate'] ?? null) ? ($infData['crDate']['@value'] ?? null) : ($infData['crDate'] ?? null),
            'upDate' => is_array($infData['upDate'] ?? null) ? ($infData['upDate']['@value'] ?? null) : ($infData['upDate'] ?? null),
            'trDate' => is_array($infData['trDate'] ?? null) ? ($infData['trDate']['@value'] ?? null) : ($infData['trDate'] ?? null),
            'authInfo' => is_array($infData['authInfo'] ?? null) ? ($infData['authInfo']['@value'] ?? null) : ($infData['authInfo'] ?? null),
            'clID' => $infData['clID'] ?? null,
            'crID' => $infData['crID'] ?? null,
            'upID' => $infData['upID'] ?? null,
            'raw' => $infData,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *     registered: bool,
     *     domainName: string,
     *     creationDate: ?string,
     *     expirationDate: ?string,
     *     raw: array<string, mixed>,
     * }
     */
    public function register(array $data): array
    {
        $domainName = $data['domainName'] ?? $data['name'] ?? null;
        $registrant = $data['registrant'] ?? null;
        $registrantInfo = $data['registrantInfo'] ?? null;
        $adminInfo = $data['adminInfo'] ?? null;
        $nameserversInput = $data['nameservers'] ?? [];
        $nsset = $data['nsset'] ?? null;
        $keyset = $data['keyset'] ?? null;
        $period = (int) ($data['period'] ?? 1);
        $periodUnit = strtolower($data['periodUnit'] ?? 'y');
        $authInfo = $data['authInfo'] ?? null;

        if (! $domainName || (! $registrant && ! $registrantInfo)) {
            throw new InvalidArgumentException('domainName and registrant (or registrantInfo) are required for registration.');
        }

        // Auto-create registrant contact if details array provided
        if (! $registrant && is_array($registrantInfo)) {
            $registrantId = 'REG-'.strtoupper(bin2hex(random_bytes(4)));
            $registrantInfo['contactId'] = $registrantId;
            $this->contactService->create($registrantInfo);
            $registrant = $registrantId;
        }

        // Process admin contacts
        $adminsArr = [];
        if (! empty($data['admin'])) {
            $adminsArr = is_array($data['admin']) ? $data['admin'] : [$data['admin']];
        }

        if ($adminInfo) {
            $adminId = 'ADM-'.strtoupper(bin2hex(random_bytes(4)));
            $adminInfo['contactId'] = $adminId;
            $this->contactService->create($adminInfo);
            $adminsArr[] = $adminId;
        }

        // Auto-create NSSet if nameservers array provided without nssetId
        $nameserversInput = array_filter($nameserversInput);
        if (! $nsset && ! empty($nameserversInput)) {
            $nsData = [];
            foreach ($nameserversInput as $ns) {
                if (is_array($ns) && isset($ns['name'])) {
                    $nsData[] = [
                        'name' => (string) $ns['name'],
                        'addresses' => isset($ns['addresses']) && is_array($ns['addresses']) ? array_map('strval', $ns['addresses']) : [],
                    ];
                } else {
                    $nsData[] = (string) (is_array($ns) ? ($ns['name'] ?? '') : $ns);
                }
            }
            $techContact = $adminsArr[0] ?? $registrant;
            $generatedNssetId = 'NSS-'.strtoupper(bin2hex(random_bytes(4)));
            $this->nameserverService->create($generatedNssetId, $nsData, [(string) $techContact]);
            $nsset = $generatedNssetId;
        }

        $createData = [
            'name' => $domainName,
            'period' => $period,
            'periodUnit' => $periodUnit,
            'registrant' => (string) $registrant,
        ];

        if (! empty($adminsArr)) {
            $createData['admin'] = $adminsArr;
        }

        if ($nsset) {
            $createData['nsset'] = $nsset;
        }

        if ($keyset) {
            $createData['keyset'] = $keyset;
        }

        if ($authInfo) {
            $createData['authInfo'] = $authInfo;
        }

        $xml = $this->client->getXmlBuilder()->domainCreateCommand($createData);
        $parsed = $this->client->request($xml);

        $creData = $parsed['epp']['response']['resData']['creData'] ?? [];

        return [
            'registered' => true,
            'domainName' => is_array($creData['name'] ?? null) ? ($creData['name']['@value'] ?? $domainName) : ($creData['name'] ?? $domainName),
            'creationDate' => is_array($creData['crDate'] ?? null) ? ($creData['crDate']['@value'] ?? null) : ($creData['crDate'] ?? null),
            'expirationDate' => is_array($creData['exDate'] ?? null) ? ($creData['exDate']['@value'] ?? null) : ($creData['exDate'] ?? null),
            'raw' => $creData,
        ];
    }

    /**
     * @return array{renewed: bool, domainName: string, expirationDate: ?string}
     */
    public function renew(
        string $domainName,
        ?string $currentExpirationDate = null,
        int $period = 1,
        string $periodUnit = 'y',
    ): array {
        if (trim($domainName) === '') {
            throw new InvalidArgumentException('domainName is required for renewal.');
        }

        if ($currentExpirationDate === null || $currentExpirationDate === '') {
            $domainInfo = $this->info($domainName);
            $currentExpirationDate = $domainInfo['expires'] ?? null;

            if (! $currentExpirationDate) {
                throw new InvalidArgumentException('Could not determine current expiration date for the domain.');
            }

            $currentExpirationDate = substr($currentExpirationDate, 0, 10);
        }

        $xml = $this->client->getXmlBuilder()->domainRenewCommand(
            $domainName,
            $currentExpirationDate,
            $period,
            $periodUnit
        );
        $parsed = $this->client->request($xml);

        $renData = $parsed['epp']['response']['resData']['renData'] ?? [];

        return [
            'renewed' => true,
            'domainName' => is_array($renData['name'] ?? null) ? ($renData['name']['@value'] ?? $domainName) : ($renData['name'] ?? $domainName),
            'expirationDate' => is_array($renData['exDate'] ?? null) ? ($renData['exDate']['@value'] ?? null) : ($renData['exDate'] ?? null),
        ];
    }

    /**
     * @return array{transferred: bool, domainName: string, op: string}
     */
    public function transfer(string $domainName, string $authInfo, string $op = 'request'): array
    {
        if (trim($domainName) === '' || trim($authInfo) === '') {
            throw new InvalidArgumentException('domainName and authInfo are required for transfer.');
        }

        $xml = $this->client->getXmlBuilder()->domainTransferCommand($domainName, $authInfo, $op);
        $this->client->request($xml);

        return [
            'transferred' => true,
            'domainName' => $domainName,
            'op' => $op,
        ];
    }

    /**
     * Locks domain by setting clientTransferProhibited, clientUpdateProhibited, clientDeleteProhibited
     *
     * @return array{updated: bool, lockStatus: bool}
     */
    public function lock(string $domainName): array
    {
        return $this->updateLock($domainName, true);
    }

    /**
     * Unlocks domain by removing clientTransferProhibited, clientUpdateProhibited, clientDeleteProhibited
     *
     * @return array{updated: bool, lockStatus: bool}
     */
    public function unlock(string $domainName): array
    {
        return $this->updateLock($domainName, false);
    }

    /**
     * @return array{updated: bool, lockStatus: bool}
     */
    public function updateLock(string $domainName, bool $lockStatus = true): array
    {
        $statuses = ['clientTransferProhibited', 'clientUpdateProhibited', 'clientDeleteProhibited'];
        $statusArray = [];
        foreach ($statuses as $status) {
            $statusArray[] = [
                '_attributes' => ['s' => $status],
            ];
        }

        $action = $lockStatus ? 'add' : 'rem';
        $payload = [
            $action => [
                'domain:status' => $statusArray,
            ],
        ];

        $xml = $this->client->getXmlBuilder()->domainUpdateCommand(
            $domainName,
            add: $lockStatus ? ['domain:status' => $statusArray] : [],
            rem: ! $lockStatus ? ['domain:status' => $statusArray] : []
        );

        $this->client->request($xml);

        return [
            'updated' => true,
            'lockStatus' => $lockStatus,
        ];
    }

    /**
     * @param array<string> $addAdmins
     * @param array<string> $remAdmins
     * @return array{updated: bool, message: string}
     */
    public function updateContacts(
        string $domainName,
        array $addAdmins = [],
        array $remAdmins = [],
        ?string $registrant = null,
        ?string $nsset = null,
        ?string $keyset = null,
    ): array {
        if (trim($domainName) === '') {
            throw new InvalidArgumentException('domainName is required');
        }

        $add = [];
        if (! empty($addAdmins)) {
            $add['domain:admin'] = $addAdmins;
        }

        $rem = [];
        if (! empty($remAdmins)) {
            $rem['domain:admin'] = $remAdmins;
        }

        $chg = [];
        if ($registrant !== null && $registrant !== '') {
            $chg['domain:registrant'] = $registrant;
        }
        if ($nsset !== null) {
            $chg['domain:nsset'] = $nsset;
        }
        if ($keyset !== null) {
            $chg['domain:keyset'] = $keyset;
        }

        if (empty($add) && empty($rem) && empty($chg)) {
            throw new InvalidArgumentException('No contact or set updates provided.');
        }

        $xml = $this->client->getXmlBuilder()->domainUpdateCommand(
            name: $domainName,
            add: $add,
            rem: $rem,
            chg: $chg
        );

        $this->client->request($xml);

        return [
            'updated' => true,
            'message' => "Domain contacts for {$domainName} updated successfully.",
        ];
    }

    /**
     * @param array<string, mixed>|null $registrantInfo
     * @param array<string, mixed>|null $adminInfo
     * @return array{updated: bool}
     */
    public function updateContactDetails(
        string $domainName,
        ?array $registrantInfo = null,
        ?array $adminInfo = null,
    ): array {
        $updates = [];

        if ($registrantInfo) {
            $contactId = 'REG-'.strtoupper(bin2hex(random_bytes(4)));
            $registrantInfo['contactId'] = $contactId;
            $this->contactService->create($registrantInfo);
            $updates['registrant'] = $contactId;
        }

        if ($adminInfo) {
            $contactId = 'ADM-'.strtoupper(bin2hex(random_bytes(4)));
            $adminInfo['contactId'] = $contactId;
            $this->contactService->create($adminInfo);

            $domainInfo = $this->info($domainName);
            $currentAdmins = $domainInfo['admin']
                ? (is_array($domainInfo['admin']) ? $domainInfo['admin'] : [$domainInfo['admin']])
                : [];

            $updates['addAdmins'] = [$contactId];
            $updates['remAdmins'] = $currentAdmins;
        }

        if (! empty($updates)) {
            $this->updateContacts(
                domainName: $domainName,
                addAdmins: $updates['addAdmins'] ?? [],
                remAdmins: $updates['remAdmins'] ?? [],
                registrant: $updates['registrant'] ?? null
            );
        }

        return ['updated' => true];
    }

    public function updateNsset(string $domainName, string $nssetId): bool
    {
        $xml = $this->client->getXmlBuilder()->domainUpdateCommand(
            name: $domainName,
            chg: [
                'domain:nsset' => $nssetId,
            ]
        );

        $this->client->request($xml);

        return true;
    }

    public function updateKeyset(string $domainName, string $keysetId): bool
    {
        $xml = $this->client->getXmlBuilder()->domainUpdateCommand(
            name: $domainName,
            chg: [
                'domain:keyset' => $keysetId,
            ]
        );

        $this->client->request($xml);

        return true;
    }

    /**
     * @param array<string>|array<array{name: string, addresses?: array<string>}> $nameserversInput
     * @return array{action: string, nssetId?: string}
     */
    public function updateNameservers(string $domainName, array $nameserversInput): array
    {
        $nameserversInput = array_values(array_filter($nameserversInput));
        $domainInfo = $this->info($domainName);
        $currentNssetId = $domainInfo['nsset'] ?? ($domainInfo['nameservers'][0] ?? null);

        if (empty($nameserversInput)) {
            if ($currentNssetId) {
                $this->updateNsset($domainName, '');
            }

            return ['action' => 'removed'];
        }

        $nsData = array_map(function ($ns) {
            return is_array($ns) ? $ns : ['name' => (string) $ns];
        }, $nameserversInput);

        // If currently linked to a managed NSSet (e.g. NSS-...)
        if ($currentNssetId && str_starts_with($currentNssetId, 'NSS-')) {
            $currentInfo = $this->nameserverService->info($currentNssetId);
            $currentNs = $currentInfo['nameservers'];

            $addNameservers = [];
            $remNameservers = [];

            $normalize = fn ($ns) => strtolower(rtrim(trim((string) $ns), '.'));
            $currentNsNames = array_map(fn ($ns) => $normalize($ns['name']), $currentNs);
            $inputNsNames = array_map(fn ($ns) => $normalize($ns['name']), $nsData);

            foreach ($nsData as $ns) {
                if (! in_array($normalize($ns['name']), $currentNsNames, true)) {
                    $addNameservers[] = $ns;
                }
            }

            foreach ($currentNs as $ns) {
                if (! in_array($normalize($ns['name']), $inputNsNames, true)) {
                    $remNameservers[] = $ns['name'];
                }
            }

            if (! empty($addNameservers) || ! empty($remNameservers)) {
                $this->nameserverService->update(
                    nssetId: $currentNssetId,
                    addNameservers: $addNameservers,
                    remNameservers: $remNameservers
                );
            }

            return ['action' => 'updated', 'nssetId' => $currentNssetId];
        }

        // If no managed NSSet exists, create new and link to domain
        $techContact = null;
        if (! empty($domainInfo['admin'])) {
            $techContact = is_array($domainInfo['admin']) ? ($domainInfo['admin'][0] ?? null) : $domainInfo['admin'];
        } else {
            $techContact = $domainInfo['registrant'] ?? null;
        }

        $techContacts = $techContact !== null ? [(string) $techContact] : [];

        if (empty($techContacts)) {
            throw new InvalidArgumentException('A tech contact could not be resolved for the NSSet. It is mandatory in FRED.');
        }

        $newNssetId = 'NSS-'.strtoupper(bin2hex(random_bytes(4)));
        $this->nameserverService->create($newNssetId, $nsData, $techContacts);
        $this->updateNsset($domainName, $newNssetId);

        return ['action' => 'created_and_linked', 'nssetId' => $newNssetId];
    }

    /**
     * @return array{
     *     nssetId: ?string,
     *     nameservers: array<array{name: string, addresses?: array<string>}>,
     * }
     */
    public function getNameservers(string $domainName): array
    {
        $domainInfo = $this->info($domainName);
        $nssetId = $domainInfo['nsset'] ?? ($domainInfo['nameservers'][0] ?? null);

        if (! $nssetId) {
            return [
                'nssetId' => null,
                'nameservers' => [],
            ];
        }

        try {
            $nssetInfo = $this->nameserverService->info($nssetId);

            return [
                'nssetId' => $nssetId,
                'nameservers' => $nssetInfo['nameservers'],
            ];
        } catch (Throwable) {
            return [
                'nssetId' => $nssetId,
                'nameservers' => array_map(fn ($ns) => ['name' => $ns], $domainInfo['nameservers']),
            ];
        }
    }

    /**
     * Requests sending of domain authorization code (authInfo) via registrar/registry email.
     *
     * @return array{sent: bool, message: string, domainName: string}
     */
    public function requestAuthCode(string $domainName): array
    {
        if (trim($domainName) === '') {
            throw new InvalidArgumentException('domainName is required');
        }

        $xml = $this->client->getXmlBuilder()->sendAuthInfoCommand($domainName);
        $this->client->request($xml);

        return [
            'sent' => true,
            'message' => 'EPP auth code request accepted. The code will be sent to the registrant and admin contacts.',
            'domainName' => $domainName,
        ];
    }

    /**
     * @return array{deleted: bool, domainName: string}
     */
    public function delete(string $domainName): array
    {
        if (trim($domainName) === '') {
            throw new InvalidArgumentException('domainName is required');
        }

        $xml = $this->client->getXmlBuilder()->domainDeleteCommand($domainName);
        $this->client->request($xml);

        return [
            'deleted' => true,
            'domainName' => $domainName,
        ];
    }
}
