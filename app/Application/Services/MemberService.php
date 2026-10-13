<?php

namespace App\Application\Services;

use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\Page;
use App\Domain\DocumentNumber;
use App\Domain\DomainRuleViolated;
use App\Domain\Member;

/** HU-05: create, list/search and detail of members. Members are never deleted (CA-05.3). */
final readonly class MemberService
{
    public function __construct(
        private MemberRepository $members,
        private LoanRepository $loans,
        private IdGenerator $ids,
    ) {
    }

    public function create(string $fullName, string $documentNumber): Member
    {
        $document = new DocumentNumber($documentNumber);
        if ($this->members->existsDocument($document->value)) {
            throw new Conflict('DOCUMENT_TAKEN', 'Ya existe un socio con ese documento.');
        }
        $member = new Member($this->ids->next(), $fullName, $document);
        $this->members->save($member);

        return $member;
    }

    public function search(?string $text, int $page, int $size): Page
    {
        if ($page < 1 || $size < 1 || $size > 50) {
            throw new DomainRuleViolated('page debe ser >= 1 y size entre 1 y 50.');
        }

        return $this->members->search($text !== null && trim($text) !== '' ? trim($text) : null, $page, $size);
    }

    /** @return array{member: Member, active_loans: int} active_loans counts unreturned books (I-07). */
    public function get(string $id): array
    {
        $member = $this->members->find($id) ?? throw new NotFound('El socio no existe.');

        return ['member' => $member, 'active_loans' => $this->loans->countUnreturnedItems($id)];
    }
}
