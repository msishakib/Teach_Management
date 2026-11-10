<?php

namespace App\Services;

use App\Enums\AccountKind;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /**
     * @param  array{
     *     kind: AccountKind|string,
     *     parent_id?: ?int,
     *     code: string,
     *     name: string,
     *     type: AccountType|string,
     *     is_cash_or_bank?: bool,
     *     opening_balance?: float|int|string,
     *     status?: AccountStatus|string,
     * }  $payload
     */
    public function create(array $payload): Account
    {
        return DB::transaction(fn () => $this->persist(new Account, $payload));
    }

    /**
     * @param  array{
     *     kind: AccountKind|string,
     *     parent_id?: ?int,
     *     code: string,
     *     name: string,
     *     type: AccountType|string,
     *     is_cash_or_bank?: bool,
     *     opening_balance?: float|int|string,
     *     status?: AccountStatus|string,
     * }  $payload
     */
    public function update(Account $account, array $payload): Account
    {
        return DB::transaction(function () use ($account, $payload) {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);

            return $this->persist($account, $payload);
        });
    }

    /**
     * @param  array{
     *     kind: AccountKind|string,
     *     parent_id?: ?int,
     *     code: string,
     *     name: string,
     *     type: AccountType|string,
     *     is_cash_or_bank?: bool,
     *     opening_balance?: float|int|string,
     *     status?: AccountStatus|string,
     * }  $payload
     */
    protected function persist(Account $account, array $payload): Account
    {
        $kind = $payload['kind'] instanceof AccountKind
            ? $payload['kind']
            : AccountKind::from((string) $payload['kind']);

        $type = $payload['type'] instanceof AccountType
            ? $payload['type']
            : AccountType::from((string) $payload['type']);

        $status = isset($payload['status'])
            ? ($payload['status'] instanceof AccountStatus
                ? $payload['status']
                : AccountStatus::from((string) $payload['status']))
            : AccountStatus::Active;

        $parentId = isset($payload['parent_id']) && $payload['parent_id'] !== ''
            ? (int) $payload['parent_id']
            : null;

        $parent = $this->resolveParent($kind, $parentId, $account->exists ? $account->id : null);

        if ($parent && $parent->type !== $type) {
            throw ValidationException::withMessages([
                'type' => 'Account type must match the parent group type.',
            ]);
        }

        if ($account->exists) {
            $this->assertKindChangeAllowed($account, $kind);
            $this->assertParentChangeAllowed($account, $parentId);
        }

        $isGroup = $kind->isGroup();
        $isCashOrBank = $isGroup ? false : (bool) ($payload['is_cash_or_bank'] ?? false);
        $openingBalance = $isGroup
            ? '0.00'
            : number_format((float) ($payload['opening_balance'] ?? 0), 2, '.', '');

        $account->fill([
            'parent_id' => $parent?->id,
            'code' => trim((string) $payload['code']),
            'name' => trim((string) $payload['name']),
            'type' => $type,
            'is_group' => $isGroup,
            'is_cash_or_bank' => $isCashOrBank,
            'opening_balance' => $openingBalance,
            'status' => $status,
        ]);
        $account->save();

        return $account->fresh(['parent']) ?? $account;
    }

    protected function resolveParent(AccountKind $kind, ?int $parentId, ?int $excludeId = null): ?Account
    {
        if ($kind === AccountKind::Group) {
            if ($parentId !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Top-level groups cannot have a parent.',
                ]);
            }

            return null;
        }

        if ($parentId === null) {
            throw ValidationException::withMessages([
                'parent_id' => $kind === AccountKind::SubGroup
                    ? 'Sub groups must belong to a group.'
                    : 'Ledgers must belong to a group or sub group.',
            ]);
        }

        $parent = Account::query()
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->find($parentId);

        if (! $parent || ! $parent->is_group) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent must be an account group.',
            ]);
        }

        if ($kind === AccountKind::SubGroup && $parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Sub groups must belong to a top-level group.',
            ]);
        }

        return $parent;
    }

    protected function assertKindChangeAllowed(Account $account, AccountKind $kind): void
    {
        $currentKind = $account->kind();

        if ($currentKind === $kind) {
            return;
        }

        if ($account->children()->exists()) {
            throw ValidationException::withMessages([
                'kind' => 'Cannot change kind while child accounts exist.',
            ]);
        }

        if ($account->journalLines()->exists()) {
            throw ValidationException::withMessages([
                'kind' => 'Cannot change kind after journal postings exist.',
            ]);
        }
    }

    protected function assertParentChangeAllowed(Account $account, ?int $parentId): void
    {
        if ((int) $account->parent_id === (int) $parentId) {
            return;
        }

        if ($parentId !== null && $this->isDescendantOf($parentId, $account->id)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Cannot move an account under one of its descendants.',
            ]);
        }
    }

    protected function isDescendantOf(int $candidateId, int $ancestorId): bool
    {
        $current = Account::query()->find($candidateId);

        while ($current?->parent_id) {
            if ((int) $current->parent_id === $ancestorId) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }
}
