<?php

namespace App\Filament\Resources;

use App\Enums\LeaveRequestStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\LeaveRequestService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LeaveRequestResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = LeaveRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('requester_type')
                    ->label('Requester type')
                    ->options([
                        Student::class => 'Student',
                        Teacher::class => 'Teacher',
                    ])
                    ->required()
                    ->live()
                    ->native(false),
                Forms\Components\Select::make('requester_id')
                    ->label('Requester')
                    ->options(function (Forms\Get $get) {
                        return match ($get('requester_type')) {
                            Student::class => Student::query()->orderBy('name')->pluck('name', 'id'),
                            Teacher::class => Teacher::query()->orderBy('name')->pluck('name', 'id'),
                            default => [],
                        };
                    })
                    ->searchable()
                    ->required(),
                Forms\Components\DatePicker::make('from_date')
                    ->required()
                    ->native(false),
                Forms\Components\DatePicker::make('to_date')
                    ->required()
                    ->native(false)
                    ->afterOrEqual('from_date'),
                Forms\Components\Textarea::make('reason')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->options(LeaveRequestStatus::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('requester_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->badge(),
                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requester')
                    ->searchable(),
                Tables\Columns\TextColumn::make('from_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('to_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved by')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(LeaveRequestStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (LeaveRequest $record) => $record->status === LeaveRequestStatus::Pending
                        && Auth::user()?->can('approve', $record))
                    ->requiresConfirmation()
                    ->action(function (LeaveRequest $record) {
                        app(LeaveRequestService::class)->approve($record, Auth::user());
                        Notification::make()->title('Leave approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (LeaveRequest $record) => $record->status === LeaveRequestStatus::Pending
                        && Auth::user()?->can('approve', $record))
                    ->requiresConfirmation()
                    ->action(function (LeaveRequest $record) {
                        app(LeaveRequestService::class)->reject($record, Auth::user());
                        Notification::make()->title('Leave rejected')->success()->send();
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['requester', 'approvedBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'view' => Pages\ViewLeaveRequest::route('/{record}'),
        ];
    }
}
