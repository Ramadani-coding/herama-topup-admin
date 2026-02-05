<?php

namespace App\Filament\Pages;

use App\Services\NodeApiService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action as HeaderAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Pagination\LengthAwarePaginator;

class ManageProducts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;
    protected static ?string $navigationLabel = 'Daftar Produk';
    protected string $view = 'filament.pages.manage-products';

    // 1. WAJIB: Tambahkan properti ini untuk menyimpan total data
    public int $totalRecords = 0;

    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('sync_products')
                ->label('Sinkronisasi Produk')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $response = NodeApiService::syncProducts();
                    if ($response->successful()) {
                        Notification::make()->title('Sinkronisasi Berhasil')->success()->send();
                    } else {
                        Notification::make()->title('Gagal Sinkronisasi')->danger()->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(\App\Models\User::query()->where('id', 0)) // Placeholder query
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->records(function () {
                // Ambil state halaman dan per-halaman dari Filament
                $page = $this->getTablePage() ?: 1;
                $perPage = $this->getTableRecordsPerPage() ?: 10;

                // 2. Panggil API Node.js
                $apiData = NodeApiService::getProducts($page, $perPage);

                // 3. Pastikan mengambil key 'products' (sesuai spread ...result di Node.js)
                $items = collect($apiData['products'] ?? []);
                $total = $apiData['total'] ?? 0;

                // Simpan total ke properti class
                $this->totalRecords = $total;

                return new LengthAwarePaginator(
                    $items,
                    $total,
                    $perPage,
                    $page,
                    [
                        'path' => request()->url(),
                        'query' => request()->query(),
                    ]
                );
            })
            ->columns([
                TextColumn::make('category_name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('sku_code')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('product_name')
                    ->label('Nama Produk')
                    ->searchable(),

                TextColumn::make('price_cost')
                    ->label('Modal')
                    ->money('IDR'),

                TextColumn::make('price_sell')
                    ->label('Jual')
                    ->money('IDR'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => $state === 'active' ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Filter Kategori')
                    ->options(function () {
                        // Ambil data kategori dari API untuk isi dropdown
                        return collect(NodeApiService::getCategories())
                            ->pluck('name', 'id')
                            ->toArray();
                    }),
                TernaryFilter::make('status')
                    ->label('Status Produk')
                    ->placeholder('Semua Status')
                    ->trueLabel('Hanya Aktif')
                    ->falseLabel('Hanya Inactive') // Permintaan Anda: munculkan yang inactive
                    ->queries(
                        true: fn($query) => $query, // Logika filter ditangani manual di records()
                        false: fn($query) => $query,
                        blank: fn($query) => $query,
                    )

            ])
            ->records(function () {
                $page = $this->getTablePage() ?: 1;
                $perPage = $this->getTableRecordsPerPage() ?: 10;

                // Ambil state filter kategori
                $categoryId = $this->getTableFilterState('category_id')['value'] ?? null;

                // AMBIL STATE FILTER STATUS
                $statusFilter = $this->getTableFilterState('status')['value'] ?? null;

                // Map value filter ke string database ('active' / 'inactive')
                $status = match ($statusFilter) {
                    '1' => 'active',
                    '0' => 'inactive',
                    default => null,
                };

                $apiData = NodeApiService::getProducts($page, $perPage, $categoryId, $status);

                $items = collect($apiData['products'] ?? []);
                $this->totalRecords = $apiData['total'] ?? 0;

                return new \Illuminate\Pagination\LengthAwarePaginator(
                    $items,
                    $this->totalRecords,
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );
            })
            ->actions([
                Action::make('view')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Rincian Informasi Produk')
                    ->modalWidth('4xl') // Membuat modal lebih lebar agar lega di desktop
                    ->infolist([
                        // SECTION 1: INFORMASI DASAR
                        Section::make('Identitas Produk')
                            ->description('Informasi utama produk dan kategori')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2]) // Responsive: 1 kolom di mobile, 2 di desktop
                                    ->schema([
                                        TextEntry::make('category_name')
                                            ->label('Kategori')
                                            ->badge()
                                            ->color('warning'),
                                        TextEntry::make('sku_code')
                                            ->label('Kode SKU')
                                            ->copyable() // Admin bisa klik untuk copy
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('product_name')
                                            ->label('Nama Produk')
                                            ->columnSpanFull(), // Mengambil baris penuh
                                    ]),
                            ]),

                        // SECTION 2: ANALISA HARGA & PROFIT
                        Section::make('Analisa Keuntungan')
                            ->description('Kalkulasi margin keuntungan berdasarkan harga modal')
                            ->schema([
                                Grid::make(['default' => 2, 'lg' => 4]) // 2 kolom di mobile, 4 di desktop
                                    ->schema([
                                        TextEntry::make('price_cost')
                                            ->label('Harga Modal')
                                            ->money('IDR')
                                            ->color('gray'),

                                        TextEntry::make('price_sell')
                                            ->label('Harga Jual')
                                            ->money('IDR')
                                            ->weight(FontWeight::Bold)
                                            ->color('primary'),

                                        // KALKULASI PROFIT (RUPIAH)
                                        TextEntry::make('profit')
                                            ->label('Untung (Rp)')
                                            ->state(fn($record) => ($record['price_sell'] ?? 0) - ($record['price_cost'] ?? 0))
                                            ->money('IDR')
                                            ->weight(FontWeight::Bold)
                                            ->color(fn($state) => $state > 0 ? 'success' : 'danger'),

                                        // KALKULASI MARGIN (%)
                                        TextEntry::make('margin')
                                            ->label('Margin (%)')
                                            ->state(function ($record) {
                                                $cost = $record['price_cost'] ?? 0;
                                                if ($cost <= 0) return '0%';
                                                $profit = ($record['price_sell'] ?? 0) - $cost;
                                                return number_format(($profit / $cost) * 100, 2) . '%';
                                            })
                                            ->badge()
                                            ->color(fn($state) => (float)$state > 0 ? 'success' : 'danger'),
                                    ]),
                            ]),

                        // SECTION 3: STATUS & METADATA
                        Section::make('Status & Sinkronisasi')
                            ->schema([
                                Grid::make(['default' => 2])
                                    ->schema([
                                        TextEntry::make('status')
                                            ->badge()
                                            ->color(fn($state) => $state === 'active' ? 'success' : 'danger'),
                                        TextEntry::make('updated_at')
                                            ->label('Terakhir Diperbarui')
                                            ->dateTime()
                                            ->since(), // Menampilkan "2 hours ago"
                                    ]),
                            ])->collapsible(), // Bisa di-minimize agar ringkas
                    ])
                    ->modalSubmitAction(false), // Menghilangkan tombol 'Save' karena hanya view
                Action::make('delete')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Varian Produk')
                    ->modalDescription('Apakah anda yakin ingin menghapus varian produk ini?')
                    ->action(function (array $record) { // Type-hint harus array
                        $response = NodeApiService::deleteProduct($record['id']);
                        if ($response->successful()) {
                            Notification::make()->title('Varian Dihapus')->success()->send();
                        } else {
                            Notification::make()->title('Gagal Menghapus Variant')->danger()->send();
                        }
                    }),
            ])
        ;
    }

    // 4. WAJIB: Override fungsi ini agar pagination bar muncul
    protected function getTableRecordsCount(): ?int
    {
        return $this->totalRecords;
    }
}