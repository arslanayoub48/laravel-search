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

    // Debug logging
    Log::info('Searchable trait debug', [
      'searchTerm' => $searchTerm,
      'priority' => $priority,
      'modelColumns' => $modelColumns,
      'modelRelations' => $modelRelations,
      'configColumns' => $configColumns,
      'configRelations' => $configRelations,
      'columns' => $columns,
      'relations' => $relations
    ]);

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

    // Store original relations for debugging
    $originalRelations = $finalRelations;
    
    // Fix: Process relationship columns properly - extract column names from priority arrays
    $processedRelations = [];
    foreach ($finalRelations as $relationPath => $relationColumns) {
      if (is_array($relationColumns)) {
        $processedRelations[$relationPath] = collect($relationColumns)
          ->when(
            collect($relationColumns)->keys()->first() !== 0,
            fn($cols) => $cols->sortBy(fn($priority) => $priority)
          )
          ->map(fn($priority, $column) => is_string($priority) ? $priority : $column)
          ->values()
          ->all();
      } else {
        $processedRelations[$relationPath] = $relationColumns;
      }
    }
    $finalRelations = $processedRelations;

    Log::info('Final search configuration', [
      'finalColumns' => $finalColumns,
      'finalRelations' => $finalRelations,
      'originalRelations' => $originalRelations
    ]);

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

      // Search in relations - Simplified approach
      if (!empty($finalRelations)) {
        Log::info('Processing relations search', [
          'relations' => $finalRelations,
          'searchTerm' => $searchTerm
        ]);
        
        foreach ($finalRelations as $relationPath => $relationColumns) {
          Log::info('Processing relation', [
            'relationPath' => $relationPath,
            'relationColumns' => $relationColumns
          ]);
          
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

    // Debug: Log the final SQL query
    Log::info('Final SQL query', [
      'sql' => $query->toSql(),
      'bindings' => $query->getBindings(),
      'searchTerm' => $searchTerm,
      'finalColumns' => $finalColumns,
      'finalRelations' => $finalRelations
    ]);

    // Also log the raw query for debugging
    try {
      $rawQuery = $query->toSql();
      $bindings = $query->getBindings();
      Log::info('Raw query debug', [
        'raw_sql' => $rawQuery,
        'bindings' => $bindings,
        'searchTerm' => $searchTerm
      ]);
    } catch (\Exception $e) {
      Log::error('Error getting raw query', ['error' => $e->getMessage()]);
    }

    return $query;
  }
} 
