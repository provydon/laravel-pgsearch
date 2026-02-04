<?php

namespace Provydon\PgSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

class PgSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge defaults; publishable config below
        $this->mergeConfigFrom(__DIR__.'/../config/pgsearch.php', 'pgsearch');
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/pgsearch.php' => config_path('pgsearch.php'),
        ], 'pgsearch-config');

        $this->registerMacro();

        // Load helpers
        require_once __DIR__.'/helpers.php';
    }

    protected function registerMacro(): void
    {
        if (Builder::hasGlobalMacro('pgSearch')) {
            return;
        }

        Builder::macro('pgSearch', function (?string $term, array $columns, array $options = []) {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            $trimmedSearch = trim((string) $term);

            if ($trimmedSearch === '' || empty($columns)) {
                return $this;
            }

            if ($this->getConnection()->getDriverName() !== 'pgsql') {
                return $this;
            }

            $opts = array_merge(config('pgsearch', []), $options);
            $normalize = (bool) ($opts['normalize'] ?? true);
            $wordBasedMatching = (bool) ($opts['word_based_matching'] ?? true);
            $ignoreSuffixes = $opts['ignore_suffixes'] ?? ['state', 'province', 'region', 'territory', 'city', 'town', 'municipality'];

            $grammar = $this->getQuery()->getGrammar();
            $model = $this->getModel();

            $normalized = $normalize
                ? preg_replace('/[^a-zA-Z0-9]/', '', $trimmedSearch)
                : $trimmedSearch;

            // Extract words for token-based matching
            $words = preg_split('/\s+/', $trimmedSearch);
            $normalizedWords = array_map(function ($word) {
                return preg_replace('/[^a-zA-Z0-9]/', '', $word);
            }, array_filter($words, function ($word) {
                return strlen(trim($word)) > 0;
            }));

            // Filter out common suffixes if word-based matching is enabled
            $searchWords = $wordBasedMatching
                ? array_filter($normalizedWords, function ($word) use ($ignoreSuffixes) {
                    return ! in_array(strtolower($word), $ignoreSuffixes) && strlen($word) > 2;
                })
                : [];

            return $this->where(function ($q) use ($columns, $trimmedSearch, $normalized, $searchWords, $grammar, $model, $normalize, $wordBasedMatching) {
                foreach ($columns as $col) {
                    if (str_contains($col, '.')) {
                        // relation.column
                        [$relation, $relatedCol] = explode('.', $col, 2);

                        $q->orWhereHas($relation, function ($sub) use ($relatedCol, $trimmedSearch, $normalized, $searchWords, $grammar, $normalize, $wordBasedMatching) {
                            /** @var \Illuminate\Database\Eloquent\Builder $sub */
                            $relatedModel = $sub->getModel();
                            $qualified = $relatedModel->qualifyColumn($relatedCol); // table.column
                            $wrapped = $grammar->wrap($qualified);

                            $sub->whereRaw("CAST($wrapped AS TEXT) ILIKE ?", ['%'.$trimmedSearch.'%']);

                            if ($normalize) {
                                $sub->orWhereRaw("REGEXP_REPLACE(CAST($wrapped AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?", ['%'.$normalized.'%']);
                                if ($wordBasedMatching && ! empty($searchWords)) {
                                    $sub->orWhere(function ($wordQuery) use ($wrapped, $searchWords) {
                                        foreach ($searchWords as $word) {
                                            $wordQuery->orWhereRaw(
                                                "REGEXP_REPLACE(CAST($wrapped AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?",
                                                ['%'.$word.'%']
                                            );
                                        }
                                    });
                                }
                            }
                        });
                    } else {
                        $qualified = $model->qualifyColumn($col);
                        $wrapped = $grammar->wrap($qualified);

                        $q->orWhereRaw("CAST($wrapped AS TEXT) ILIKE ?", ['%'.$trimmedSearch.'%']);

                        if ($normalize) {
                            $q->orWhereRaw("REGEXP_REPLACE(CAST($wrapped AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?", ['%'.$normalized.'%']);
                            if ($wordBasedMatching && ! empty($searchWords)) {
                                $q->orWhere(function ($wordQuery) use ($wrapped, $searchWords) {
                                    foreach ($searchWords as $word) {
                                        $wordQuery->orWhereRaw(
                                            "REGEXP_REPLACE(CAST($wrapped AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?",
                                            ['%'.$word.'%']
                                        );
                                    }
                                });
                            }
                        }
                    }
                }
            });
        });
    }
}
