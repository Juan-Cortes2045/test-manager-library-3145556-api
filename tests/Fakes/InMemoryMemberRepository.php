<?php

namespace Tests\Fakes;

use App\Application\Ports\MemberRepository;
use App\Application\Ports\Page;
use App\Domain\Member;

final class InMemoryMemberRepository implements MemberRepository
{
    /** @var array<string, Member> */
    public array $members = [];

    public function find(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    public function existsDocument(string $documentNumber): bool
    {
        foreach ($this->members as $m) {
            if ($m->documentNumber->value === $documentNumber) {
                return true;
            }
        }

        return false;
    }

    public function save(Member $member): void
    {
        $this->members[$member->id] = $member;
    }

    public function search(?string $text, int $page, int $size): Page
    {
        $found = array_values(array_filter($this->members, fn (Member $m) => $text === null
            || stripos($m->fullName, $text) !== false || str_contains($m->documentNumber->value, $text)));
        usort($found, fn (Member $a, Member $b) => strcmp($a->fullName, $b->fullName));

        return new Page(array_slice($found, ($page - 1) * $size, $size), $page, $size, count($found));
    }
}
