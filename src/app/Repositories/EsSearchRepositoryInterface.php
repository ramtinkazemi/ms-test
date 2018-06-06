<?php
declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Collection;

interface EsSearchRepositoryInterface
{
    public function searchData(array $params = []): Collection;

    public function getSearchQuery(array $queryParams, int $size, int $from): array;

    public function autoComplete(string $query): array;
}