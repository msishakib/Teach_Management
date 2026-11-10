<?php

namespace App\Filament\Resources\ReceiveVoucherResource\Pages;

use App\Filament\Resources\ReceiveVoucherResource;
use App\Services\ReceiveVoucherService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewReceiveVoucher extends ViewRecord
{
    protected static string $resource = ReceiveVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),
            Actions\Action::make('approve')
                ->label('Approve & post')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isDraft() && Auth::user()?->can('voucher.approve'))
                ->action(function (): void {
                    app(ReceiveVoucherService::class)->approve(Auth::user(), $this->record);
                    $this->refreshFormData(['status', 'approved_by', 'approved_at', 'posted_at', 'journal_entry_id']);

                    Notification::make()
                        ->title('Receive voucher approved and posted')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('pdf')
                ->label('Print PDF')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('receive-vouchers.pdf', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
