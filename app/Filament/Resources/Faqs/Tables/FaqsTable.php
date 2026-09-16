<?php

namespace App\Filament\Resources\Faqs\Tables;

use App\Models\Faq;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FaqsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')->label('Câu hỏi')->searchable()->wrap()->limit(80),
                TextColumn::make('category')
                    ->label('Chuyên mục')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Faq::NHOM[$state] ?? 'Chung'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('sort_order')->label('Thứ tự')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('category')->label('Chuyên mục')->options(Faq::NHOM),
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(['published' => 'Đang hiển thị', 'hidden' => 'Đã ẩn']),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
