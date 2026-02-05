<?php

namespace App\Filament\Pages;

use App\Services\NodeApiService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
// HANYA GUNAKAN ACTION INI UNTUK DATA ARRAY/API
use Filament\Actions\Action;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ManageCategories extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    protected static ?string $navigationLabel = 'Manajemen Kategori';
    protected string $view = 'filament.pages.manage-categories';

    public function table(Table $table): Table
    {
        return $table
            ->query(\App\Models\User::query()->where('id', 0))
            ->records(fn() => collect(NodeApiService::getCategoriesPage()['data'] ?? []))
            ->columns([
                ImageColumn::make('image_url')->label('Icon')->circular(),
                TextColumn::make('name')->label('Nama Kategori'),
                TextColumn::make('markup_type')->label('Tipe Profit')->badge()->color('info'),
                TextColumn::make('markup_value')->label('Nilai Profit'),
            ])
            ->actions([
                // GUNAKAN ACTION BIASA UNTUK EDIT (Agar menerima Array)
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->fillForm(fn(array $record) => [ // Type-hint harus array
                        ...$record,
                        'server_list' => is_string($record['server_list'])
                            ? json_decode($record['server_list'], true)
                            : ($record['server_list'] ?? []),
                    ])
                    ->form([
                        TextInput::make('image_url')->required(),
                        Select::make('markup_type')
                            ->options(['flat' => 'Flat (Rp)', 'percent' => 'Persentase (%)'])
                            ->required(),
                        TextInput::make('markup_value')->numeric()->required(),
                        TextInput::make('input_type')->required(),
                        TextInput::make('placeholder')->required(),
                        TextInput::make('check_sku'),
                        Repeater::make('server_list')
                            ->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('value')->required(),
                            ])->columns(2),
                    ])
                    ->action(function (array $record, array $data) {
                        $response = NodeApiService::updateCategory($record['id'], $data);
                        if ($response->successful()) {
                            Notification::make()->title('Berhasil Update')->success()->send();
                        }
                    }),
            ]);
    }
}