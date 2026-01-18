<?php

namespace App\Traits;

trait Searchable
{
    // this handles multi-word search across whatever fields the model defines
    // basically splits the search term into words and makes sure ALL words match somewhere
    // e.g. "john doe" will only return results that have both "john" AND "doe" in at least one field
    public function scopeSearch($query, $searchTerm)
    {
        if (empty(trim($searchTerm))) {
            return $query;
        }

        // split by spaces and filter out empty strings
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

        // for each word, it must match at least one field (AND between words, OR between fields)
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

    // each model needs to define which fields can be searched
    abstract protected function getSearchableFields(): array;
}

