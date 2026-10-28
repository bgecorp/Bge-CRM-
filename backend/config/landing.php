<?php

/*
 * What the public landing page sells. Prices are per bundle in pesos; null means
 * the price is confirmed by phone when the order is called in. Images live in
 * frontend/public/landing/.
 */
return [
    'offers' => [
        'barley-powder' => [
            'name' => 'Pure Barley Powder',
            'short' => 'Barley Powder',
            'size' => '50 g per pack',
            'deal' => '10 packs minimum order',
            'packs' => 10,
            'price' => 680,
            'image' => '/landing/barley-powder.webp',
        ],
        'corn-coffee' => [
            'name' => 'Malunggay Mangosteen Corn Coffee',
            'short' => 'Corn Coffee',
            'size' => '100 g per pack',
            'deal' => '20 packs minimum order',
            'packs' => 20,
            'price' => 1360,
            'image' => '/landing/corn-coffee.webp',
        ],
        'barley-capsules' => [
            'name' => 'Pure Barley Capsules',
            'size' => '100 capsules (500 mg) per bottle',
            'deal' => null,
            'packs' => 1,
            'price' => null,
            'image' => '/landing/barley-capsules.webp',
        ],
    ],

    // Most minimum orders (bundles) of one product in a single web order.
    'max_quantity' => 20,
];
