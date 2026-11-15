<?php

namespace App\Enums;

/** Kategori berkas unduhan publik (AGENTS.md C2). */
enum DownloadCategory: string
{
    case ProposalTemplate = 'proposal_template';
    case LpjTemplate = 'lpj_template';
    case CampusLoanForm = 'campus_loan_form';
    case BemLoanForm = 'bem_loan_form';
    case Sop = 'sop';
    case Letter = 'letter';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ProposalTemplate => 'Template Proposal',
            self::LpjTemplate => 'Template LPJ',
            self::CampusLoanForm => 'Form Peminjaman Barang Kampus',
            self::BemLoanForm => 'Form Peminjaman Barang BEM',
            self::Sop => 'SOP',
            self::Letter => 'Surat',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ProposalTemplate, self::LpjTemplate => 'primary',
            self::CampusLoanForm, self::BemLoanForm => 'warning',
            self::Sop => 'info',
            self::Letter => 'success',
            self::Other => 'gray',
        };
    }

    public function slug(): string
    {
        return str($this->label())->slug()->toString();
    }

    public static function fromSlug(?string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }
}
