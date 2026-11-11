<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\AdmissionApplicationResource\Pages;
use App\Models\AdmissionApplication;
use App\Models\Course;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms;

class AdmissionApplicationResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = AdmissionApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Admissions';

    public static function table(Tables\Table $table): Tables\Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('application_no')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('course.name')->label('Course')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'reviewing' => 'Reviewing',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
                Tables\Filters\SelectFilter::make('course_id')->label('Course')->options(fn () => Course::pluck('name','id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->action(function (AdmissionApplication $record, array $data = []) {
                        app(\App\Services\AdmissionService::class)->approve($record, auth()->user());
                    })
                    ->requiresConfirmation()
                    ->color('success'),
                Tables\Actions\Action::make('reject')
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (AdmissionApplication $record, array $data) {
                        app(\App\Services\AdmissionService::class)->reject($record, $data['reason'], auth()->user());
                    })
                    ->color('danger'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmissionApplications::route('/'),
        ];
    }
}

