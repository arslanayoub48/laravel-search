<?php

namespace ArslanAyoub\SearchableScope\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

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

    // Priority resolution
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

    // Process columns - extract column names from priority arrays
    $finalColumns = $this->processPriorityArray($finalColumns);

    // Process relations - extract column names from priority arrays
    $finalRelations = $this->processRelationsArray($finalRelations);

    // Debug logging (only if enabled)
    if (Config::get('searchable-scope.debug', false)) {
      Log::info('Searchable trait debug', [
        'searchTerm' => $searchTerm,
        'priority' => $priority,
        'finalColumns' => $finalColumns,
        'finalRelations' => $finalRelations
      ]);
    }

    // Apply search
    $query->where(function ($query) use ($finalColumns, $finalRelations, $searchTerm, $operator, $caseSensitive) {
      // Search in columns
      if (!empty($finalColumns)) {
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
      }

      // Search in relations
      if (!empty($finalRelations)) {
        foreach ($finalRelations as $relationPath => $relationColumns) {
          $query->orWhereHas($relationPath, function ($relationQuery) use ($relationColumns, $searchTerm, $operator, $caseSensitive) {
            foreach ($relationColumns as $column) {
              $value = $caseSensitive ? $searchTerm : strtolower($searchTerm);
              if (!$caseSensitive) {
                $relationQuery->orWhereRaw('LOWER(' . $column . ') ' . $operator . ' ?', [
                  $operator === 'LIKE' ? "%{$value}%" : $value
                ]);
              } else {
                $relationQuery->orWhere($column, $operator, $operator === 'LIKE' ? "%{$value}%" : $value);
              }
            }
          });
        }
      }
    });

    // Debug logging (only if enabled)
    if (Config::get('searchable-scope.debug', false)) {
      Log::info('Final SQL query', [
        'sql' => $query->toSql(),
        'bindings' => $query->getBindings(),
        'searchTerm' => $searchTerm
      ]);
    }

    return $query;
  }

  /**
   * Process priority array to extract column names
   *
   * @param array $array
   * @return array
   */
  private function processPriorityArray(array $array): array
  {
    if (empty($array)) {
      return [];
    }

    // Check if this is a priority array (has numeric keys)
    $firstKey = array_key_first($array);
    if (is_numeric($firstKey)) {
      // Simple array, return as is
      return $array;
    }

    // Priority array, sort by priority and extract column names
    asort($array); // Sort by priority (value)
    $result = [];
    foreach ($array as $column => $priority) {
      $result[] = $column;
    }
    
    return $result;
  }

  /**
   * Process relations array to extract column names
   *
   * @param array $relations
   * @return array
   */
  private function processRelationsArray(array $relations): array
  {
    $processed = [];
    
    foreach ($relations as $relationPath => $relationColumns) {
      if (is_array($relationColumns)) {
        $processed[$relationPath] = $this->processPriorityArray($relationColumns);
      } else {
        $processed[$relationPath] = [$relationColumns];
      }
    }
    
    return $processed;
  }
} 
