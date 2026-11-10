<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\TeacherSalaryStatus;
use App\Jobs\GenerateTeacherSalariesJob;
use App\Models\PaymentVoucher;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Support\LedgerAccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherSalaryService
{
    public function __construct(
        protected PaymentVoucherService $vouchers,
        protected SettingsService $settings,
    ) {}

    public function dispatchGenerateForMonth(string $month, ?User $actor = null): void
    {
        $month = $this->normalizeMonth($month);

        GenerateTeacherSalariesJob::dispatch($month, $actor?->id);
    }

    /**
     * @return array{created: int, skipped: int, last_teacher_id: ?int, processed: int}
     */
    public function generateChunk(string $month, int $afterTeacherId = 0, int $limit = 100): array
    {
        $month = $this->normalizeMonth($month);

        $teachers = Teacher::query()
            ->whereNull('deleted_at')
            ->where('id', '>', $afterTeacherId)
            ->whereNotNull('salary')
            ->where('salary', '>', 0)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($teachers as $teacher) {
            $salary = $this->generateForTeacher($teacher, $month);

            if ($salary->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'last_teacher_id' => $teachers->last()?->id,
            'processed' => $teachers->count(),
        ];
    }

    public function generateForTeacher(
        Teacher $teacher,
        string $month,
        float|int|string|null $deductions = null,
        float|int|string|null $bonus = null,
    ): TeacherSalary {
        $month = $this->normalizeMonth($month);

        return DB::transaction(function () use ($teacher, $month, $deductions, $bonus) {
            $existing = TeacherSalary::query()
                ->where('teacher_id', $teacher->id)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->wasRecentlyCreated = false;

                return $existing;
            }

            $amount = number_format((float) ($teacher->salary ?? 0), 2, '.', '');
            $deductionAmount = number_format((float) ($deductions ?? 0), 2, '.', '');
            $bonusAmount = number_format((float) ($bonus ?? 0), 2, '.', '');

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'salary' => "Teacher [{$teacher->name}] has no salary configured.",
                ]);
            }

            $net = bcsub(bcadd($amount, $bonusAmount, 2), $deductionAmount, 2);

            if (bccomp($net, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'net' => 'Net salary must be greater than zero.',
                ]);
            }

            $salary = TeacherSalary::query()->create([
                'teacher_id' => $teacher->id,
                'month' => $month,
                'amount' => $amount,
                'deductions' => $deductionAmount,
                'bonus' => $bonusAmount,
                'net' => $net,
                'status' => TeacherSalaryStatus::Generated,
            ]);

            $salary->wasRecentlyCreated = true;

            return $salary;
        });
    }

    /**
     * Create a draft payment voucher for a generated salary.
     *
     * @param  array{
     *     paid_from_account_id?: int,
     *     method?: PaymentMethod|string,
     *     date?: string,
     *     narration?: ?string,
     * }  $options
     */
    public function pay(User $actor, TeacherSalary $salary, array $options = []): PaymentVoucher
    {
        return DB::transaction(function () use ($actor, $salary, $options) {
            /** @var TeacherSalary $salary */
            $salary = TeacherSalary::query()->lockForUpdate()->with('teacher')->findOrFail($salary->id);

            if (! $salary->canPay()) {
                throw ValidationException::withMessages([
                    'status' => 'Only generated salaries can be paid.',
                ]);
            }

            $method = isset($options['method'])
                ? ($options['method'] instanceof PaymentMethod ? $options['method'] : PaymentMethod::from((string) $options['method']))
                : PaymentMethod::Bank;

            $paidFromId = (int) ($options['paid_from_account_id']
                ?? $this->settings->get('finance.default_bank_account')
                ?? LedgerAccounts::byCode($method->defaultAccountCode() ?? LedgerAccounts::BANK)->id);

            $expenseAccount = LedgerAccounts::byCode(LedgerAccounts::SALARY_EXPENSE);

            return $this->vouchers->create([
                'date' => $options['date'] ?? now()->toDateString(),
                'payee' => $salary->teacher,
                'account_id' => $expenseAccount->id,
                'paid_from_account_id' => $paidFromId,
                'amount' => $salary->net,
                'method' => $method,
                'narration' => $options['narration'] ?? "Salary payment {$salary->month} — {$salary->teacher?->name}",
                'teacher_salary_id' => $salary->id,
                'created_by' => $actor,
            ]);
        });
    }

    protected function normalizeMonth(string $month): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $month)->format('Y-m');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'month' => 'Month must be in Y-m format.',
            ]);
        }
    }
}
