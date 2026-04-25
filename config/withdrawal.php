<?php

return [
//    'scanner' => [ //take from config/blockchain.php
//        'default_scan_interval_seconds' => 30,
//        'networks' => [
//            'ethereum' => [
//                'scan_interval_seconds' => 6,
//            ],
//            'tron' => [
//                'scan_interval_seconds' => 5,
//            ],
//            'bitcoin' => [
//                'scan_interval_seconds' => 30,
//            ],
//        ],
//    ],
    'tron' => [
        // Максимальный лимит комиссии для токенов (TRC-20) в Sun (1 TRX = 1,000,000 Sun)
        // По умолчанию: 15 TRX (с запасом, чтобы транзакция не провалилась из-за нехватки энергии)
        'token_fee_limit_sun' => env('TRON_TOKEN_FEE_LIMIT_SUN', 15_000_000),

        // Лимит для обычных переводов TRX (если нужен отдельный, обычно меньше)
        'trx_fee_limit_sun' => env('TRON_TRX_FEE_LIMIT_SUN', 1_000_000), // 1 TRX
    ],

    'ethereum' => [
        // Лимит газа для перевода токенов ERC-20 (например, USDT)
        // Стандартный transfer() у USDT занимает около 45 000 - 65 000 газа.
        // Ставим с запасом, чтобы точно хватило.
        'token_gas_limit' => (int) env('ETH_TOKEN_GAS_LIMIT', 100_000),

        // Лимит газа для обычного перевода ETH (всегда ровно 21 000)
        'eth_gas_limit' => (int) env('ETH_ETH_GAS_LIMIT', 21_000),

        // Стандартные настройки цены газа (цена за единицу газа в Gwei).
        // Система может использовать это как приоритет по умолчанию.
        'max_priority_fee_gwei' => (float) env('ETH_MAX_PRIORITY_FEE', 1.5),
        'max_fee_per_gas_gwei' => (float) env('ETH_MAX_FEE', 50),
    ],

    'bitcoin' => [
        // Комиссия майнерам в сатоши за байт (sat/vByte).
        // Bitcoin не имеет газа, как смарт-контракты. Тут платят за размер транзакции.
        // Значение зависит от текущей загруженности сети (mempool).
        'fee_rate_sat_per_byte' => (int) env('BTC_FEE_RATE', 10), // 10 sat/vB (экономичный режим)

        // Фиксированная минимальная комиссия (независимо от размера), если не используется расчет по байтам.
        // Часто используется как fallback или для простоты.
        'min_fee_satoshi' => (int) env('BTC_MIN_FEE_SATOSHI', 1000), // 0.00001000 BTC

        // Пыль (dust limit). Минимальная сумма вывода, которую сеть считает экономически целесообразной.
        // Обычно ~546 сатоши для стандартных Bitcoin-адресов.
        'dust_limit_satoshi' => (int) env('BTC_DUST_LIMIT', 546),
    ],

    'recovery' => [
        'stale_reserved_minutes' => 15,
        'stale_broadcasted_minutes' => 10,
        'stale_settled_minutes' => 10,
    ],
];
