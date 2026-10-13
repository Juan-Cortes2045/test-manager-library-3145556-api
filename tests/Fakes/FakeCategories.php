<?php

namespace Tests\Fakes;

use App\Application\Ports\CategoryReader;

final class FakeCategories implements CategoryReader
{
    public const NOVELA = '11111111-1111-4111-8111-111111111111';
    public const CIENCIA = '22222222-2222-4222-8222-222222222222';

    public function nameOf(string $categoryId): ?string
    {
        return [self::NOVELA => 'Novela', self::CIENCIA => 'Ciencia'][$categoryId] ?? null;
    }
}
