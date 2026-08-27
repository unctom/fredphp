<?php

namespace AfricoreDev\FredPhp\Services;

use AfricoreDev\FredPhp\Epp\EppClient;
use InvalidArgumentException;

class PollService
{
    public function __construct(
        protected EppClient $client,
    ) {
    }

    /**
     * @return array{
     *     hasMessages: bool,
     *     msgId?: string|null,
     *     count?: int|null,
     *     qDate?: string|null,
     *     msg?: string|null,
     *     raw?: array<string, mixed>,
     * }
     */
    public function request(): array
    {
        $xml = $this->client->getXmlBuilder()->pollRequestCommand();
        $parsed = $this->client->request($xml);

        $code = $this->client->getXmlParser()->getResultCode($parsed);

        if ($code === 1300) {
            return [
                'hasMessages' => false,
                'msgId' => null,
                'count' => 0,
            ];
        }

        $msgData = $parsed['epp']['response']['msgQ'] ?? [];
        $msgId = $msgData['@attributes']['id'] ?? null;
        $count = isset($msgData['@attributes']['count']) ? (int) $msgData['@attributes']['count'] : 0;
        $qDate = $msgData['qDate'] ?? null;
        $msg = is_array($msgData['msg'] ?? null) ? ($msgData['msg']['@value'] ?? '') : ($msgData['msg'] ?? null);

        return [
            'hasMessages' => true,
            'msgId' => $msgId,
            'count' => $count,
            'qDate' => is_array($qDate) ? ($qDate['@value'] ?? null) : $qDate,
            'msg' => $msg,
            'raw' => $parsed,
        ];
    }

    /**
     * @return array{acknowledged: bool, msgId: string, count?: int|null}
     */
    public function ack(string $msgId): array
    {
        if (trim($msgId) === '') {
            throw new InvalidArgumentException('msgId is required');
        }

        $xml = $this->client->getXmlBuilder()->pollAckCommand($msgId);
        $parsed = $this->client->request($xml);

        $msgData = $parsed['epp']['response']['msgQ'] ?? [];
        $count = isset($msgData['@attributes']['count']) ? (int) $msgData['@attributes']['count'] : null;

        return [
            'acknowledged' => true,
            'msgId' => $msgId,
            'count' => $count,
        ];
    }
}
