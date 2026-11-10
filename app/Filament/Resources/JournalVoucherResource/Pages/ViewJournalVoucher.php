<?php

namespace App\Filament\Resources\JournalVoucherResource\Pages;

use App\Filament\Resources\JournalVoucherResource;
use App\Services\AccountingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewJournalVoucher extends ViewRecord
{
    protected static string $resource = JournalVoucherResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['lines'] = $this->record->lines()
            ->orderBy('id')
            ->get()
            ->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
            ])
            ->all();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),
            Actions\Action::make('post')
                ->label('Post')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isDraft() && Auth::user()?->can('journal.approve'))
                ->action(function (): void {
                    try {
                        app(AccountingService::class)->post($this->record);
                        $this->refreshFormData(['status', 'posted_at']);

                        Notification::make()
                            ->title('Journal voucher posted')
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Could not post voucher')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('reverse')
                ->label('Reverse')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isPosted() && Auth::user()?->can('journal.approve'))
                ->action(function (): void {
                    app(AccountingService::class)->reverse($this->record, Auth::user());
                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Journal voucher reversed')
                        ->success()
                        ->send();
                }),
        ];
    }
}
