<?php

namespace App\Support;

use App\Http\Controllers\Budget\TransactionController;
use App\Models\BudgetCommitment;
use App\Models\FinancialTransaction;
use App\Models\User;

/**
 * Books a unit-level payment (hostel, Jawan Markaz…) exactly once in the
 * central expense register, against the unit's budget commitment. The entry
 * is marked verified on behalf of the officer who approved the expense in
 * the unit module, so it is not reviewed twice.
 */
class CentralPayment
{
    public static function book(BudgetCommitment $commitment, array $data, User $payer, ?User $approver, string $approvalNote): FinancialTransaction
    {
        $txn = TransactionController::book($commitment->allocation, $commitment, $data, $payer);
        $txn->recordAction('verified', 'verified', $approver ?? $payer, $approvalNote);

        return $txn;
    }
}
