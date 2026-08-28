<?php

namespace AfricoreDev\FredPhp\Xml;

use AfricoreDev\FredPhp\Exceptions\EppCommandException;
use AfricoreDev\FredPhp\Exceptions\EppException;
use SimpleXMLElement;

final class EppXmlParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $xml): array
    {
        if (trim($xml) === '') {
            return [];
        }

        // Clean namespaces from tag names to make array traversal clean and predictable
        $cleanXml = preg_replace('/(<\/?)[\w\-]+:/', '$1', $xml);
        $cleanXml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', (string) $cleanXml);

        if ($cleanXml === null) {
            throw new EppException('Failed to normalize EPP XML.');
        }

        libxml_use_internal_errors(true);

        try {
            $xmlElement = simplexml_load_string(
                $cleanXml,
                SimpleXMLElement::class,
                LIBXML_NOCDATA
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors(false);
        }

        if ($xmlElement === false) {
            throw new EppException('Failed to parse EPP XML response.');
        }

        return [
            $xmlElement->getName() => $this->xmlToArray($xmlElement),
        ];
    }

    /**
     * @param array<string, mixed> $parsed
     */
    public function getResultCode(array $parsed): ?int
    {
        $code = $parsed['epp']['response']['result']['@attributes']['code'] ?? null;
        if ($code !== null) {
            return (int) $code;
        }

        if (isset($parsed['epp']['response']['result'][0]['@attributes']['code'])) {
            return (int) $parsed['epp']['response']['result'][0]['@attributes']['code'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $parsed
     */
    public function getResultMessage(array $parsed): ?string
    {
        $msg = $parsed['epp']['response']['result']['msg'] ?? null;
        if ($msg === null && isset($parsed['epp']['response']['result'][0]['msg'])) {
            $msg = $parsed['epp']['response']['result'][0]['msg'];
        }

        if (is_array($msg)) {
            return (string) ($msg['@value'] ?? $msg[0] ?? 'Unknown EPP result');
        }

        return is_string($msg) ? $msg : null;
    }

    /**
     * @param array<string, mixed> $parsed
     */
    public function getClTRID(array $parsed): ?string
    {
        $trID = $parsed['epp']['response']['trID'] ?? null;
        if (! is_array($trID)) {
            return null;
        }

        $clTRID = $trID['clTRID'] ?? null;

        return is_string($clTRID) ? $clTRID : (is_array($clTRID) ? ($clTRID['@value'] ?? null) : null);
    }

    /**
     * @param array<string, mixed> $parsed
     */
    public function getSvTRID(array $parsed): ?string
    {
        $trID = $parsed['epp']['response']['trID'] ?? null;
        if (! is_array($trID)) {
            return null;
        }

        $svTRID = $trID['svTRID'] ?? null;

        return is_string($svTRID) ? $svTRID : (is_array($svTRID) ? ($svTRID['@value'] ?? null) : null);
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array<string, mixed>|null
     */
    public function getResData(array $parsed): ?array
    {
        $resData = $parsed['epp']['response']['resData'] ?? null;

        return is_array($resData) ? $resData : null;
    }

    /**
     * @param array<string, mixed> $parsed
     */
    public function isSuccess(array $parsed): bool
    {
        $code = $this->getResultCode($parsed);

        return $code !== null && ($code >= 1000 && $code < 2000);
    }

    /**
     * @param array<string, mixed> $parsed
     * @throws EppCommandException
     */
    public function throwIfError(array $parsed): void
    {
        $code = $this->getResultCode($parsed);
        if ($code === null) {
            throw new EppCommandException('EPP response did not contain a result code.', 0, null, $parsed);
        }

        if (! $this->isSuccess($parsed)) {
            $message = $this->getResultMessage($parsed) ?? "EPP command failed with code {$code}";
            throw new EppCommandException("EPP Error [{$code}]: {$message}", $code, $message, $parsed);
        }
    }

    /**
     * Extracts supported services (objURI and extURI) from a parsed EPP greeting.
     *
     * @param array<string, mixed> $parsed
     * @return array{
     *     version?: string,
     *     lang?: string,
     *     objURIs: array<string>,
     *     extURIs: array<string>,
     * }
     */
    public function getGreetingServices(array $parsed): array
    {
        $svcMenu = $parsed['epp']['greeting']['svcMenu'] ?? [];
        if (! is_array($svcMenu)) {
            return [
                'objURIs' => [],
                'extURIs' => [],
            ];
        }

        $objURIs = [];
        if (isset($svcMenu['objURI'])) {
            $objs = is_array($svcMenu['objURI']) && ! isset($svcMenu['objURI']['@value'])
                ? $svcMenu['objURI']
                : [$svcMenu['objURI']];
            foreach ($objs as $obj) {
                $val = is_array($obj) ? ($obj['@value'] ?? null) : $obj;
                if (is_string($val) && $val !== '') {
                    $objURIs[] = $val;
                }
            }
        }

        $extURIs = [];
        $svcExtension = $svcMenu['svcExtension'] ?? null;
        if (is_array($svcExtension) && isset($svcExtension['extURI'])) {
            $exts = is_array($svcExtension['extURI']) && ! isset($svcExtension['extURI']['@value'])
                ? $svcExtension['extURI']
                : [$svcExtension['extURI']];
            foreach ($exts as $ext) {
                $val = is_array($ext) ? ($ext['@value'] ?? null) : $ext;
                if (is_string($val) && $val !== '') {
                    $extURIs[] = $val;
                }
            }
        }

        $version = $svcMenu['version'] ?? '1.0';
        if (is_array($version)) {
            $version = (string) ($version['@value'] ?? $version[0] ?? '1.0');
        }

        $lang = $svcMenu['lang'] ?? 'en';
        if (is_array($lang)) {
            $lang = (string) ($lang['@value'] ?? $lang[0] ?? 'en');
        }

        return [
            'version' => (string) $version,
            'lang' => (string) $lang,
            'objURIs' => $objURIs,
            'extURIs' => $extURIs,
        ];
    }

    /**
     * @return array<int|string, mixed>|string
     */
    protected function xmlToArray(
        SimpleXMLElement $xmlElement
    ): array|string {
        $array = [];

        foreach ($xmlElement->attributes() as $key => $value) {
            $array['@attributes'][$key] = (string) $value;
        }

        $text = trim((string) $xmlElement);

        if ($text !== '') {
            $array['@value'] = $text;
        }

        foreach ($xmlElement->children() as $key => $child) {
            $childArray = $this->xmlToArray($child);

            if (isset($array[$key])) {
                if (
                    ! is_array($array[$key])
                    || ! isset($array[$key][0])
                ) {
                    $array[$key] = [$array[$key]];
                }

                $array[$key][] = $childArray;
            } else {
                $array[$key] = $childArray;
            }
        }

        if (
            count($array) === 1
            && isset($array['@value'])
        ) {
            return $array['@value'];
        }

        return $array;
    }
}