<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Services\PermissionCatalogService;
use App\Support\PermissionName;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?string $navigationLabel = 'Roles';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Role')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: 'roles',
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, Forms\Get $get) => $rule->where(
                                    'guard_name',
                                    $get('guard_name') ?: config('permission-matrix.guard', 'web')
                                ),
                            )
                            ->helperText('Use snake_case identifiers such as accountant or department_head.'),

                        Forms\Components\TextInput::make('guard_name')
                            ->default(config('permission-matrix.guard', 'web'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Permissions')
                    ->description('Permissions follow the {module}.{action} pattern and are grouped by module.')
                    ->schema(static::permissionCheckboxSchema()),
            ]);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function permissionCheckboxSchema(): array
    {
        /** @var PermissionCatalogService $catalog */
        $catalog = app(PermissionCatalogService::class);
        $grouped = $catalog->groupedPermissionOptions();

        if ($grouped === []) {
            return [
                Forms\Components\Placeholder::make('no_permissions')
                    ->content('No permissions found. Run the RolePermissionSeeder first.'),
            ];
        }

        $sections = [];

        foreach ($grouped as $module => $options) {
            $sections[] = Forms\Components\Section::make(Str::headline($module))
                ->compact()
                ->schema([
                    Forms\Components\CheckboxList::make("module_permissions.{$module}")
                        ->hiddenLabel()
                        ->options($options)
                        ->columns(3)
                        ->bulkToggleable()
                        ->dehydrated(false),
                ])
                ->columnSpan(1);
        }

        return [
            Forms\Components\Grid::make(2)
                ->schema($sections),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),
                Tables\Columns\TextColumn::make('guard_name')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount('permissions');
    }

    /**
     * Flatten module_permissions.* checkbox state into permission names.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function extractPermissionNames(array $data): array
    {
        $modulePermissions = $data['module_permissions'] ?? [];

        if (! is_array($modulePermissions)) {
            return [];
        }

        $names = [];

        foreach ($modulePermissions as $selected) {
            if (! is_array($selected)) {
                continue;
            }

            foreach ($selected as $permission) {
                if (is_string($permission) && str_contains($permission, '.')) {
                    $names[] = $permission;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function permissionStateForRecord(?Model $record): array
    {
        if (! $record instanceof Role) {
            return [];
        }

        $grouped = [];

        foreach ($record->permissions as $permission) {
            $module = PermissionName::module($permission->name);
            $grouped[$module][] = $permission->name;
        }

        return $grouped;
    }
}
