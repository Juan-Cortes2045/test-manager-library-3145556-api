<?php

namespace App\Domain;

/**
 * Computed loan status (CA-08.3, Article VII): never stored.
 */
enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';
}
