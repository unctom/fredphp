<?php

namespace AfricoreDev\FredPhp\Services;

use AfricoreDev\FredPhp\Epp\EppClient;
use InvalidArgumentException;

class DnssecService
{
    public function __construct(
        protected EppClient $client,
    ) {
    }

    /**
     * @param string|array<string> $keysetIds
     * @return array<string, array{keysetId: string, available: bool, reason: ?string}>|array{keysetId: string, available: bool, reason: ?string}
     */
    public function check(string|array $keysetIds): array
    {
        $isSingle = is_string($keysetIds);
        $ids = is_array($keysetIds) ? $keysetIds : [$keysetIds];

        $xml = $this->client->getXmlBuilder()->keysetCheckCommand($ids);
        $parsed = $this->client->request($xml);

        $chkData = $parsed['epp']['response']['resData']['chkData']['cd'] ?? [];
        $cdList = isset($chkData['id']) || isset($chkData[0]) ? (isset($chkData[0]) ? $chkData : [$chkData]) : [];

        $results = [];
        foreach ($cdList as $cd) {
            $idVal = is_array($cd['id'] ?? null) ? ($cd['id']['@value'] ?? '') : ($cd['id'] ?? '');
            $availAttr = $cd['id']['@attributes']['avail'] ?? ($cd['@attributes']['avail'] ?? null);
            $available = filter_var($availAttr, FILTER_VALIDATE_BOOLEAN);
            $reason = is_array($cd['reason'] ?? null) ? ($cd['reason']['@value'] ?? null) : ($cd['reason'] ?? null);

            $results[$idVal] = [
                'keysetId' => (string) $idVal,
                'available' => $available,
                'reason' => $reason,
            ];
        }

        if ($isSingle) {
            return $results[$ids[0]] ?? [
                'keysetId' => $ids[0],
                'available' => false,
                'reason' => null,
            ];
        }

        return $results;
    }

    /**
     * @return array{
     *     keysetId: string,
     *     dnskeys: array<array{flags: string, protocol: string, alg: string, pubKey: string}>,
     *     technicalContacts: array<string>,
     *     clID?: string|null,
     *     crID?: string|null,
     *     crDate?: string|null,
     *     upID?: string|null,
     *     upDate?: string|null,
     *     raw: array<string, mixed>,
     * }
     */
    public function info(string $keysetId, ?string $authInfo = null): array
    {
        if (trim($keysetId) === '') {
            throw new InvalidArgumentException('keysetId is required');
        }

        $xml = $this->client->getXmlBuilder()->keysetInfoCommand($keysetId, $authInfo);
        $parsed = $this->client->request($xml);

        $infData = $parsed['epp']['response']['resData']['infData'] ?? [];

        $dnskeyData = [];
        if (isset($infData['dnskey'])) {
            $keys = is_array($infData['dnskey']) && ! isset($infData['dnskey']['flags'])
                ? $infData['dnskey']
                : [$infData['dnskey']];

            foreach ($keys as $k) {
                $dnskeyData[] = [
                    'flags' => is_array($k['flags'] ?? null) ? ($k['flags']['@value'] ?? '') : (string) ($k['flags'] ?? ''),
                    'protocol' => is_array($k['protocol'] ?? null) ? ($k['protocol']['@value'] ?? '') : (string) ($k['protocol'] ?? ''),
                    'alg' => is_array($k['alg'] ?? null) ? ($k['alg']['@value'] ?? '') : (string) ($k['alg'] ?? ''),
                    'pubKey' => is_array($k['pubKey'] ?? null) ? ($k['pubKey']['@value'] ?? '') : (string) ($k['pubKey'] ?? ''),
                ];
            }
        }

        $tech = [];
        if (isset($infData['tech'])) {
            $techList = is_array($infData['tech']) && ! isset($infData['tech']['@value']) ? $infData['tech'] : [$infData['tech']];
            foreach ($techList as $t) {
                $tech[] = is_array($t) ? ($t['@value'] ?? '') : (string) $t;
            }
        }

        return [
            'keysetId' => $keysetId,
            'dnskeys' => $dnskeyData,
            'technicalContacts' => $tech,
            'clID' => $infData['clID'] ?? null,
            'crID' => $infData['crID'] ?? null,
            'crDate' => $infData['crDate'] ?? null,
            'upID' => $infData['upID'] ?? null,
            'upDate' => $infData['upDate'] ?? null,
            'raw' => $infData,
        ];
    }

    /**
     * @param array<array{flags: int|string, protocol: int|string, alg?: int|string, algorithm?: int|string, pubKey?: string, publicKey?: string}> $dnskeys
     * @param array<string> $techContacts
     * @return array{created: bool, keysetId: string, crDate: ?string}
     */
    public function create(
        string $keysetId,
        array $dnskeys,
        array $techContacts = [],
        ?string $authInfo = null,
    ): array {
        if (trim($keysetId) === '') {
            throw new InvalidArgumentException('keysetId is required');
        }

        if (empty($dnskeys)) {
            throw new InvalidArgumentException('dnskeys array cannot be empty');
        }

        $xml = $this->client->getXmlBuilder()->keysetCreateCommand($keysetId, $dnskeys, $techContacts, $authInfo);
        $parsed = $this->client->request($xml);

        $creData = $parsed['epp']['response']['resData']['creData'] ?? [];
        $actualId = is_array($creData['id'] ?? null) ? ($creData['id']['@value'] ?? $keysetId) : ($creData['id'] ?? $keysetId);
        $crDate = is_array($creData['crDate'] ?? null) ? ($creData['crDate']['@value'] ?? null) : ($creData['crDate'] ?? null);

        return [
            'created' => true,
            'keysetId' => (string) $actualId,
            'crDate' => $crDate,
        ];
    }

    /**
     * @param array<array{flags: int|string, protocol: int|string, alg?: int|string, algorithm?: int|string, pubKey?: string, publicKey?: string}> $addDnskeys
     * @param array<array{flags: int|string, protocol: int|string, alg?: int|string, algorithm?: int|string, pubKey?: string, publicKey?: string}> $remDnskeys
     * @param array<string> $addTech
     * @param array<string> $remTech
     * @return array{updated: bool, keysetId: string}
     */
    public function update(
        string $keysetId,
        array $addDnskeys = [],
        array $remDnskeys = [],
        array $addTech = [],
        array $remTech = [],
        ?string $authInfo = null,
    ): array {
        if (trim($keysetId) === '') {
            throw new InvalidArgumentException('keysetId is required');
        }

        $xml = $this->client->getXmlBuilder()->keysetUpdateCommand(
            $keysetId,
            $addDnskeys,
            $remDnskeys,
            $addTech,
            $remTech,
            $authInfo
        );
        $this->client->request($xml);

        return [
            'updated' => true,
            'keysetId' => $keysetId,
        ];
    }

    /**
     * @return array{deleted: bool, keysetId: string}
     */
    public function delete(string $keysetId): array
    {
        if (trim($keysetId) === '') {
            throw new InvalidArgumentException('keysetId is required');
        }

        $xml = $this->client->getXmlBuilder()->keysetDeleteCommand($keysetId);
        $this->client->request($xml);

        return [
            'deleted' => true,
            'keysetId' => $keysetId,
        ];
    }
}
