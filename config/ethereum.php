<?php

return [
    // Enable or disable Ethereum integration. Controlled via env `APP_ETH_ENABLED`.
    'enabled' => filter_var(env('APP_ETH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // JSON-RPC provider URL (Alchemy, Infura, QuickNode, or your node)
    'provider_url' => env('ETH_PROVIDER_URL', null),

    // Chain id (1 = mainnet). Used for signing / tx building when needed.
    'chain_id' => env('ETH_CHAIN_ID', env('ETH_CHAIN', null)),

    // Optional custodial private key (DO NOT store plain keys in repo; prefer KMS).
    'custodial_private_key' => env('ETH_CUSTODIAL_PRIVATE_KEY', null),
];
