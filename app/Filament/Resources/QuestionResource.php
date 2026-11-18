<?php

namespace App\Filament\Resources;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Question;
use App\Services\Lms\QuestionCsvImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuestionResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?string $navigationLabel = 'Question bank';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function formSchema(): array
    {
        return [
            Forms\Components\Select::make('course_id')
                ->relationship('course', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('type')
                ->options(QuestionType::options())
                ->required()
                ->live()
                ->native(false),
            Forms\Components\Select::make('difficulty')
                ->options(QuestionDifficulty::options())
                ->default(QuestionDifficulty::Medium->value)
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('marks')
                ->numeric()
                ->minValue(0)
                ->default(1)
                ->required(),
            Forms\Components\Textarea::make('text')
                ->required()
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('explanation')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Repeater::make('options')
                ->relationship()
                ->schema([
                    Forms\Components\TextInput::make('text')
                        ->required()
                        ->maxLength(1000),
                    Forms\Components\Toggle::make('is_correct')
                        ->inline(false),
                    Forms\Components\Hidden::make('sort_order')
                        ->default(0),
                ])
                ->orderColumn('sort_order')
                ->defaultItems(2)
                ->visible(fn (Forms\Get $get): bool => $get('type') !== QuestionType::ShortAnswer->value)
                ->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('course.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('text')->limit(60)->searchable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('difficulty')->badge(),
                Tables\Columns\TextColumn::make('marks'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('type')
                    ->options(QuestionType::options()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('importCsv')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\Select::make('course_id')
                            ->label('Course')
                            ->options(fn (): array => \App\Models\Course::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->searchable(),
                        Forms\Components\FileUpload::make('file')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->required()
                            ->storeFiles(false),
                    ])
                    ->action(function (array $data): void {
                        $course = \App\Models\Course::query()->findOrFail($data['course_id']);
                        $upload = $data['file'];
                        $contents = is_string($upload)
                            ? (string) file_get_contents($upload)
                            : (string) $upload->get();
                        $result = app(QuestionCsvImportService::class)->import($course, $contents);

                        Notification::make()
                            ->title("Imported {$result['imported']} question(s)")
                            ->success()
                            ->send();
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
        return parent::getEloquentQuery()->with(['course', 'options']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
