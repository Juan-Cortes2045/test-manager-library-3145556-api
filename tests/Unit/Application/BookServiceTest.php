<?php

namespace Tests\Unit\Application;

use App\Application\Services\BookService;
use App\Application\Services\Conflict;
use App\Application\Services\NotFound;
use App\Domain\DomainRuleViolated;
use App\Domain\InvalidAvailableCopies;
use App\Domain\InvalidIsbn;
use App\Domain\InvalidQuantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeCategories;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryCoverStorage;
use Tests\Fakes\SequentialIds;

final class BookServiceTest extends TestCase
{
    private InMemoryBookRepository $books;
    private InMemoryCoverStorage $covers;
    private BookService $service;

    protected function setUp(): void
    {
        $this->books = new InMemoryBookRepository();
        $this->covers = new InMemoryCoverStorage();
        $this->service = new BookService($this->books, new FakeCategories(), $this->covers, new SequentialIds(),
            new FixedClock(new DateTimeImmutable('2026-10-13T16:00:00Z')));
    }

    private function create(string $title = 'Cien años de soledad', string $isbn = '9780307474728', int $total = 4): string
    {
        return $this->service->create($title, 'Gabriel García Márquez', $isbn, FakeCategories::NOVELA, $total)->id;
    }

    public function test_ca031_available_starts_equal_to_total(): void
    {
        $book = $this->service->get($this->create(total: 4));
        $this->assertSame(4, $book->availableCopies());
    }

    public function test_ca032_isbn_13_digits_and_unique_among_active(): void
    {
        $id = $this->create();
        try {
            $this->create('Otro');
            $this->fail('duplicate ISBN must fail');
        } catch (Conflict $e) {
            $this->assertSame('ISBN_TAKEN', $e->errorCode);
        }
        $this->service->delete($id);
        $this->create('Reingreso');
        $this->expectException(InvalidIsbn::class);
        $this->create('Malo', '123');
    }

    public function test_ca033_total_at_least_one_and_available_never_negative(): void
    {
        $id = $this->create(total: 3);
        $book = $this->service->get($id);
        $book->withdraw();
        $book->withdraw();
        $updated = $this->service->update($id, 'Cien años', 'GGM', FakeCategories::CIENCIA, 5);
        $this->assertSame(3, $updated->availableCopies());
        try {
            $this->service->update($id, 'Cien años', 'GGM', FakeCategories::CIENCIA, 1);
            $this->fail('available would be negative');
        } catch (InvalidAvailableCopies) {
            $this->assertSame(3, $this->service->get($id)->availableCopies());
        }
        $this->expectException(InvalidQuantity::class);
        $this->create('Cero', '9780000000009', 0);
    }

    public function test_ca034_cover_png_jpg_up_to_2mb_and_null_when_absent(): void
    {
        $id = $this->create();
        $this->assertNull($this->service->get($id)->coverKey());
        $png = "\x89PNG\r\n\x1A\n".str_repeat('a', 100);
        $this->assertSame('cover-1', $this->service->setCover($id, $png)->coverKey());
        try {
            $this->service->setCover($id, "\x89PNG\r\n\x1A\n".str_repeat('a', 3 * 1024 * 1024));
            $this->fail('3 MB must fail');
        } catch (DomainRuleViolated) {
        }
        try {
            $this->service->setCover($id, 'GIF89a....');
            $this->fail('GIF must fail');
        } catch (DomainRuleViolated) {
        }
        $this->service->removeCover($id);
        $this->assertNull($this->service->get($id)->coverKey());
        $this->assertSame([], $this->covers->files);
    }

    public function test_ca035_soft_delete_and_open_loans_conflict(): void
    {
        $id = $this->create();
        $this->books->withOpenLoans[$id] = true;
        try {
            $this->service->delete($id);
            $this->fail('open loans must block deletion');
        } catch (Conflict $e) {
            $this->assertSame('BOOK_HAS_OPEN_LOANS', $e->errorCode);
        }
        unset($this->books->withOpenLoans[$id]);
        $this->service->delete($id);
        $this->expectException(NotFound::class);
        $this->service->get($id);
    }

    public function test_hu04_search_partial_text_category_active_ordered_paged_with_total(): void
    {
        $this->create('Zorro', '9780000000001');
        $this->create('Alfa', '9780000000002');
        $gone = $this->create('Alfabeto', '9780000000003');
        $this->service->delete($gone);
        $this->service->create('Átomos', 'Bohr', '9780000000004', FakeCategories::CIENCIA, 1);

        $page = $this->service->search('gabriel', FakeCategories::NOVELA, 1, 1);
        $this->assertSame(2, $page->total);
        $this->assertSame('Alfa', $page->items[0]->title());

        $this->expectException(DomainRuleViolated::class);
        $this->service->search(null, null, 1, 51);
    }
}
