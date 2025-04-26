<?php

namespace Ktr\LightSearch;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ktr\LightSearch\Core\DatabaseEngine;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

/**
 * Laravel Scout Engine powered by a lightweight database index.
 */
class LightSearchEngine extends Engine
{
    protected DatabaseEngine $engine;
    protected array $modelFieldWeights;
    protected string $table = "lightsearch_index";

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->modelFieldWeights = $config['model_field_weights'] ?? [];
        $this->engine = new DatabaseEngine($this->table);
    }

    /**
     * Normalize text, strip non-word chars, split into tokens.
     *
     * @param string $text
     * @return array
     */
    protected function tokenize(string $text): array
    {
        $normalized = strtolower($text);
        $normalized = preg_replace('/[^\p{L}0-9\s]+/u', ' ', $normalized);
        return preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Recursively extract unique tokens from the input data structure.
     *
     * @param array $data
     * @return array
     */
    protected function extractTokens(array $data): array
    {
        $tokens = [];
        foreach ($data as $value) {
            $tokens = array_merge($tokens, is_array($value)
                ? $this->extractTokens($value)
                : $this->tokenize((string)$value)
            );
        }
        return array_unique($tokens);
    }

    /**
     * Update the search index for the given set of models.
     *
     * @param iterable $models
     */
    public function update($models): void
    {
        foreach ($models as $record) {
            $id = $record->getKey();
            $model = get_class($record);

            $this->engine->deleteByRecord($id, $model);

            $weights = $this->modelFieldWeights[$model] ?? [];
            $tokens = $this->getRecordWeightedTokens($record->toSearchableArray(), $weights);

            foreach ($tokens as $token) {
                $this->engine->insert($token, $id, $model);
            }
        }
    }

    /**
     * Delete the search index entries for the given models.
     *
     * @param iterable $models
     */
    public function delete($models): void
    {
        foreach ($models as $record) {
            $this->engine->deleteByRecord($record->getKey(), get_class($record));
        }
    }

    /**
     * Perform a search and return matched ids and hit count.
     *
     * @param Builder $builder
     * @return array
     */
    public function search(Builder $builder): array
    {
        $terms = preg_split('/\s+/u', trim($builder->query ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($terms)) {
            return ['ids' => [], 'hits' => 0];
        }

        $query = DB::table($this->table)
            ->select('record_id', DB::raw('COUNT(*) as occurrences'))
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhere('token', 'like', "{$term}%");
                }
            })
            ->groupBy('record_id')
            ->orderByDesc('occurrences')
            ->limit($builder->limit ?: 10);

        $ids = $query->pluck('record_id')->toArray();

        return ['ids' => $ids, 'hits' => count($ids)];
    }

    /**
     * Paginate results: for DB implementation it's identical to plain search.
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->search($builder);
    }

    /**
     * Map raw result ids to a Laravel Collection.
     *
     * @param array $results
     * @return Collection
     */
    public function mapIds($results): Collection
    {
        return collect($results['ids']);
    }

    /**
     * Map search result IDs into their corresponding models, respecting result order.
     *
     * @param Builder $builder
     * @param array $results
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        $ids = $results['ids'] ?? [];
        if (empty($ids)) {
            return collect();
        }

        return $model->whereIn($model->getKeyName(), $ids)
            ->get()
            ->sortBy(fn($m) => array_search($m->getKey(), $ids))
            ->values();
    }

    /**
     * Get the number of hits from results.
     *
     * @param array $results
     * @return int
     */
    public function getTotalCount($results): int
    {
        return $results['hits'] ?? 0;
    }

    /**
     * Truncate the search index.
     *
     * @param $model
     */
    public function flush($model): void
    {
        DB::table($this->table)->truncate();
    }

    /**
     * No-op: Index is handled by shared DB table.
     */
    public function createIndex($name, array $options = []): void
    {
        // Not required for this engine.
    }

    /**
     * Delete all tokens for the given index name (model).
     *
     * @param string $name
     */
    public function deleteIndex($name): void
    {
        DB::table($this->table)->where('model', $name)->delete();
    }

    /**
     * Lazily map results into model instances (same as map).
     */
    public function lazyMap(Builder $builder, $results, $model): Collection
    {
        return $this->map($builder, $results, $model);
    }

    /**
     * Helper: Build a weighted flat token array for a model's search fields.
     *
     * @param array $data
     * @param array $weights
     * @return array
     */
    private function getRecordWeightedTokens(array $data, array $weights): array
    {
        $tokens = [];
        foreach ($data as $field => $value) {
            $rawTokens = is_array($value) ? $this->extractTokens($value) : $this->tokenize((string)$value);
            $weight = $weights[$field] ?? 1;
            if ($weight <= 0) {
                continue;
            }
            for ($i = 0; $i < $weight; $i++) {
                $tokens = array_merge($tokens, $rawTokens);
            }
        }
        return $tokens;
    }
}
