<?php

namespace App\Filament\Resources;

use App\Enums\EnrollmentStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\EnrollmentResource\Pages;
use App\Models\Enrollment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class EnrollmentResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Enrollment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('student_id')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Forms\Get $get) => $rule
                            ->where('batch_id', $get('batch_id')),
                    ),
                Forms\Components\Select::make('batch_id')
                    ->relationship('batch', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('enrolled_on')
                    ->required()
                    ->native(false)
                    ->default(now()),
                Forms\Components\Select::make('status')
                    ->options(EnrollmentStatus::class)
                    ->required()
                    ->native(false)
                    ->default(EnrollmentStatus::Active),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.admission_no')
                    ->label('Admission no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('batch.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('batch.course.name')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('enrolled_on')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(EnrollmentStatus::class),
                Tables\Filters\SelectFilter::make('batch_id')
                    ->relationship('batch', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['student', 'batch.course']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnrollments::route('/'),
            'create' => Pages\CreateEnrollment::route('/create'),
            'edit' => Pages\EditEnrollment::route('/{record}/edit'),
        ];
    }
}
