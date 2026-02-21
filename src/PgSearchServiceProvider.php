<?php

namespace Provydon\PgSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

class PgSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pgsearch.php', 'pgsearch');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/pgsearch.php' => config_path('pgsearch.php'),
        ], 'pgsearch-config');

        $this->registerMacro();
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
            if ($trimmedSearch === '' || empty($columns) || $this->getConnection()->getDriverName() !== 'pgsql') {
                return $this;
            }

            $opts = array_merge(config('pgsearch', []), $options);
            $normalize = (bool) ($opts['normalize'] ?? true);
            $wordBasedMatching = (bool) ($opts['word_based_matching'] ?? true);
            $orderByBestMatch = (bool) ($opts['order_by_best_match'] ?? true);
            $ignoreSuffixes = $opts['ignore_suffixes'] ?? ['state', 'province', 'region', 'territory', 'city', 'town', 'municipality'];

            $grammar = $this->getQuery()->getGrammar();
            $model = $this->getModel();
            $normalized = $normalize ? preg_replace('/[^a-zA-Z0-9]/', '', $trimmedSearch) : $trimmedSearch;

            // Tokenize search term; filter suffixes (e.g. "state") and short words
            $normalizedWords = array_map(fn ($w) => preg_replace('/[^a-zA-Z0-9]/', '', $w),
                array_filter(preg_split('/\s+/', $trimmedSearch), fn ($w) => strlen(trim($w)) > 0));
            $searchWords = $wordBasedMatching
                ? array_filter($normalizedWords, fn ($w) => ! in_array(strtolower($w), $ignoreSuffixes) && strlen($w) > 2)
                : [];

            // Apply WHERE: ILIKE on raw + normalized + word tokens per column
            $addColumnConditions = function ($q, $wrapped) use ($trimmedSearch, $normalized, $searchWords, $normalize, $wordBasedMatching) {
                $q->whereRaw("CAST($wrapped AS TEXT) ILIKE ?", ['%'.$trimmedSearch.'%']);
                if ($normalize) {
                    $q->orWhereRaw("REGEXP_REPLACE(CAST($wrapped AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?", ['%'.$normalized.'%']);
                    if ($wordBasedMatching && ! empty($searchWords)) {
                        $q->orWhere(function ($sub) use ($wrapped, $searchWords) {
                            foreach ($searchWords as $word) {
                                $sub->orWhereRaw("REGEXP_REPLACE(CAST($wrapped AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?", ['%'.$word.'%']);
                            }
                        });
                    }
                }
            };

            $builder = $this->where(function ($q) use ($columns, $grammar, $model, $addColumnConditions) {
                foreach ($columns as $col) {
                    if (str_contains($col, '.')) {
                        [$relation, $relatedCol] = explode('.', $col, 2);
                        $q->orWhereHas($relation, function ($sub) use ($relatedCol, $grammar, $addColumnConditions) {
                            $wrapped = $grammar->wrap($sub->getModel()->qualifyColumn($relatedCol));
                            $addColumnConditions($sub, $wrapped);
                        });
                    } else {
                        $wrapped = $grammar->wrap($model->qualifyColumn($col));
                        $q->orWhere(function ($sub) use ($wrapped, $addColumnConditions) {
                            $addColumnConditions($sub, $wrapped);
                        });
                    }
                }
            });

            // Relevance scoring: exact phrase 100, normalized 50, each word 10. Chain orderBy after pgSearch for custom sort.
            if ($orderByBestMatch) {
                $simpleColumns = array_filter($columns, fn ($c) => ! str_contains($c, '.'));
                if (! empty($simpleColumns)) {
                    $scoreParts = [];
                    $bindings = [];
                    foreach ($simpleColumns as $col) {
                        $wrapped = $grammar->wrap($model->qualifyColumn($col));
                        $repl = "REGEXP_REPLACE(CAST($wrapped AS TEXT), '[^a-zA-Z0-9]', '', 'g')";
                        $part = "(CASE WHEN CAST($wrapped AS TEXT) ILIKE ? THEN 100 ELSE 0 END";
                        $bindings[] = '%'.$trimmedSearch.'%';
                        if ($normalize) {
                            $part .= " + CASE WHEN $repl ILIKE ? THEN 50 ELSE 0 END";
                            $bindings[] = '%'.$normalized.'%';
                        }
                        foreach ($searchWords as $word) {
                            $part .= " + CASE WHEN $repl ILIKE ? THEN 10 ELSE 0 END";
                            $bindings[] = '%'.$word.'%';
                        }
                        $scoreParts[] = $part.')';
                    }
                    $builder->orderByRaw('GREATEST('.implode(', ', $scoreParts).') DESC', $bindings);
                }
            }

            return $builder;
        });
    }
}
