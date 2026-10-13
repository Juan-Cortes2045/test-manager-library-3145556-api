<?php

namespace Tests\Unit\Domain;

use App\Domain\DocumentNumber;
use App\Domain\DomainRuleViolated;
use App\Domain\Member;
use PHPUnit\Framework\TestCase;

final class MemberTest extends TestCase
{
    public function test_member_keeps_trimmed_name_and_document(): void
    {
        $member = new Member('id', ' Ana Pérez ', new DocumentNumber(' 1020304050 '));
        $this->assertSame('Ana Pérez', $member->fullName);
        $this->assertSame('1020304050', $member->documentNumber->value);
    }

    public function test_empty_document_is_rejected(): void
    {
        $this->expectException(DomainRuleViolated::class);
        new DocumentNumber('  ');
    }

    public function test_document_longer_than_30_is_rejected(): void
    {
        $this->expectException(DomainRuleViolated::class);
        new DocumentNumber(str_repeat('9', 31));
    }

    public function test_empty_name_is_rejected(): void
    {
        $this->expectException(DomainRuleViolated::class);
        new Member('id', ' ', new DocumentNumber('1'));
    }
}
