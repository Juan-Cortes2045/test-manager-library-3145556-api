<?php

namespace App\Adapters\Persistence;

use App\Application\Ports\MemberRepository;
use App\Application\Ports\Page;
use App\Domain\DocumentNumber;
use App\Domain\Member;
use Illuminate\Support\Facades\DB;

final class DbMemberRepository implements MemberRepository
{
    public function find(string $id): ?Member
    {
        $r = DB::table('library.member')->where('id', $id)->first();

        return $r === null ? null : new Member($r->id, $r->full_name, new DocumentNumber($r->document_number));
    }

    public function existsDocument(string $documentNumber): bool
    {
        return DB::table('library.member')->where('document_number', $documentNumber)->exists();
    }

    public function save(Member $member): void
    {
        DB::table('library.member')->insert(['id' => $member->id, 'full_name' => $member->fullName, 'document_number' => $member->documentNumber->value]);
    }

    public function search(?string $text, int $page, int $size): Page
    {
        $query = DB::table('library.member')->when($text !== null,
            fn ($q) => $q->where(fn ($w) => $w->where('full_name', 'ilike', '%'.$text.'%')->orWhere('document_number', 'ilike', '%'.$text.'%')));
        $total = (clone $query)->count();
        $rows = $query->orderBy('full_name')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get();

        return new Page(array_map(fn ($r) => new Member($r->id, $r->full_name, new DocumentNumber($r->document_number)), $rows->all()), $page, $size, $total);
    }
}
