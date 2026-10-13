<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-18: moves the two "domain-only" invariants of data-model.md §4 into the engine (Article VI).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE library.book ADD CONSTRAINT ck_book_total_positive CHECK (total_copies >= 1);
ALTER TABLE library."user" ADD CONSTRAINT ck_user_role CHECK (role IN ('admin', 'librarian'));
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE library.book DROP CONSTRAINT ck_book_total_positive;
ALTER TABLE library."user" DROP CONSTRAINT ck_user_role;
SQL);
    }
};
