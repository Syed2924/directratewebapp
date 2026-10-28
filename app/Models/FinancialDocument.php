<?php

namespace App\Models;

/**
 * Budget documents, receipts and approvals — same private storage and
 * download rules as activity documents, kept in their own table.
 */
class FinancialDocument extends ActivityDocument
{
    protected $table = 'financial_documents';

    protected static string $folder = 'budget';

    public function kindLabel(): string
    {
        return config("budget.document_kinds.{$this->kind}", ucfirst($this->kind));
    }
}
