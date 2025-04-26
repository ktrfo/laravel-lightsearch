<?php

namespace Ktr\LightSearch\Core;

use Illuminate\Support\Facades\DB;

class DatabaseEngine
{
    protected string $table;

    /**
     * DatabaseEngine constructor.
     *
     * @param string $table The table name to operate on.
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Insert or update a token posting.
     *
     * @param string $token
     * @param int $recordId
     * @param string $model
     * @return void
     */
    public function insert(string $token, int $recordId, string $model): void
    {
        $now = now(); // Avoid calling now() twice

        DB::table($this->table)->upsert(
            [
                [
                    'token' => $token,
                    'record_id' => $recordId,
                    'model' => $model,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['token', 'record_id', 'model'],
            ['updated_at']
        );
    }

    /**
     * Remove all postings for a record.
     *
     * @param int $recordId
     * @param string $model
     * @return void
     */
    public function deleteByRecord(int $recordId, string $model): void
    {
        DB::table($this->table)
            ->where('record_id', $recordId)
            ->where('model', $model)
            ->delete();
    }

    /**
     * Search postings by token.
     *
     * @param string $token
     * @param int $limit
     * @return array<int>
     */
    public function search(string $token, int $limit): array
    {
        return DB::table($this->table)
            ->where('token', $token)
            ->limit($limit)
            ->pluck('record_id')
            ->toArray();
    }
}
