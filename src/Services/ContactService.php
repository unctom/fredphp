<?php

namespace AfricoreDev\FredPhp\Services;

use AfricoreDev\FredPhp\Epp\EppClient;
use AfricoreDev\FredPhp\Exceptions\EppException;
use InvalidArgumentException;

class ContactService
{
    public function __construct(
        protected EppClient $client,
    ) {
    }

    /**
     * @param string|array<string> $contactIds
     * @return array<string, array{contactId: string, available: bool, reason: ?string}>|array{contactId: string, available: bool, reason: ?string}
     */
    public function check(string|array $contactIds): array
    {
        $isSingle = is_string($contactIds);
        $ids = is_array($contactIds) ? $contactIds : [$contactIds];

        $xml = $this->client->getXmlBuilder()->contactCheckCommand($ids);
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
                'contactId' => (string) $idVal,
                'available' => $available,
                'reason' => $reason,
            ];
        }

        if ($isSingle) {
            return $results[$ids[0]] ?? [
                'contactId' => $ids[0],
                'available' => false,
                'reason' => null,
            ];
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function info(string $contactId, ?string $authInfo = null): array
    {
        if (trim($contactId) === '') {
            throw new InvalidArgumentException('contactId is required');
        }

        $xml = $this->client->getXmlBuilder()->contactInfoCommand($contactId, $authInfo);
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

        return [
            'contactId' => $contactId,
            'status' => $statuses,
            'postalInfo' => $infData['postalInfo'] ?? null,
            'voice' => $infData['voice'] ?? null,
            'fax' => $infData['fax'] ?? null,
            'email' => $infData['email'] ?? null,
            'notifyEmail' => $infData['notifyEmail'] ?? null,
            'vat' => $infData['vat'] ?? null,
            'ident' => $infData['ident'] ?? null,
            'crID' => $infData['crID'] ?? null,
            'crDate' => $infData['crDate'] ?? null,
            'upID' => $infData['upID'] ?? null,
            'upDate' => $infData['upDate'] ?? null,
            'clID' => $infData['clID'] ?? null,
            'raw' => $infData,
        ];
    }

    /**
     * @param array<string, mixed> $contactData
     * @return array{created: bool, contactId: string, crDate: ?string}
     */
    public function create(array $contactData): array
    {
        $id = $contactData['id'] ?? $contactData['contactId'] ?? ('CNT-'.strtoupper(bin2hex(random_bytes(4))));
        $name = $contactData['name'] ?? null;
        $email = $contactData['email'] ?? null;
        $street = $contactData['street'] ?? $contactData['address'] ?? null;
        $city = $contactData['city'] ?? null;
        $pc = $contactData['pc'] ?? $contactData['postalCode'] ?? $contactData['postcode'] ?? null;
        $cc = $contactData['cc'] ?? $contactData['country'] ?? 'TZ';
        $sp = $contactData['sp'] ?? $contactData['state'] ?? $contactData['province'] ?? null;
        $org = $contactData['org'] ?? $contactData['organization'] ?? null;
        $voice = $contactData['voice'] ?? $contactData['phone'] ?? null;

        if (! $name || ! $email || ! $street || ! $city || ! $pc) {
            throw new InvalidArgumentException(
                'Missing required contact fields: name, email, street (or address), city, pc (or postalCode)'
            );
        }

        $payload = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'street' => $street,
            'city' => $city,
            'pc' => (string) $pc,
            'cc' => (string) $cc,
            'sp' => $sp,
            'org' => $org,
            'voice' => $voice,
            'fax' => $contactData['fax'] ?? null,
            'notifyEmail' => $contactData['notifyEmail'] ?? null,
            'vat' => $contactData['vat'] ?? null,
            'ident' => $contactData['ident'] ?? null,
            'authInfo' => $contactData['authInfo'] ?? null,
            'disclose' => $contactData['disclose'] ?? null,
        ];

        $xml = $this->client->getXmlBuilder()->contactCreateCommand($payload);
        $parsed = $this->client->request($xml);

        $creData = $parsed['epp']['response']['resData']['creData'] ?? [];
        $actualId = is_array($creData['id'] ?? null) ? ($creData['id']['@value'] ?? $id) : ($creData['id'] ?? $id);
        $crDate = is_array($creData['crDate'] ?? null) ? ($creData['crDate']['@value'] ?? null) : ($creData['crDate'] ?? null);

        return [
            'created' => true,
            'contactId' => (string) $actualId,
            'crDate' => $crDate,
        ];
    }

    /**
     * @param array<string, mixed> $chg
     * @param array<string>|null $disclose
     * @return array{updated: bool, contactId: string}
     */
    public function update(string $contactId, array $chg = [], ?array $disclose = null): array
    {
        if (trim($contactId) === '') {
            throw new InvalidArgumentException('contactId is required');
        }

        $xml = $this->client->getXmlBuilder()->contactUpdateCommand($contactId, $chg, $disclose);
        $this->client->request($xml);

        return [
            'updated' => true,
            'contactId' => $contactId,
        ];
    }

    /**
     * @return array{deleted: bool, contactId: string}
     */
    public function delete(string $contactId): array
    {
        if (trim($contactId) === '') {
            throw new InvalidArgumentException('contactId is required');
        }

        $xml = $this->client->getXmlBuilder()->contactDeleteCommand($contactId);
        $this->client->request($xml);

        return [
            'deleted' => true,
            'contactId' => $contactId,
        ];
    }
}
