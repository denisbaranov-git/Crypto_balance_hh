<?php
// app/Services/TokenConfigService.php

namespace App\Services;

class TokenConfigService
{
    public function getTokenConfig(string $symbol): ?array
    {
        return config("tokens.{$symbol}");
    }

    public function getNetworkConfig(string $network): ?array
    {
        return config("networks.{$network}");
    }

    public function getTokenNetworkConfig(string $symbol, string $network): ?array
    {
        $token = $this->getTokenConfig($symbol);
        if (!$token) return null;

        $networkConfig = $this->getNetworkConfig($network);
        if (!$networkConfig) return null;

        $tokenNetwork = $token['networks'][$network] ?? null;
        if (!$tokenNetwork) return null;

        return array_merge($networkConfig, $tokenNetwork, [
            'symbol' => $symbol,
            'network' => $network,
            'name' => $token['name'],
        ]);
    }

    public function validateTokenNetwork(string $symbol, string $network): bool
    {
        return $this->getTokenNetworkConfig($symbol, $network) !== null;
    }
}
