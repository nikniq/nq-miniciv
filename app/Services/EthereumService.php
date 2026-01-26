<?php

namespace App\Services;

use GuzzleHttp\Client;

class EthereumService
{
    protected Client $client;
    protected ?string $rpcUrl;

    public function __construct(?string $rpcUrl = null)
    {
        $this->rpcUrl = $rpcUrl ?: config('ethereum.provider_url', env('ETH_PROVIDER_URL'));
        $this->client = new Client(['timeout' => 10]);
    }

    protected function rpcRequest(string $method, array $params = [])
    {
        if (! $this->rpcUrl) {
            return null;
        }

        $res = $this->client->post($this->rpcUrl, [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $params,
            ],
        ]);

        $body = json_decode((string) $res->getBody(), true);
        return $body['result'] ?? null;
    }

    protected function hexToDec(string $hex): string
    {
        $hex = preg_replace('/^0x/i', '', $hex);
        if ($hex === '') return '0';
        $dec = '0';
        $len = strlen($hex);
        for ($i = 0; $i < $len; $i++) {
            $digit = hexdec($hex[$i]);
            $dec = bcadd(bcmul($dec, '16'), (string) $digit);
        }
        return $dec;
    }

    protected function weiToEth(string $weiHex): string
    {
        $dec = $this->hexToDec($weiHex);
        if ($dec === '0') return '0';
        // divide by 1e18 with 18 decimal places
        return rtrim(rtrim(number_format((float) bcdiv($dec, '1000000000000000000', 18), 18, '.', ''), '0'), '.');
    }

    /**
     * Get last N transactions by scanning recent blocks backwards.
     * This is a lightweight approach for an admin overview; it's not intended
     * to be a full indexer.
     *
     * @param int $limit
     * @return array
     */
    public function getLatestTransactions(int $limit = 10): array
    {
        if (! $this->rpcUrl) return [];

        try {
            $blockHex = $this->rpcRequest('eth_blockNumber');
            if (! $blockHex) return [];
            $blockNum = hexdec($blockHex);

            $txs = [];
            $bn = $blockNum;
            while ($bn >= 0 && count($txs) < $limit) {
                $hex = '0x' . dechex($bn);
                $block = $this->rpcRequest('eth_getBlockByNumber', [$hex, true]);
                if (! $block) { $bn--; continue; }

                $timestamp = isset($block['timestamp']) ? hexdec($block['timestamp']) : null;
                $blockNumberDec = isset($block['number']) ? hexdec($block['number']) : $bn;

                if (! empty($block['transactions']) && is_array($block['transactions'])) {
                    foreach ($block['transactions'] as $tx) {
                        $txs[] = [
                            'hash' => $tx['hash'] ?? null,
                            'from' => $tx['from'] ?? null,
                            'to' => $tx['to'] ?? null,
                            'value' => $tx['value'] ?? '0x0',
                            'value_eth' => $this->weiToEth($tx['value'] ?? '0x0'),
                            'block_number' => $blockNumberDec,
                            'timestamp' => $timestamp ? date('c', $timestamp) : null,
                        ];

                        if (count($txs) >= $limit) break 2;
                    }
                }

                $bn--;
            }

            return $txs;
        } catch (\Exception $e) {
            return [];
        }
    }
}
