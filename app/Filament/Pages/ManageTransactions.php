<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\NodeApiService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Collection;

class ManageTransactions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Daftar Transaksi';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;
    protected string $view = 'filament.pages.manage-transactions';

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query())
            ->records(function () {
                $allTransactions = collect(NodeApiService::getTransactions());
                $search = $this->tableSearch;
                return $allTransactions->when($search, function ($collection) use ($search) {
                    return $collection->filter(function ($item) use ($search) {
                        return str_contains(strtolower($item['ref_id'] ?? ''), strtolower($search)) ||
                            str_contains(strtolower($item['customer_no'] ?? ''), strtolower($search)) ||
                            str_contains(strtolower($item['display_name'] ?? ''), strtolower($search)) ||
                            str_contains(strtolower($item['sku_code'] ?? ''), strtolower($search));
                    });
                });
            })
            ->columns([
                TextColumn::make('ref_id')
                    ->label('Invoice')
                    ->searchable(),
                TextColumn::make('customer_no')
                    ->label('Tujuan')
                    ->searchable(),
                TextColumn::make('display_name')
                    ->label('Produk')
                    ->limit(10)
                    ->searchable(),
                TextColumn::make('amount_sell')
                    ->label('Harga Jual')
                    ->money('IDR'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'sukses' => 'success',
                        'pending' => 'warning',
                    })
            ])
            ->actions([ // Gunakan actions (kolom tombol), bukan recordActions
                Action::make('view_detail')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Detail Transaksi')
                    ->modalWidth('3xl')
                    ->infolist([
                        Section::make('Informasi Utama')
                            ->schema([
                                Grid::make(4)->schema([ // Ubah ke Grid 4 agar muat dengan kolom WhatsApp
                                    TextEntry::make('ref_id')->label('Invoice'),
                                    TextEntry::make('customer_no')->label('Nomor Tujuan'),
                                    TextEntry::make('display_name')->label('Produk'),

                                    // Kolom WhatsApp Baru dengan Link
                                    TextEntry::make('phone_number')
                                        ->label('WhatsApp Pembeli')
                                        ->icon('heroicon-m-chat-bubble-left-ellipsis')
                                        ->color('success')
                                        ->placeholder('Tidak ada nomor')
                                        ->url(function ($state) {
                                            if (!$state) return null;
                                            // Bersihkan karakter non-angka
                                            $phone = preg_replace('/[^0-9]/', '', $state);
                                            // Jika nomor diawali '0', ubah ke format internasional '62'
                                            if (str_starts_with($phone, '0')) {
                                                $phone = '62' . substr($phone, 1);
                                            }
                                            return "https://wa.me/{$phone}";
                                        })
                                        ->openUrlInNewTab(), // Membuka di tab baru agar dashboard tidak tertutup
                                ]),
                            ]),
                        Section::make('Status & Provider')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('status')
                                        ->label('Status Provider')
                                        ->badge()
                                        ->formatStateUsing(function (array $record) {
                                            return match ($record['payment_status']) {
                                                'expire', 'failed' => 'cancel',
                                                'pending' => 'waiting payment',
                                                default => $record['status'],
                                            };
                                        })
                                        ->color(fn(string $state): string => match ($state) {
                                            'success', 'sukses' => 'success',
                                            'waiting payment', 'pending' => 'warning',
                                            'cancel', 'failed', 'expire' => 'danger',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('sn')->label('SN')->placeholder('Belum tersedia'),
                                    TextEntry::make('message')->label('Pesan Provider')->placeholder('Belum tersedia'),
                                ]),
                            ]),
                        Section::make('Rincian Pembayaran')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('amount_sell')->label('Harga Jual')->money('IDR'),
                                    TextEntry::make('amount_cost')->label('Modal')->money('IDR'),

                                    // LOGIKA PROFIT: Harga Jual - Modal
                                    TextEntry::make('profit')
                                        ->label('Profit')
                                        ->getStateUsing(fn(array $record): float => $record['amount_sell'] - $record['amount_cost'])
                                        ->money('IDR')
                                        ->color('success')
                                        ->weight('bold'),

                                    TextEntry::make('payment_method')->label('Metode Bayar'),
                                    TextEntry::make('payment_status')
                                        ->label('Status Bayar')
                                        ->badge()
                                        ->color(fn(string $state): string => match ($state) {
                                            'success' => 'success',
                                            'pending' => 'warning',
                                            'expire', 'failed' => 'danger',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('fee')->label('Biaya Admin')->money('IDR'),
                                ]),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
            ]);
    }
}