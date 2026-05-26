<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('brand')
                    ->nullable(),
                TextInput::make('description'),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('Rp'),
                TextInput::make('category')
                    ->required(),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0),
                FileUpload::make('image')
                    ->image(),
            ]);
    }
}