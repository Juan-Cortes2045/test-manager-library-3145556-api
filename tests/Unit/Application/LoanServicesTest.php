<?php

namespace Tests\Unit\Application;

use App\Application\Services\BookService;
use App\Application\Services\Conflict;
use App\Application\Services\LendBooksService;
use App\Application\Services\MemberLimitExceeded;
use App\Application\Services\MostBorrowedReportService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\DocumentNumber;
use App\Domain\DuplicateBookInLoan;
use App\Domain\InvalidDateRange;
use App\Domain\LoanStatus;
use App\Domain\Member;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeCategories;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryCoverStorage;
use Tests\Fakes\InMemoryLoanRepository;
use Tests\Fakes\InMemoryMemberRepository;
use Tests\Fakes\SequentialIds;

final class LoanServicesTest extends TestCase
{
    private FixedClock $clock;
    private InMemoryBookRepository $books;
    private InMemoryLoanRepository $loans;
    private BookService $bookService;
    private LendBooksService $lend;
    private ReturnItemService $return;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new DateTimeImmutable('2026-10-01T10:00:00Z'));
        $ids = new SequentialIds();
        $members = new InMemoryMemberRepository();
        $members->save(new Member('m1', 'Ana Pérez', new DocumentNumber('1')));
        $members->save(new Member('m2', 'Luis Gómez', new DocumentNumber('2')));
        $this->books = new InMemoryBookRepository();
        $this->loans = new InMemoryLoanRepository($members);
        $categories = new FakeCategories();
        $this->bookService = new BookService($this->books, $categories, new InMemoryCoverStorage(), $ids, $this->clock);
        $this->lend = new LendBooksService($members, $this->books, $categories, $this->loans, $ids, $this->clock);
        $this->return = new ReturnItemService($this->loans, $this->books, $this->clock);
    }

    private function book(string $title, string $isbn, int $copies = 2): string
    {
        return $this->bookService->create($title, 'Autor', $isbn, FakeCategories::NOVELA, $copies)->id;
    }

    public function test_hu06_lend_withdraws_and_freezes(): void
    {
        $b = $this->book('Cien años', '9780000000001', 1);
        $loan = $this->lend->lend('m1', 'u1', [$b]);
        $this->assertSame(0, $this->books->findActive($b)->availableCopies());
        $this->assertSame('Novela', $loan->items()[0]->categoryName);
        $this->assertSame(LoanStatus::Open, $loan->status($this->clock->now()));
    }

    public function test_ca062_repeated_book_is_422(): void
    {
        $b = $this->book('A', '9780000000001');
        $this->expectException(DuplicateBookInLoan::class);
        $this->lend->lend('m1', 'u1', [$b, $b]);
    }

    public function test_ca063_no_copies_is_409_and_nothing_is_registered(): void
    {
        $b = $this->book('A', '9780000000001', 1);
        $this->lend->lend('m1', 'u1', [$b]);
        try {
            $this->lend->lend('m2', 'u1', [$b]);
            $this->fail('no copies left');
        } catch (Conflict $e) {
            $this->assertSame('BOOK_NOT_AVAILABLE', $e->errorCode);
        }
        $this->assertCount(1, $this->loans->loans);
    }

    public function test_rnf2_concurrent_last_copy_is_409(): void
    {
        $b = $this->book('A', '9780000000001', 1);
        $this->loans->simulateConcurrentWithdraw = true;
        try {
            $this->lend->lend('m1', 'u1', [$b]);
            $this->fail('concurrent withdraw must fail');
        } catch (Conflict $e) {
            $this->assertSame('BOOK_NOT_AVAILABLE', $e->errorCode);
        }
        $this->assertCount(0, $this->loans->loans);
    }

    public function test_ca064_more_than_three_unreturned_is_422_with_count(): void
    {
        $ids = [$this->book('A', '9780000000001'), $this->book('B', '9780000000002'), $this->book('C', '9780000000003'), $this->book('D', '9780000000004')];
        $this->lend->lend('m1', 'u1', [$ids[0], $ids[1]]);
        try {
            $this->lend->lend('m1', 'u1', [$ids[2], $ids[3]]);
            $this->fail('limit must apply');
        } catch (MemberLimitExceeded $e) {
            $this->assertSame(2, $e->currentCount);
        }
        $this->lend->lend('m1', 'u1', [$ids[2]]);
        $this->assertSame(3, $this->loans->countUnreturnedItems('m1'));
    }

    public function test_ca065_member_with_overdue_is_409(): void
    {
        $a = $this->book('A', '9780000000001');
        $b = $this->book('B', '9780000000002');
        $this->lend->lend('m1', 'u1', [$a]);
        $this->clock->now = new DateTimeImmutable('2026-10-20T10:00:00Z');
        try {
            $this->lend->lend('m1', 'u1', [$b]);
            $this->fail('overdue must block');
        } catch (Conflict $e) {
            $this->assertSame('MEMBER_HAS_OVERDUE', $e->errorCode);
        }
    }

    public function test_hu07_return_once_restocks_and_closes(): void
    {
        $b = $this->book('A', '9780000000001', 1);
        $loan = $this->lend->lend('m1', 'u1', [$b]);
        $itemId = $loan->items()[0]->id;
        $this->return->return($loan->id, $itemId);
        $this->assertSame(1, $this->books->findActive($b)->availableCopies());
        $this->assertSame(LoanStatus::Closed, $loan->status($this->clock->now()));
        try {
            $this->return->return($loan->id, $itemId);
            $this->fail('second return must fail');
        } catch (Conflict $e) {
            $this->assertSame('ITEM_ALREADY_RETURNED', $e->errorCode);
        }
        $this->assertSame(1, $this->books->findActive($b)->availableCopies());
    }

    public function test_hu09_overdue_sorted_by_days_late(): void
    {
        $a = $this->book('A', '9780000000001');
        $b = $this->book('B', '9780000000002');
        $this->lend->lend('m1', 'u1', [$a]);
        $this->clock->now = new DateTimeImmutable('2026-10-05T10:00:00Z');
        $this->lend->lend('m2', 'u1', [$b]);
        $this->clock->now = new DateTimeImmutable('2026-10-25T10:00:00Z');

        $rows = (new OverdueService($this->loans, $this->clock))->list();
        $this->assertSame(['A', 'B'], array_column($rows, 'book_title'));
        $this->assertSame([10, 6], array_column($rows, 'days_late'));
        $this->assertSame('Ana Pérez', $rows[0]['member_name']);
    }

    public function test_hu10_report_keeps_frozen_title_and_validates_range(): void
    {
        $a = $this->book('Título viejo', '9780000000001', 3);
        $this->lend->lend('m1', 'u1', [$a]);
        $this->lend->lend('m2', 'u1', [$a]);
        $this->bookService->update($a, 'Título nuevo', 'Autor', FakeCategories::CIENCIA, 3);

        $report = new MostBorrowedReportService($this->loans);
        $rows = $report->report('2026-10-01', '2026-10-31');
        $this->assertSame([['book_id' => $a, 'book_title' => 'Título viejo', 'category_name' => 'Novela', 'times_borrowed' => 2]], $rows);
        $this->assertArrayNotHasKey('member_id', $rows[0]);
        $this->assertSame([], $report->report('2026-11-01', '2026-11-30'));

        $this->expectException(InvalidDateRange::class);
        $report->report('2026-10-31', '2026-10-01');
    }
}
