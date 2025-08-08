<?php

if (! function_exists('pg_search')) {
    function pg_search($query, $search, $columns, $options = [])
    {
        $trimmedSearch = trim((string) $search);

        if ($trimmedSearch === '') {
            return $query;
        }

        return $query->pgSearch($trimmedSearch, $columns, $options);
    }
}
