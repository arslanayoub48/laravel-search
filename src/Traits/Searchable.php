<?php

namespace ArslanAyoub\SearchableScope\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;

trait Searchable
{
  /**
   * Search scope for models
   *
   * @param Builder $query
   * @param string|null $searchTerm
   * @param array $columns
   * @param array $relations
   * @param string $priority Accepts: 'params', 'model', 'config'
   * @return Builder
   */
  public function scopeSearch(
    Builder $query,
    ?string $searchTerm,
    array $columns = [],
    array $relations = [],
    string $priority = 'params'
  ): Builder {
    if (!$searchTerm || strlen($searchTerm) < Config::get('searchable-scope.min_term_length', 2)) {
      return $query;
    }

    $operator = Config::get('searchable-scope.default_operator', 'LIKE');
    $caseSensitive = Config::get('searchable-scope.case_sensitive', false);

    // Sources
    $modelColumns = property_exists($this, 'searchable') ? ($this->searchable['columns'] ?? []) : [];
    $modelRelations = property_exists($this, 'searchable') ? ($this->searchable['relations'] ?? []) : [];

    $configColumns = Config::get('searchable-scope.default_columns', []);
    $configRelations = Config::get('searchable-scope.default_relations', []);

    // Strict priority resolution
    switch ($priority) {
      case 'model':
        $finalColumns = $modelColumns;
        $finalRelations = $modelRelations;
        break;
      case 'config':
        $finalColumns = $configColumns;
        $finalRelations = $configRelations;
        break;
      case 'params':
      default:
        $finalColumns = $columns;
        $finalRelations = $relations;
        break;
    }

    // Sort associative priority columns (optional)
    $finalColumns = collect($finalColumns)
      ->when(
        collect($finalColumns)->keys()->first() !== 0,
        fn($col) => $col->sortBy(fn($priority) => $priority)
      )
      ->map(fn($priority, $column) => is_string($priority) ? $priority : $column)
      ->values()
      ->all();

    // Apply search
    $query->where(function ($query) use ($finalColumns, $finalRelations, $searchTerm, $operator, $caseSensitive) {
      foreach ($finalColumns as $column) {
        $value = $caseSensitive ? $searchTerm : strtolower($searchTerm);
        if (!$caseSensitive) {
          $query->orWhereRaw('LOWER(' . $column . ') ' . $operator . ' ?', [
            $operator === 'LIKE' ? "%{$value}%" : $value
          ]);
        } else {
          $query->orWhere($column, $operator, $operator === 'LIKE' ? "%{$value}%" : $value);
        }
      }

      foreach ($finalRelations as $relationPath => $relationColumns) {
        $query->orWhereHas($relationPath, function ($relationQuery) use ($relationColumns, $searchTerm, $operator, $caseSensitive) {
          $relationQuery->where(function ($nestedQuery) use ($relationColumns, $searchTerm, $operator, $caseSensitive) {
            foreach ($relationColumns as $column) {
              $value = $caseSensitive ? $searchTerm : strtolower($searchTerm);
              if (!$caseSensitive) {
                $nestedQuery->orWhereRaw('LOWER(' . $column . ') ' . $operator . ' ?', [
                  $operator === 'LIKE' ? "%{$value}%" : $value
                ]);
              } else {
                $nestedQuery->orWhere($column, $operator, $operator === 'LIKE' ? "%{$value}%" : $value);
              }
            }
          });
        });
      }
    });

    return $query;
  }
} 