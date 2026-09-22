<?php

/**
 * @file plugins/paymethod/paystack/classes/PaystackClient.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackClient
 *
 * @brief Thin Guzzle wrapper around the Paystack REST API.
 */

namespace APP\plugins\paymethod\paystack\classes;

use Exception;
use GuzzleHttp\Client;

class PaystackClient
{
    public const API_URL = 'https://api.paystack.co';

    /** @var string */
    private $secretKey;

    /** @var Client */
    private $http;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
        $this->http = new Client([
            'base_uri' => self::API_URL,
            'timeout' => 30,
            'http_errors' => false,
            'verify' => true,
        ]);
    }

    /**
     * Convert a major-unit amount into Paystack's subunit integer.
     */
    public static function toSubunit(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert a Paystack subunit integer into a major-unit amount.
     */
    public static function fromSubunit($amount): float
    {
        return ((int) $amount) / 100;
    }

    public function initializeTransaction(array $payload): array
    {
        return $this->request('POST', '/transaction/initialize', $payload);
    }

    public function verifyTransaction(string $reference): array
    {
        return $this->request('GET', '/transaction/verify/' . rawurlencode($reference));
    }

    public function refund(string $reference, ?int $amountSubunit = null): array
    {
        $payload = ['transaction' => $reference];
        if ($amountSubunit !== null) {
            $payload['amount'] = $amountSubunit;
        }
        return $this->request('POST', '/refund', $payload);
    }

    /**
     * @throws Exception
     */
    private function request(string $method, string $path, ?array $json = null): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }

        $response = $this->http->request($method, $path, $options);
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new Exception('Paystack returned a non-JSON response (HTTP ' . $status . '). Body: ' . substr($body, 0, 300));
        }
        if (empty($data['status'])) {
            $message = $data['message'] ?? ('Paystack request failed (HTTP ' . $status . ').');
            throw new Exception($message);
        }
        return $data;
    }
}
