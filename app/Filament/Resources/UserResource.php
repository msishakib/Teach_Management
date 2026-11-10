<?php

namespace App\Filament\Resources;

use App\Enums\UserType;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\UserService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('type')
                            ->options(UserType::class)
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Leave blank to keep the current password.'
                                : null),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Roles')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->options(fn (): array => Role::query()
                                ->where('guard_name', config('permission-matrix.guard', 'web'))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Role $role): array => [
                                    $role->id => Str::headline($role->name),
                                ])
                                ->all())
                            ->preload()
                            ->searchable()
                            ->native(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? Str::headline($state) : '—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(UserType::class),
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Role $record): string => Str::headline($record->name)
                    )
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->using(fn (User $record) => app(UserService::class)->delete(
                        $record,
                        auth()->user(),
                    )),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $service = app(UserService::class);
                            $actor = auth()->user();

                            $records->each(
                                fn (User $record) => $service->delete(
                                    $record,
                                    $actor instanceof User ? $actor : null,
                                )
                            );
                        }),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['roles']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
