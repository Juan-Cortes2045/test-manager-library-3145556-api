<?php

namespace App\Adapters\Persistence;

use App\Application\Ports\CategoryReader;
use Illuminate\Support\Facades\DB;

final class DbCategoryReader implements CategoryReader
{
    public function nameOf(string $categoryId): ?string
    {
        return DB::table('library.category')->where('id', $categoryId)->value('name');
    }

    /** @return list<array{id: string, name: string}> */
    public function all(): array
    {
        return DB::table('library.category')->orderBy('name')->get(['id', 'name'])->map(fn ($r) => (array) $r)->all();
    }
}
