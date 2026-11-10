<?php

namespace App\Services;

use App\Enums\JournalEntryStatus;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    /**
     * Create a draft journal entry with balanced lines.
     *
     * @param  array{
     *     entry_date: CarbonInterface|string,
     *     voucher_type: VoucherType|string,
     *     narration?: ?string,
     *     reference?: ?Model,
     *     created_by?: ?User|int,
     * }  $header
     * @param  list<array{
     *     account_id: int,
     *     debit?: float|int|string,
     *     credit?: float|int|string,
     *     student_id?: ?int,
     *     description?: ?string,
     * }>  $lines
     */
    public function createEntry(array $header, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($header, $lines) {
            $normalizedLines = $this->validateAndNormalizeLines($lines);

            $voucherType = $header['voucher_type'] instanceof VoucherType
                ? $header['voucher_type']
                : VoucherType::from((string) $header['voucher_type']);

            $createdBy = $header['created_by'] ?? null;
            if ($createdBy instanceof User) {
                $createdBy = $createdBy->id;
            }

            $reference = $header['reference'] ?? null;

            $entry = JournalEntry::query()->create([
                'entry_no' => $this->nextEntryNo($voucherType),
                'entry_date' => $header['entry_date'],
                'voucher_type' => $voucherType,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'narration' => $header['narration'] ?? null,
                'status' => JournalEntryStatus::Draft,
                'created_by' => $createdBy,
                'posted_at' => null,
            ]);

            foreach ($normalizedLines as $line) {
                $entry->lines()->create($line);
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * Replace header + lines on a draft journal voucher.
     *
     * @param  array{
     *     entry_date?: CarbonInterface|string,
     *     narration?: ?string,
     * }  $header
     * @param  list<array{
     *     account_id: int,
     *     debit?: float|int|string,
     *     credit?: float|int|string,
     *     student_id?: ?int,
     *     description?: ?string,
     * }>  $lines
     */
    public function updateDraft(JournalEntry $entry, array $header, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($entry, $header, $lines) {
            /** @var JournalEntry $entry */
            $entry = JournalEntry::query()->lockForUpdate()->with('lines')->findOrFail($entry->id);

            if (! $entry->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft journal entries can be updated.',
                ]);
            }

            if ($entry->voucher_type !== VoucherType::Journal) {
                throw ValidationException::withMessages([
                    'voucher_type' => 'Only journal vouchers can be edited here.',
                ]);
            }

            $normalizedLines = $this->validateAndNormalizeLines($lines);

            $entry->update([
                'entry_date' => $header['entry_date'] ?? $entry->entry_date,
                'narration' => array_key_exists('narration', $header)
                    ? $header['narration']
                    : $entry->narration,
            ]);

            $entry->lines()->delete();

            foreach ($normalizedLines as $line) {
                $entry->lines()->create($line);
            }

            return $entry->fresh(['lines.account']) ?? $entry;
        });
    }

    public function post(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry) {
            /** @var JournalEntry $entry */
            $entry = JournalEntry::query()->lockForUpdate()->with('lines')->findOrFail($entry->id);

            if (! $entry->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft journal entries can be posted.',
                ]);
            }

            $this->assertBalanced(
                $entry->lines->map(fn (JournalLine $line) => [
                    'account_id' => $line->account_id,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ])->all()
            );

            $entry->update([
                'status' => JournalEntryStatus::Posted,
                'posted_at' => now(),
            ]);

            return $entry->fresh(['lines.account']) ?? $entry;
        });
    }

    /**
     * Reverse a posted entry by creating a new posted reversing entry.
     * The original entry is marked reversed and left unchanged otherwise.
     */
    public function reverse(JournalEntry $entry, ?User $actor = null, ?string $narration = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $actor, $narration) {
            /** @var JournalEntry $entry */
            $entry = JournalEntry::query()->lockForUpdate()->with('lines')->findOrFail($entry->id);

            if (! $entry->isPosted()) {
                throw ValidationException::withMessages([
                    'status' => 'Only posted journal entries can be reversed.',
                ]);
            }

            $reversingLines = $entry->lines->map(fn (JournalLine $line) => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'student_id' => $line->student_id,
                'description' => $line->description,
            ])->all();

            $reversal = $this->createEntry([
                'entry_date' => now()->toDateString(),
                'voucher_type' => $entry->voucher_type,
                'narration' => $narration ?? "Reversal of {$entry->entry_no}",
                'reference' => $entry,
                'created_by' => $actor?->id ?? $entry->created_by,
            ], $reversingLines);

            $reversal = $this->post($reversal);

            $entry->update([
                'status' => JournalEntryStatus::Reversed,
            ]);

            return $reversal->load('lines.account');
        });
    }

    /**
     * @param  list<array{
     *     account_id: int,
     *     debit?: float|int|string,
     *     credit?: float|int|string,
     *     student_id?: ?int,
     *     description?: ?string,
     * }>  $lines
     * @return list<array{account_id: int, debit: string, credit: string, student_id: ?int, description: ?string}>
     */
    protected function validateAndNormalizeLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'A journal entry requires at least two lines.',
            ]);
        }

        $accountIds = collect($lines)->pluck('account_id')->unique()->filter()->all();
        $accounts = Account::query()->whereIn('id', $accountIds)->get()->keyBy('id');

        $normalized = [];

        foreach ($lines as $index => $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $account = $accounts->get($accountId);

            if (! $account) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => "Account [{$accountId}] was not found.",
                ]);
            }

            if (! $account->isPostable()) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => "Account [{$account->code}] is not postable.",
                ]);
            }

            $debit = $this->normalizeMoney($line['debit'] ?? 0);
            $credit = $this->normalizeMoney($line['credit'] ?? 0);

            if (bccomp($debit, '0', 2) < 0 || bccomp($credit, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => 'Debit and credit amounts must be zero or positive.',
                ]);
            }

            if (bccomp($debit, '0', 2) > 0 && bccomp($credit, '0', 2) > 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => 'A line cannot have both debit and credit amounts.',
                ]);
            }

            if (bccomp($debit, '0', 2) === 0 && bccomp($credit, '0', 2) === 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => 'A line must have either a debit or a credit amount.',
                ]);
            }

            $normalized[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'student_id' => isset($line['student_id']) ? (int) $line['student_id'] : null,
                'description' => $line['description'] ?? null,
            ];
        }

        $this->assertBalanced($normalized);

        return $normalized;
    }

    /**
     * @param  list<array{debit: float|int|string, credit: float|int|string}>  $lines
     */
    protected function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'A journal entry requires at least two lines.',
            ]);
        }

        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($lines as $line) {
            $totalDebit = bcadd($totalDebit, $this->normalizeMoney($line['debit'] ?? 0), 2);
            $totalCredit = bcadd($totalCredit, $this->normalizeMoney($line['credit'] ?? 0), 2);
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw ValidationException::withMessages([
                'lines' => "Journal entry is unbalanced: debit {$totalDebit} vs credit {$totalCredit}.",
            ]);
        }

        if (bccomp($totalDebit, '0', 2) === 0) {
            throw ValidationException::withMessages([
                'lines' => 'Journal entry totals must be greater than zero.',
            ]);
        }
    }

    protected function nextEntryNo(VoucherType $type): string
    {
        $year = (int) now()->year;

        $sequencesSetting = $this->lockOrCreateSetting(
            'finance',
            'entry_yearly_sequences',
            [],
        );

        $prefix = (string) (
            $this->settings->get($type->prefixSettingKey())
            ?: data_get(config('settings.defaults'), $type->prefixSettingKey())
            ?: $type->defaultPrefix()
        );

        $format = (string) (
            $this->settings->get('finance.voucher_number_format')
            ?: data_get(config('settings.defaults'), 'finance.voucher_number_format', '{PREFIX}-{YEAR}-{NUMBER}')
        );

        $padding = max(1, (int) (
            $this->settings->get('numbering.padding')
            ?? data_get(config('settings.defaults'), 'numbering.padding', 4)
        ));

        $sequences = is_array($sequencesSetting->value) ? $sequencesSetting->value : [];
        $typeKey = $type->value;
        $yearKey = (string) $year;
        $typeSequences = is_array($sequences[$typeKey] ?? null) ? $sequences[$typeKey] : [];
        $next = ((int) ($typeSequences[$yearKey] ?? 0)) + 1;
        $typeSequences[$yearKey] = $next;
        $sequences[$typeKey] = $typeSequences;
        $sequencesSetting->update(['value' => $sequences]);

        $this->settings->forgetCache();

        return strtr($format, [
            '{PREFIX}' => $prefix,
            '{YEAR}' => (string) $year,
            '{NUMBER}' => str_pad((string) $next, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    protected function lockOrCreateSetting(string $group, string $key, mixed $default): Setting
    {
        $setting = Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->lockForUpdate()
            ->first();

        if ($setting !== null) {
            return $setting;
        }

        Setting::query()->create([
            'group' => $group,
            'key' => $key,
            'value' => $default,
        ]);

        return Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function normalizeMoney(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
