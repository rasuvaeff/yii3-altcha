<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-altcha' => [
        // HMAC secret signing every challenge: at least 32 bytes, from the environment, never committed
        'secret' => '',
        // PBKDF2/SHA-256, PBKDF2/SHA-384, PBKDF2/SHA-512, SHA-256, SHA-384, SHA-512
        'algorithm' => 'PBKDF2/SHA-256',
        // iterations per attempt in the browser (verification repeats it once)
        'cost' => 5000,
        // upper bound of the counter the browser searches: the average solve is half of it
        'maxCounter' => 1000,
        // lifetime of a challenge, hence of a solved payload
        'ttlSeconds' => 600,
        // accept a solved payload only once; needs a Psr\SimpleCache\CacheInterface in the container
        'replayProtection' => true,
        'translation.category' => 'yii3-altcha',
    ],
];
