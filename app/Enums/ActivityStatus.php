<?php

namespace App\Enums;

/**
 * Status kegiatan (AGENTS.md C7, keputusan Fase 7):
 * draft → proposal_submitted → proposal_verified → proposal_budget_reviewed → approved
 *       → lpj_submitted → lpj_verified → lpj_budget_reviewed → completed.
 * Revisi: proposal_revision / lpj_revision (kembali ke penanggung jawab, diajukan ulang dari Sekretaris).
 * Proposal bisa rejected; kegiatan bisa cancelled.
 */
enum ActivityStatus: string
{
    case Draft = 'draft';
    case ProposalSubmitted = 'proposal_submitted';
    case ProposalVerified = 'proposal_verified';
    case ProposalBudgetReviewed = 'proposal_budget_reviewed';
    case ProposalRevision = 'proposal_revision';
    case Approved = 'approved';
    case LpjSubmitted = 'lpj_submitted';
    case LpjVerified = 'lpj_verified';
    case LpjBudgetReviewed = 'lpj_budget_reviewed';
    case LpjRevision = 'lpj_revision';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ProposalSubmitted => 'Proposal: menunggu Sekretaris',
            self::ProposalVerified => 'Proposal: menunggu Bendahara',
            self::ProposalBudgetReviewed => 'Proposal: menunggu Ketua/Wakil',
            self::ProposalRevision => 'Proposal perlu revisi',
            self::Approved => 'Disetujui · menunggu LPJ',
            self::LpjSubmitted => 'LPJ: menunggu Sekretaris',
            self::LpjVerified => 'LPJ: menunggu Bendahara',
            self::LpjBudgetReviewed => 'LPJ: menunggu Ketua/Wakil',
            self::LpjRevision => 'LPJ perlu revisi',
            self::Completed => 'Selesai',
            self::Rejected => 'Proposal ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'gray',
            self::ProposalSubmitted, self::ProposalVerified, self::ProposalBudgetReviewed,
            self::LpjSubmitted, self::LpjVerified, self::LpjBudgetReviewed => 'warning',
            self::ProposalRevision, self::LpjRevision => 'info',
            self::Approved => 'primary',
            self::Completed => 'success',
            self::Rejected => 'danger',
        };
    }

    public function chartColor(): string
    {
        return match ($this) {
            self::Draft => '#B6B6B7',
            self::ProposalSubmitted, self::LpjSubmitted => '#FFDB1A',
            self::ProposalVerified, self::LpjVerified => '#F59E0B',
            self::ProposalBudgetReviewed, self::LpjBudgetReviewed => '#FB923C',
            self::ProposalRevision, self::LpjRevision => '#38BDF8',
            self::Approved => '#000078',
            self::Completed => '#232361',
            self::Rejected => '#DC2626',
            self::Cancelled => '#64748B',
        };
    }

    /** Tahap review & peran yang memprosesnya. */
    public function reviewStep(): ?string
    {
        return match ($this) {
            self::ProposalSubmitted, self::LpjSubmitted => 'secretary',
            self::ProposalVerified, self::LpjVerified => 'treasurer',
            self::ProposalBudgetReviewed, self::LpjBudgetReviewed => 'leader',
            default => null,
        };
    }

    public function isLpjStage(): bool
    {
        return in_array($this, [self::LpjSubmitted, self::LpjVerified, self::LpjBudgetReviewed, self::LpjRevision], true);
    }

    /** LPJ belum masuk/diperbaiki: tenggat & pengingat berlaku. */
    public function awaitsLpj(): bool
    {
        return in_array($this, [self::Approved, self::LpjRevision], true);
    }

    public function isEditableByPic(): bool
    {
        return in_array($this, [self::Draft, self::ProposalRevision], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    /** @return list<self> */
    public static function inReview(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->reviewStep() !== null));
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
