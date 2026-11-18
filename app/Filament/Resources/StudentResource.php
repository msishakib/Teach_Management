<?php

namespace App\Filament\Resources;

use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Exports\StudentExporter;
use App\Filament\Imports\StudentImporter;
use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers\GuardiansRelationManager;
use App\Models\Batch;
use App\Models\Student;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'People';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Student')
                    ->schema([
                        Forms\Components\TextInput::make('admission_no')
                            ->label('Admission no')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated on create')
                            ->visibleOn('edit'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('dob')
                            ->label('Date of birth')
                            ->native(false)
                            ->maxDate(now()),
                        Forms\Components\Select::make('gender')
                            ->options(Gender::class)
                            ->native(false),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options(StudentStatus::class)
                            ->required()
                            ->native(false)
                            ->default(StudentStatus::Active),
                        Forms\Components\Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('photo')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120)
                            ->directory('students/photos')
                            ->visibility('public')
                            ->imageEditor()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Identity & academic background')
                    ->schema([
                        Forms\Components\TextInput::make('national_id')
                            ->label('NID number')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('birth_certificate_no')
                            ->label('Birth certificate no')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('religion')
                            ->maxLength(50),
                        Forms\Components\Select::make('blood_group')
                            ->options(array_combine(
                                $groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
                                $groups,
                            ))
                            ->native(false),
                        Forms\Components\Textarea::make('present_address')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('permanent_address')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('last_institution')
                            ->label('Last institution')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_exam')
                            ->label('Last exam')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('last_result')
                            ->label('Result / GPA')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('passing_year')
                            ->numeric()
                            ->minValue(1950)
                            ->maxValue(now()->year),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Linked user')
                    ->schema([
                        Forms\Components\Toggle::make('create_user')
                            ->label('Create linked user account')
                            ->live()
                            ->default(false)
                            ->dehydrated(false)
                            ->visible(fn (?Student $record) => $record?->user_id === null),
                        Forms\Components\TextInput::make('user_email')
                            ->label('User email')
                            ->email()
                            ->required(fn (Get $get, ?Student $record) => (bool) $get('create_user') || filled($record?->user_id))
                            ->maxLength(255)
                            ->visible(fn (Get $get, ?Student $record) => (bool) $get('create_user') || filled($record?->user_id))
                            ->dehydrated(fn (Get $get, ?Student $record) => (bool) $get('create_user') || filled($record?->user_id)),
                        Forms\Components\TextInput::make('user_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (Get $get) => (bool) $get('create_user'))
                            ->minLength(8)
                            ->visible(fn (Get $get, ?Student $record) => (bool) $get('create_user') || filled($record?->user_id))
                            ->dehydrated(fn (Get $get) => filled($get('user_password')))
                            ->helperText(fn (?Student $record) => filled($record?->user_id) ? 'Leave blank to keep the current password.' : null),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Guardians')
                    ->schema([
                        Forms\Components\Repeater::make('guardianItems')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('relation')
                                    ->options(GuardianRelation::class)
                                    ->required()
                                    ->native(false)
                                    ->default(GuardianRelation::Guardian),
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(50),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add guardian')
                            ->dehydrated()
                            ->visibleOn('create'),
                    ])
                    ->visibleOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->circular()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('admission_no')
                    ->label('Admission no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('dob')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('User')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(StudentStatus::class),
                Tables\Filters\SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->options(fn () => Batch::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'enrollments',
                            fn (Builder $enrollments) => $enrollments->where('batch_id', $data['value']),
                        );
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(StudentImporter::class)
                    ->chunkSize(100)
                    ->csvDelimiter(','),
                ExportAction::make()
                    ->exporter(StudentExporter::class)
                    ->formats([
                        ExportFormat::Csv,
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(StudentExporter::class)
                        ->formats([
                            ExportFormat::Csv,
                        ]),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            GuardiansRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
