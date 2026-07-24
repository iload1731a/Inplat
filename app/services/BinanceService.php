<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class BinanceService
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?: 'https://api.binance.com', '/');
    }

    public function exchangeInfo(): array
    {
        $data = $this->get('/api/v3/exchangeInfo');
        return is_array($data['symbols'] ?? null) ? $data['symbols'] : [];
    }

    public function ticker24h(?string $symbol = null): array
    {
        $query = [];
        if ($symbol !== null && $symbol !== '') {
            $query['symbol'] = strtoupper($symbol);
        }
        $data = $this->get('/api/v3/ticker/24hr', $query);
        if (isset($data[0]) && is_array($data[0])) {
            return $data;
        }
        return is_array($data) ? [$data] : [];
    }

    public function klines(string $symbol, string $interval, int $limit = 200): array
    {
        $data = $this->get('/api/v3/klines', [
            'symbol' => strtoupper($symbol),
            'interval' => $interval,
            'limit' => max(1, min(1000, $limit)),
        ]);
        return is_array($data) ? $data : [];
    }

    private function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize HTTP client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'InplatMarketSync/1.0',
        ]);

        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Binance request failed: ' . $error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Binance returned non-JSON response.');
        }

        if ($status >= 400) {
            $message = (string)($decoded['msg'] ?? 'HTTP ' . $status);
            throw new RuntimeException('Binance API error: ' . $message, $status);
        }

        return $decoded;
    }
}
