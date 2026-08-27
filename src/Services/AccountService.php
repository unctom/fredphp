<?php

namespace AfricoreDev\FredPhp\Services;

use AfricoreDev\FredPhp\Epp\EppClient;

class AccountService
{
    public function __construct(
        protected EppClient $client,
    ) {
    }

    /**
     * @return array{
     *     balance: array<string, array{available: float, currency: string}>,
     *     raw: array<string, mixed>,
     * }
     */
    public function getCreditInfo(): array
    {
        $xml = $this->client->getXmlBuilder()->creditInfoCommand();
        $parsed = $this->client->request($xml);

        $resCreditInfo = $parsed['epp']['response']['resData']['resCreditInfo']
            ?? ($parsed['epp']['response']['extension']['resCreditInfo'] ?? []);

        $balance = [];
        if (isset($resCreditInfo['zoneCredit'])) {
            $zones = is_array($resCreditInfo['zoneCredit']) && ! isset($resCreditInfo['zoneCredit']['zone'])
                ? $resCreditInfo['zoneCredit']
                : [$resCreditInfo['zoneCredit']];

            foreach ($zones as $zoneData) {
                if (isset($zoneData['zone']) && isset($zoneData['credit'])) {
                    $zone = is_array($zoneData['zone']) ? ($zoneData['zone']['@value'] ?? '') : (string) $zoneData['zone'];
                    $credit = is_array($zoneData['credit']) ? ($zoneData['credit']['@value'] ?? '0') : (string) $zoneData['credit'];

                    $balance[$zone] = [
                        'available' => (float) $credit,
                        'currency' => 'TZS',
                    ];
                }
            }
        }

        if (empty($balance)) {
            $balance['TZ'] = [
                'available' => 0.0,
                'currency' => 'TZS',
            ];
        }

        return [
            'balance' => $balance,
            'raw' => $resCreditInfo,
        ];
    }

    /**
     * Alias for getCreditInfo()
     *
     * @return array{
     *     balance: array<string, array{available: float, currency: string}>,
     *     raw: array<string, mixed>,
     * }
     */
    public function getBalance(): array
    {
        return $this->getCreditInfo();
    }
}
