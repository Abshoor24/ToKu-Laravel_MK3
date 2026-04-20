<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),

                TextInput::make('total_price')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled(),

                Repeater::make('items')
                    ->relationship() // ambil dari relasi table Transaction kolom items
                    ->schema([
                        Select::make('product_id')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->required(),

                        TextInput::make('quantity')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        TextInput::make('price')
                            ->numeric()
                            ->required(),
                    ])
                    ->columns(3)
                    ->required()
                    ->label('Daftar Produk'),
            ])->disabled();
    }
}
