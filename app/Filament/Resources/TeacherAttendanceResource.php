<?php

namespace App\Filament\Resources;

use App\Enums\TeacherAttendanceStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\TeacherAttendanceResource\Pages;
use App\Models\TeacherAttendance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class TeacherAttendanceResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = TeacherAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Teacher attendance';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Forms\Get $get) => $rule->where('date', $get('date')),
                    ),
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->native(false)
                    ->maxDate(now())
                    ->default(now()),
                Forms\Components\TimePicker::make('check_in')
                    ->seconds(false),
                Forms\Components\TimePicker::make('check_out')
                    ->seconds(false),
                Forms\Components\Select::make('status')
                    ->options(TeacherAttendanceStatus::class)
                    ->required()
                    ->native(false)
                    ->default(TeacherAttendanceStatus::Present),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('teacher.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('teacher.employee_no')
                    ->label('Employee no')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_in')
                    ->time('H:i')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('check_out')
                    ->time('H:i')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TeacherAttendanceStatus::class),
                Tables\Filters\SelectFilter::make('teacher_id')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('on_date')->label('Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['on_date'] ?? null,
                            fn (Builder $q, $date) => $q->whereDate('date', $date),
                        );
                    }),
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
        return parent::getEloquentQuery()->with(['teacher']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeacherAttendances::route('/'),
            'create' => Pages\CreateTeacherAttendance::route('/create'),
            'edit' => Pages\EditTeacherAttendance::route('/{record}/edit'),
        ];
    }
}
