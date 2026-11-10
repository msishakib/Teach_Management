<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Exports\TeacherExporter;
use App\Filament\Imports\TeacherImporter;
use App\Filament\Resources\TeacherResource\Pages;
use App\Models\Batch;
use App\Models\Teacher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeacherResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Teacher::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'People';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Teacher')
                    ->schema([
                        Forms\Components\TextInput::make('employee_no')
                            ->label('Employee no')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated on create')
                            ->visibleOn('edit'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\DatePicker::make('joining_date')
                            ->native(false),
                        Forms\Components\TextInput::make('salary')
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn () => (string) setting('general.currency_symbol', '৳')),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Linked user')
                    ->schema([
                        Forms\Components\Toggle::make('create_user')
                            ->label('Create linked user account')
                            ->live()
                            ->default(false)
                            ->dehydrated(false)
                            ->visible(fn (?Teacher $record) => $record?->user_id === null),
                        Forms\Components\TextInput::make('user_email')
                            ->label('User email')
                            ->email()
                            ->required(fn (Get $get, ?Teacher $record) => (bool) $get('create_user') || filled($record?->user_id))
                            ->maxLength(255)
                            ->visible(fn (Get $get, ?Teacher $record) => (bool) $get('create_user') || filled($record?->user_id))
                            ->dehydrated(fn (Get $get, ?Teacher $record) => (bool) $get('create_user') || filled($record?->user_id)),
                        Forms\Components\TextInput::make('user_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (Get $get) => (bool) $get('create_user'))
                            ->minLength(8)
                            ->visible(fn (Get $get, ?Teacher $record) => (bool) $get('create_user') || filled($record?->user_id))
                            ->dehydrated(fn (Get $get) => filled($get('user_password')))
                            ->helperText(fn (?Teacher $record) => filled($record?->user_id) ? 'Leave blank to keep the current password.' : null),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('employee_no')
                    ->label('Employee no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('joining_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('salary')
                    ->formatStateUsing(fn ($state) => $state === null
                        ? '—'
                        : setting('general.currency_symbol', '৳').' '.number_format((float) $state, 2))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('User')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->options(fn () => Batch::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'classRoutines',
                            fn (Builder $routines) => $routines->where('batch_id', $data['value']),
                        );
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(TeacherImporter::class)
                    ->chunkSize(100)
                    ->csvDelimiter(','),
                ExportAction::make()
                    ->exporter(TeacherExporter::class)
                    ->formats([
                        \Filament\Actions\Exports\Enums\ExportFormat::Csv,
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(TeacherExporter::class)
                        ->formats([
                            \Filament\Actions\Exports\Enums\ExportFormat::Csv,
                        ]),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeachers::route('/'),
            'create' => Pages\CreateTeacher::route('/create'),
            'edit' => Pages\EditTeacher::route('/{record}/edit'),
        ];
    }
}
