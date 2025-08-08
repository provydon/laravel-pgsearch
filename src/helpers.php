<?php

if (! function_exists('pg_search')) {
    function pg_search($query, $search, $columns, $options = [])
    {
        if (! $search || trim($search) === '') {
            return $query;
        }

        return $query->pgSearch($search, $columns, $options);
    }
}
