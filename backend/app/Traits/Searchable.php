<?php

namespace App\Traits;

trait Searchable
{
    /**
     * Scope a query to search across multiple fields with multi-word support.
     * All words must match at least one field for the record to be included.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $searchTerm)
    {
        // Return early if search term is empty
        if (empty(trim($searchTerm))) {
            return $query;
        }

        // Split search term into individual words and remove empty strings
        $words = array_filter(
            explode(' ', trim($searchTerm)),
            function ($word) {
                return !empty(trim($word));
            }
        );

        // If no valid words after filtering, return query unchanged
        if (empty($words)) {
            return $query;
        }

        // Get the searchable fields defined in the model
        $fields = $this->getSearchableFields();

        // Apply search logic: each word must match at least one field
        return $query->where(function ($q) use ($words, $fields) {
            foreach ($words as $word) {
                $word = trim($word);
                
                // Each word must match at least one field (AND logic between words)
                $q->where(function ($subQuery) use ($word, $fields) {
                    foreach ($fields as $field) {
                        // Word can match any field (OR logic between fields)
                        $subQuery->orWhere($field, 'LIKE', "%{$word}%");
                    }
                });
            }
        });
    }

    /**
     * Get the fields that should be searched.
     * Must be implemented by the model using this trait.
     *
     * @return array
     */
    abstract protected function getSearchableFields(): array;
}
