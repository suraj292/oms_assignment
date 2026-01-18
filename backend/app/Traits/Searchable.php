<?php

namespace App\Traits;

trait Searchable
{
    public function scopeSearch($query, $searchTerm)
    {
        if (empty(trim($searchTerm))) {
            return $query;
        }

        $searchWords = array_filter(
            explode(' ', trim($searchTerm)),
            function ($word) {
                return !empty(trim($word));
            }
        );

        if (empty($searchWords)) {
            return $query;
        }

        $searchableFields = $this->getSearchableFields();

        return $query->where(function ($q) use ($searchWords, $searchableFields) {
            foreach ($searchWords as $word) {
                $word = trim($word);
                
                $q->where(function ($subQuery) use ($word, $searchableFields) {
                    foreach ($searchableFields as $field) {
                        $subQuery->orWhere($field, 'LIKE', "%{$word}%");
                    }
                });
            }
        });
    }

    abstract protected function getSearchableFields(): array;
}

