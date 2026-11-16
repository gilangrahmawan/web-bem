<?php

namespace App\Services;

use App\Enums\ApplicantStatus;
use App\Models\Recruitment;
use App\Models\RecruitmentApplicant;
use App\Models\RecruitmentInterview;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\RecruitmentApplicantRegisteredNotification;
use App\Support\TrackingCode;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Oprec (AGENTS.md Fase 7B + keputusan pemilik): pendaftaran publik, seleksi, hasil, akun pengurus,
 * dan penghapusan data pribadi pendaftar yang tidak diterima setelah masa simpan.
 * Perpindahan status di dalam transaction + recruitment_applicant_logs + activity_logs.
 */
class RecruitmentService
{
    public const DEFAULT_RETENTION_MONTHS = 6;

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ImageService $images,
    ) {}

    public static function normalizeNim(string $nim): string
    {
        return strtoupper(preg_replace('/\s+/', '', $nim) ?? '');
    }

    public static function retentionMonths(): int
    {
        return max(1, (int) (Setting::get('recruitment_retention_months') ?? self::DEFAULT_RETENTION_MONTHS));
    }

    /**
     * Pendaftaran dari form publik (data sudah divalidasi Form Request).
     *
     * @param  array<string, mixed>  $data
     */
    public function register(Recruitment $recruitment, array $data, UploadedFile $cv, UploadedFile $photo): RecruitmentApplicant
    {
        if (! $recruitment->isOpen()) {
            throw ValidationException::withMessages(['recruitment' => 'Pendaftaran gelombang ini sudah ditutup.']);
        }

        $nim = self::normalizeNim((string) $data['nim']);

        if ($recruitment->applicants()->where('nim', $nim)->exists()) {
            throw ValidationException::withMessages(['nim' => 'NIM ini sudah terdaftar pada gelombang ini.']);
        }

        $open = array_map('intval', $recruitment->open_divisions ?? []);
        $first = (int) $data['first_choice_division_id'];
        $second = filled($data['second_choice_division_id'] ?? null) ? (int) $data['second_choice_division_id'] : null;

        if (! in_array($first, $open, true) || ($second !== null && ! in_array($second, $open, true))) {
            throw ValidationException::withMessages(['first_choice_division_id' => 'Pilih divisi yang dibuka pada gelombang ini.']);
        }

        if ($second === $first) {
            throw ValidationException::withMessages(['second_choice_division_id' => 'Pilihan 2 harus berbeda dari pilihan 1.']);
        }

        $cvPath = $cv->storeAs('recruitment/cv', Str::uuid().'.pdf', RecruitmentApplicant::DISK);
        $photoPath = $this->images->storeWebp($photo, 'recruitment/photos', 800, RecruitmentApplicant::DISK);

        try {
            $applicant = DB::transaction(function () use ($recruitment, $data, $nim, $first, $second, $cv, $cvPath, $photoPath): RecruitmentApplicant {
                $applicant = $recruitment->applicants()->create([
                    'registration_code' => TrackingCode::generate('REG', fn (string $c) => RecruitmentApplicant::query()->where('registration_code', $c)->exists()),
                    'name' => trim((string) $data['name']),
                    'nim' => $nim,
                    'prodi' => trim((string) $data['prodi']),
                    'batch_year' => (int) $data['batch_year'],
                    'phone' => trim((string) $data['phone']),
                    'email' => strtolower(trim((string) $data['email'])),
                    'first_choice_division_id' => $first,
                    'second_choice_division_id' => $second,
                    'motivation' => trim((string) $data['motivation']),
                    'experience' => filled($data['experience'] ?? null) ? trim((string) $data['experience']) : null,
                    'cv_path' => $cvPath,
                    'cv_name' => Str::limit($cv->getClientOriginalName(), 200, ''),
                    'photo_path' => $photoPath,
                    'consent_at' => now(),
                ]);

                $applicant->logs()->create(['from_status' => null, 'to_status' => ApplicantStatus::Registered, 'note' => 'Mendaftar lewat form publik.']);
                $this->logger->log('recruitment.registered', $applicant, ['code' => $applicant->registration_code], recordIp: false);

                return $applicant;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk(RecruitmentApplicant::DISK)->delete([$cvPath, $photoPath]);

            throw ValidationException::withMessages(['nim' => 'NIM ini sudah terdaftar pada gelombang ini.']);
        } catch (\Throwable $e) {
            Storage::disk(RecruitmentApplicant::DISK)->delete([$cvPath, $photoPath]);

            throw $e;
        }

        $this->notifyNewApplicant($applicant);

        return $applicant;
    }

    /** Pendaftar baru → Sekretaris & koordinator divisi pilihan 1/2 (gagal kirim tidak menggagalkan pendaftaran). */
    private function notifyNewApplicant(RecruitmentApplicant $applicant): void
    {
        try {
            $recipients = User::query()->where('is_active', true)->role('sekretaris')->get()
                ->merge(User::query()->where('is_active', true)
                    ->role(['koordinator_kajian', 'koordinator_medpub', 'koordinator_humas'])
                    ->whereIn('division_id', $applicant->choiceDivisionIds())->get())
                ->unique('id');

            Notification::send($recipients, new RecruitmentApplicantRegisteredNotification($applicant));
        } catch (\Throwable $e) {
            Log::warning('Notifikasi pendaftar oprec gagal dikirim.', ['applicant_id' => $applicant->getKey(), 'error' => $e->getMessage()]);
        }
    }

    /** Lolos berkas. */
    public function passDocuments(User $actor, RecruitmentApplicant $applicant, ?string $note = null): RecruitmentApplicant
    {
        $this->authorize($actor, 'update', $applicant);

        return $this->transition($actor, $applicant, ApplicantStatus::DocumentPassed, $note);
    }

    /** Atur/ubah jadwal interview; status lolos berkas otomatis menjadi interview. */
    public function scheduleInterview(User $actor, RecruitmentApplicant $applicant, string $at, string $location): RecruitmentApplicant
    {
        $this->authorize($actor, 'update', $applicant);

        if (! in_array($applicant->status, [ApplicantStatus::DocumentPassed, ApplicantStatus::Interview], true)) {
            throw ValidationException::withMessages(['interview_at' => 'Jadwal interview hanya untuk pendaftar yang lolos berkas.']);
        }

        $location = trim($location);

        if ($location === '' || mb_strlen($location) > 255) {
            throw ValidationException::withMessages(['interview_location' => 'Isi tempat atau link interview (maks. 255 karakter).']);
        }

        return DB::transaction(function () use ($actor, $applicant, $at, $location): RecruitmentApplicant {
            $applicant->forceFill(['interview_at' => CarbonImmutable::parse($at), 'interview_location' => $location])->save();

            if ($applicant->status === ApplicantStatus::DocumentPassed) {
                $this->transition($actor, $applicant, ApplicantStatus::Interview, 'Jadwal interview diatur.');
            } else {
                $this->logger->log('recruitment.interview_rescheduled', $applicant, ['at' => $at], $actor);
            }

            return $applicant;
        });
    }

    /** Nilai 1–5 + catatan internal (satu per pewawancara; diperbarui bila sudah ada). */
    public function score(User $actor, RecruitmentApplicant $applicant, int $score, ?string $note = null): RecruitmentInterview
    {
        $this->authorize($actor, 'score', $applicant);

        if ($score < 1 || $score > 5) {
            throw ValidationException::withMessages(['score' => 'Nilai harus 1 sampai 5.']);
        }

        return DB::transaction(function () use ($actor, $applicant, $score, $note): RecruitmentInterview {
            $interview = $applicant->interviews()->updateOrCreate(
                ['interviewer_id' => $actor->getKey()],
                ['score' => $score, 'note' => filled($note) ? trim($note) : null],
            );

            $this->logger->log('recruitment.scored', $applicant, ['score' => $score], $actor);

            return $interview;
        });
    }

    /** Keputusan akhir: diterima (divisi = pilihan 1/2) atau ditolak. */
    public function decide(User $actor, RecruitmentApplicant $applicant, bool $accept, ?int $divisionId = null, ?string $note = null): RecruitmentApplicant
    {
        $this->authorize($actor, 'update', $applicant);

        if ($accept && ! in_array((int) $divisionId, $applicant->choiceDivisionIds(), true)) {
            throw ValidationException::withMessages(['accepted_division_id' => 'Divisi diterima harus pilihan 1 atau pilihan 2 pendaftar.']);
        }

        return DB::transaction(function () use ($actor, $applicant, $accept, $divisionId, $note): RecruitmentApplicant {
            $applicant->forceFill(['accepted_division_id' => $accept ? (int) $divisionId : null])->save();

            return $this->transition($actor, $applicant, $accept ? ApplicantStatus::Accepted : ApplicantStatus::Rejected, $note);
        });
    }

    /** Umumkan hasil: keputusan diterima/ditolak mulai terlihat di halaman cek hasil. */
    public function publishResults(User $actor, Recruitment $recruitment): Recruitment
    {
        if (Gate::forUser($actor)->denies('publishResults', $recruitment)) {
            throw new AuthorizationException('Hasil gelombang ini sudah diumumkan atau Anda tidak berhak.');
        }

        DB::transaction(function () use ($actor, $recruitment): void {
            $recruitment->forceFill(['results_published_at' => now()])->save();
            $this->logger->log('recruitment.results_published', $recruitment, ['name' => $recruitment->name], $actor);
        });

        return $recruitment;
    }

    /**
     * "Jadikan akun pengurus": user NONAKTIF tanpa role, divisi = divisi diterima, wajib ganti password.
     * Super Admin yang mengaktifkan, mereset password, dan memberi role lewat menu User.
     */
    public function createStaffAccount(User $actor, RecruitmentApplicant $applicant): User
    {
        $this->authorize($actor, 'update', $applicant);

        if ($applicant->status !== ApplicantStatus::Accepted || $applicant->accepted_division_id === null) {
            throw ValidationException::withMessages(['status' => 'Akun hanya bisa dibuat untuk pendaftar yang diterima.']);
        }

        if ($applicant->user_id !== null) {
            throw ValidationException::withMessages(['user_id' => 'Akun untuk pendaftar ini sudah dibuat.']);
        }

        if (blank($applicant->email)) {
            throw ValidationException::withMessages(['email' => 'Email pendaftar kosong; akun tidak bisa dibuat.']);
        }

        if (User::query()->where('email', $applicant->email)->exists()) {
            throw ValidationException::withMessages(['email' => "Email {$applicant->email} sudah dipakai akun lain. Periksa menu User."]);
        }

        return DB::transaction(function () use ($actor, $applicant): User {
            $user = new User([
                'name' => $applicant->name,
                'email' => $applicant->email,
                'phone' => $applicant->phone,
                'division_id' => $applicant->accepted_division_id,
                'position' => 'Anggota '.$applicant->acceptedDivision?->name,
            ]);
            $user->forceFill([
                'password' => Str::password(32),
                'is_active' => false,
                'must_change_password' => true,
            ])->save();

            $applicant->forceFill(['user_id' => $user->getKey()])->save();

            $this->logger->log('recruitment.account_created', $user, [
                'applicant_id' => $applicant->getKey(),
                'division_id' => $applicant->accepted_division_id,
            ], $actor);

            return $user;
        });
    }

    /**
     * Hapus data pribadi pendaftar yang TIDAK diterima (ditolak maupun tidak diproses sampai akhir)
     * setelah N bulan gelombang ditutup: CV, foto, nomor WA, email.
     * Disimpan untuk rekap: nama, NIM, prodi, angkatan, pilihan divisi, status, riwayat.
     */
    public function purgePersonalData(): int
    {
        $cutoff = now()->subMonths(self::retentionMonths());
        $purged = 0;

        RecruitmentApplicant::query()
            ->whereNull('data_purged_at')
            ->where('status', '!=', ApplicantStatus::Accepted->value)
            ->whereHas('recruitment', fn ($q) => $q->where('closes_at', '<=', $cutoff))
            ->chunkById(100, function ($applicants) use (&$purged): void {
                foreach ($applicants as $applicant) {
                    DB::transaction(function () use ($applicant): void {
                        Storage::disk(RecruitmentApplicant::DISK)->delete(array_filter([$applicant->cv_path, $applicant->photo_path]));

                        $applicant->forceFill([
                            'cv_path' => null,
                            'cv_name' => null,
                            'photo_path' => null,
                            'phone' => null,
                            'email' => null,
                            'data_purged_at' => now(),
                        ])->save();

                        $this->logger->log('recruitment.personal_data_purged', $applicant, ['code' => $applicant->registration_code], recordIp: false);
                    });
                    $purged++;
                }
            });

        return $purged;
    }

    private function transition(User $actor, RecruitmentApplicant $applicant, ApplicantStatus $to, ?string $note): RecruitmentApplicant
    {
        $from = $applicant->status;

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages(['status' => "Status tidak bisa diubah dari {$from->label()} ke {$to->label()}."]);
        }

        return DB::transaction(function () use ($actor, $applicant, $from, $to, $note): RecruitmentApplicant {
            $applicant->forceFill(['status' => $to])->save();
            $applicant->logs()->create([
                'from_status' => $from,
                'to_status' => $to,
                'note' => filled($note) ? trim($note) : null,
                'user_id' => $actor->getKey(),
            ]);
            $this->logger->log('recruitment.status_changed', $applicant, ['from' => $from->value, 'to' => $to->value], $actor);

            return $applicant;
        });
    }

    private function authorize(User $actor, string $ability, RecruitmentApplicant $applicant): void
    {
        if (! $actor->is_active || Gate::forUser($actor)->denies($ability, $applicant)) {
            throw new AuthorizationException('Anda tidak berhak melakukan aksi ini.');
        }
    }
}
