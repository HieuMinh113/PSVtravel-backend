<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Banner\Models\Banner;

class BannerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('subtitle')
                    ->placeholder('-'),
                ImageEntry::make('image'),
                ImageEntry::make('image_mobile')
                    ->placeholder('-'),
                TextEntry::make('link')
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('start_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('end_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('sort_order')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Banner $record): bool => $record->trashed()),
            ]);
    }
}
