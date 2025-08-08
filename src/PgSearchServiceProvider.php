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

            // Only act when using Postgres; otherwise no-op (or implement LIKE fallback if you want)
            if ($this->getConnection()->getDriverName() !== 'pgsql') {
                return $this;
            }

            $opts = array_merge(config('pgsearch', []), $options);
            $normalize = (bool) ($opts['normalize'] ?? true);

            $grammar = $this->getQuery()->getGrammar();
            $model = $this->getModel();

            $normalized = $normalize
                ? preg_replace('/[^a-zA-Z0-9]/', '', $trimmedSearch)
                : $trimmedSearch;

            return $this->where(function ($q) use ($columns, $trimmedSearch, $normalized, $grammar, $model, $normalize) {
                foreach ($columns as $col) {
                    if (str_contains($col, '.')) {
                        // relation.column
                        [$relation, $relatedCol] = explode('.', $col, 2);

                        $q->orWhereHas($relation, function ($sub) use ($relatedCol, $trimmedSearch, $normalized, $grammar, $normalize) {
                            /** @var \Illuminate\Database\Eloquent\Builder $sub */
                            $relatedModel = $sub->getModel();
                            $qualified = $relatedModel->qualifyColumn($relatedCol); // table.column
                            $wrapped = $grammar->wrap($qualified);

                            $sub->whereRaw("CAST($wrapped AS TEXT) ILIKE ?", ['%'.$trimmedSearch.'%']);

                            if ($normalize) {
                                $sub->orWhereRaw("REGEXP_REPLACE(CAST($wrapped AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?", ['%'.$normalized.'%']);
                            }
                        });
                    } else {
                        $qualified = $model->qualifyColumn($col);
                        $wrapped = $grammar->wrap($qualified);

                        $q->orWhereRaw("CAST($wrapped AS TEXT) ILIKE ?", ['%'.$trimmedSearch.'%']);

                        if ($normalize) {
                            $q->orWhereRaw("REGEXP_REPLACE(CAST($wrapped AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?", ['%'.$normalized.'%']);
                        }
                    }
                }
            });
        });
    }
}
