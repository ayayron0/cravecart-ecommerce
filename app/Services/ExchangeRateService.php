<?php

declare(strict_types=1);

namespace App\Services;

/*
 * ExchangeRateService - fetches external exchange-rate data.
 *
 * WHAT: Gets the current CAD -> USD exchange rate from Frankfurter.
 * HOW:  Calls the public API and caches the result in session for 1 hour
 *       so the app does not make unnecessary requests on every page load.
 */
class ExchangeRateService
{
    private const CACHE_KEY = 'exchange_rate_cad_usd';
    private const CACHE_TTL = 3600; // 1 hour

    public function getCadToUsdRate(): ?float
    {
        if (
            isset($_SESSION[self::CACHE_KEY]['rate'], $_SESSION[self::CACHE_KEY]['timestamp']) &&
            (time() - (int) $_SESSION[self::CACHE_KEY]['timestamp']) < self::CACHE_TTL
        ) {
            return (float) $_SESSION[self::CACHE_KEY]['rate'];
        }

        $url = 'https://api.frankfurter.dev/v2/rate/CAD/USD';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $json = curl_exec($ch);

        if ($json === false) {
            return null;
}

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);


        if ($statusCode !== 200) {
            return null;
        }

        $data = json_decode($json, true);

        if (!is_array($data) || !isset($data['rate'])) {
            return null;
        }

        $rate = (float) $data['rate'];

        if ($rate <= 0) {
            return null;
        }

        $_SESSION[self::CACHE_KEY] = [
            'rate' => $rate,
            'timestamp' => time(),
        ];

        return $rate;
    }

    public function convertCadToUsd(float $cadAmount): ?float
    {
        $rate = $this->getCadToUsdRate();

        if ($rate === null) {
            return null;
        }

        return round($cadAmount * $rate, 2);
    }
}
