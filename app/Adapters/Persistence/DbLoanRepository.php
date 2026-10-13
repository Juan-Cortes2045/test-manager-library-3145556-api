<?php

namespace App\Adapters\Persistence;

use App\Application\Ports\LoanRepository;
use App\Application\Ports\OverdueLine;
use App\Application\Ports\Page;
use App\Application\Ports\ReportReader;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DbLoanRepository implements LoanRepository, ReportReader
{
    public function countUnreturnedItems(string $memberId): int
    {
        return DB::table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $memberId)->whereNull('i.returned_at')->count();
    }

    public function memberHasOverdue(string $memberId, DateTimeImmutable $now): bool
    {
        return DB::table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $memberId)->whereNull('i.returned_at')->where('i.due_at', '<', Rows::sql($now))->exists();
    }

    /** ADR-002 / I-01: conditional decrement per book inside one transaction; 0 rows → nothing persisted. */
    public function saveNew(Loan $loan, array $books): bool
    {
        try {
            DB::transaction(function () use ($loan, $books) {
                foreach ($books as $book) {
                    $updated = DB::update('UPDATE library.book SET available_copies = available_copies - 1 WHERE id = ? AND available_copies > 0 AND deleted_at IS NULL', [$book->id]);
                    if ($updated !== 1) {
                        throw new RuntimeException('copy taken');
                    }
                }
                DB::table('library.loan')->insert(['id' => $loan->id, 'member_id' => $loan->memberId,
                    'loaned_by_user_id' => $loan->loanedByUserId, 'loaned_at' => Rows::sql($loan->loanedAt)]);
                foreach ($loan->items() as $item) {
                    DB::table('library.loan_item')->insert(['id' => $item->id, 'loan_id' => $loan->id, 'book_id' => $item->bookId,
                        'book_title' => $item->bookTitle, 'isbn' => $item->isbn, 'category_name' => $item->categoryName,
                        'due_at' => Rows::sql($item->dueAt), 'returned_at' => null]);
                }
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'copy taken') {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function find(string $id): ?Loan
    {
        $l = DB::table('library.loan')->where('id', $id)->first();
        if ($l === null) {
            return null;
        }
        $items = DB::table('library.loan_item')->where('loan_id', $id)->orderBy('id')->get()->map(fn ($i) => new LoanItem(
            $i->id, $i->book_id, $i->book_title, trim($i->isbn), $i->category_name, Rows::instant($i->due_at), Rows::instant($i->returned_at)))->all();

        return Loan::restore($l->id, $l->member_id, $l->loaned_by_user_id, Rows::instant($l->loaned_at), $items);
    }

    /** CA-07.1/07.2: returned_at only if still null, then +1 copy, in one transaction; never adds twice. */
    public function saveReturn(LoanItem $item, Book $book): void
    {
        DB::transaction(function () use ($item) {
            $updated = DB::update('UPDATE library.loan_item SET returned_at = ? WHERE id = ? AND returned_at IS NULL', [Rows::sql($item->returnedAt()), $item->id]);
            if ($updated === 1) {
                DB::update('UPDATE library.book SET available_copies = available_copies + 1 WHERE id = ?', [$item->bookId]);
            }
        });
    }

    public function list(?DateTimeImmutable $from, ?DateTimeImmutable $toExclusive, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): Page
    {
        $open = fn ($q) => $q->select(DB::raw(1))->from('library.loan_item as i')->whereColumn('i.loan_id', 'l.id')->whereNull('i.returned_at');
        $late = fn ($q) => $open($q)->where('i.due_at', '<', Rows::sql($now));
        $query = DB::table('library.loan as l')
            ->when($from !== null, fn ($q) => $q->where('l.loaned_at', '>=', Rows::sql($from)))
            ->when($toExclusive !== null, fn ($q) => $q->where('l.loaned_at', '<', Rows::sql($toExclusive)))
            ->when($memberId !== null, fn ($q) => $q->where('l.member_id', $memberId))
            ->when($status === 'closed', fn ($q) => $q->whereNotExists($open))
            ->when($status === 'overdue', fn ($q) => $q->whereExists($late))
            ->when($status === 'open', fn ($q) => $q->whereExists($open)->whereNotExists($late));
        $total = (clone $query)->count();
        $ids = $query->orderByDesc('l.loaned_at')->orderBy('l.id')->offset(($page - 1) * $size)->limit($size)->pluck('l.id')->all();

        return new Page(array_map(fn ($id) => $this->find($id), $ids), $page, $size, $total);
    }

    public function overdueLines(DateTimeImmutable $now): array
    {
        return DB::table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('i.returned_at')->where('i.due_at', '<', Rows::sql($now))
            ->get(['l.id as loan_id', 'i.id as item_id', 'i.book_title', 'm.full_name', 'i.due_at'])
            ->map(fn ($r) => new OverdueLine($r->loan_id, $r->item_id, $r->book_title, $r->full_name, Rows::instant($r->due_at)))->all();
    }

    /** ADR-004: one aggregated query over frozen values; no join to the live catalog. */
    public function mostBorrowed(DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        return DB::table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.loaned_at', '>=', Rows::sql($from))->where('l.loaned_at', '<', Rows::sql($toExclusive))
            ->groupBy('i.book_id', 'i.book_title', 'i.category_name')
            ->orderByRaw('count(*) desc, i.book_title')
            ->get([DB::raw('i.book_id'), DB::raw('i.book_title'), DB::raw('i.category_name'), DB::raw('count(*)::int as times_borrowed')])
            ->map(fn ($r) => ['book_id' => $r->book_id, 'book_title' => $r->book_title, 'category_name' => $r->category_name, 'times_borrowed' => (int) $r->times_borrowed])->all();
    }
}
