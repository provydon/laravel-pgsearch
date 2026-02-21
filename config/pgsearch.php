<?php

return [
    // When true, also compare with punctuation stripped (helps phone numbers, IDs, etc.)
    'normalize' => true,

    // Order by relevance (exact 100, normalized 50, word 10). Chain orderBy after pgSearch for custom sort.
    'order_by_best_match' => true,

    // When true, split the search term into tokens (words) and
    // also search by each significant word individually. This helps
    // cases like searching for "Lagos State" when the DB only contains "Lagos".
    'word_based_matching' => true,

    // When using word-based matching, these suffixes are ignored when they
    // appear as standalone words in the search term (case-insensitive).
    // Helpful for geographic names: "Lagos State" will effectively search
    // for "Lagos" as a token.
    'ignore_suffixes' => [
        'state',
        'province',
        'region',
        'territory',
        'city',
        'town',
        'municipality',
    ],

    // Placeholders for future engines:
    // 'engine' => 'ilike', // ilike|fts|trigram
    // 'language' => 'simple', // for FTS
    // 'min_similarity' => 0.3, // for trigram
];
