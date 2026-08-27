<?php

namespace AfricoreDev\FredPhp\Services;

use AfricoreDev\FredPhp\Epp\EppClient;
use InvalidArgumentException;

class NameserverService
{
    public function __construct(
        protected EppClient $client,
    ) {
    }

    /**
     * @param string|array<string> $nssetIds
     * @return array<string, array{nssetId: string, available: bool, reason: ?string}>|array{nssetId: string, available: bool, reason: ?string}
     */
    public function check(string|array $nssetIds): array
    {
        $isSingle = is_string($nssetIds);
        $ids = is_array($nssetIds) ? $nssetIds : [$nssetIds];

        $xml = $this->client->getXmlBuilder()->nssetCheckCommand($ids);
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
                'nssetId' => (string) $idVal,
                'available' => $available,
                'reason' => $reason,
            ];
        }

        if ($isSingle) {
            return $results[$ids[0]] ?? [
                'nssetId' => $ids[0],
                'available' => false,
                'reason' => null,
            ];
        }

        return $results;
    }

    /**
     * @return array{
     *     nssetId: string,
     *     nameservers: array<array{name: string, addresses?: array<string>}>,
     *     technicalContacts: array<string>,
     *     clID?: string|null,
     *     crID?: string|null,
     *     crDate?: string|null,
     *     upID?: string|null,
     *     upDate?: string|null,
     *     raw: array<string, mixed>,
     * }
     */
    public function info(string $nssetId, ?string $authInfo = null): array
    {
        if (trim($nssetId) === '') {
            throw new InvalidArgumentException('nssetId is required');
        }

        $xml = $this->client->getXmlBuilder()->nssetInfoCommand($nssetId, $authInfo);
        $parsed = $this->client->request($xml);

        $infData = $parsed['epp']['response']['resData']['infData'] ?? [];

        $nameservers = [];
        if (isset($infData['ns'])) {
            $nsData = is_array($infData['ns']) && ! isset($infData['ns']['name']) ? $infData['ns'] : [$infData['ns']];
            foreach ($nsData as $ns) {
                if (isset($ns['name'])) {
                    $nsName = is_array($ns['name']) ? ($ns['name']['@value'] ?? '') : $ns['name'];
                    $nsItem = ['name' => (string) $nsName];

                    if (isset($ns['addr'])) {
                        $addrData = is_array($ns['addr']) && ! isset($ns['addr']['@value']) && isset($ns['addr'][0])
                            ? $ns['addr']
                            : [$ns['addr']];

                        $addresses = [];
                        foreach ($addrData as $addr) {
                            $addresses[] = is_array($addr) ? ($addr['@value'] ?? '') : (string) $addr;
                        }
                        $nsItem['addresses'] = $addresses;
                    }

                    $nameservers[] = $nsItem;
                }
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
            'nssetId' => $nssetId,
            'nameservers' => $nameservers,
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
     * @param array<array{name: string, addresses?: array<string>}>|array<string> $nameservers
     * @param array<string> $techContacts
     * @return array{created: bool, nssetId: string, crDate: ?string}
     */
    public function create(
        string $nssetId,
        array $nameservers,
        array $techContacts = [],
        ?string $authInfo = null,
    ): array {
        if (trim($nssetId) === '') {
            throw new InvalidArgumentException('nssetId is required');
        }

        if (empty($nameservers)) {
            throw new InvalidArgumentException('nameservers array cannot be empty');
        }

        $xml = $this->client->getXmlBuilder()->nssetCreateCommand($nssetId, $nameservers, $techContacts, $authInfo);
        $parsed = $this->client->request($xml);

        $creData = $parsed['epp']['response']['resData']['creData'] ?? [];
        $actualId = is_array($creData['id'] ?? null) ? ($creData['id']['@value'] ?? $nssetId) : ($creData['id'] ?? $nssetId);
        $crDate = is_array($creData['crDate'] ?? null) ? ($creData['crDate']['@value'] ?? null) : ($creData['crDate'] ?? null);

        return [
            'created' => true,
            'nssetId' => (string) $actualId,
            'crDate' => $crDate,
        ];
    }

    /**
     * @param array<array{name: string, addresses?: array<string>}>|array<string> $addNameservers
     * @param array<string> $remNameservers
     * @param array<string> $addTech
     * @param array<string> $remTech
     * @return array{updated: bool, nssetId: string}
     */
    public function update(
        string $nssetId,
        array $addNameservers = [],
        array $remNameservers = [],
        array $addTech = [],
        array $remTech = [],
        ?string $authInfo = null,
    ): array {
        if (trim($nssetId) === '') {
            throw new InvalidArgumentException('nssetId is required');
        }

        $xml = $this->client->getXmlBuilder()->nssetUpdateCommand(
            $nssetId,
            $addNameservers,
            $remNameservers,
            $addTech,
            $remTech,
            $authInfo
        );
        $this->client->request($xml);

        return [
            'updated' => true,
            'nssetId' => $nssetId,
        ];
    }

    /**
     * @return array{deleted: bool, nssetId: string}
     */
    public function delete(string $nssetId): array
    {
        if (trim($nssetId) === '') {
            throw new InvalidArgumentException('nssetId is required');
        }

        $xml = $this->client->getXmlBuilder()->nssetDeleteCommand($nssetId);
        $this->client->request($xml);

        return [
            'deleted' => true,
            'nssetId' => $nssetId,
        ];
    }
}
