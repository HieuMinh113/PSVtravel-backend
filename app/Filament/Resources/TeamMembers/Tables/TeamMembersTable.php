<?php

namespace App\Filament\Resources\TeamMembers\Tables;

use App\Models\TeamMember;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TeamMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('Ảnh')->circular(),
                TextColumn::make('name')
                    ->label('Họ tên')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('position')
                    ->label('Chức vụ')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->toggleable(),
                // Số bài cẩm nang đã đăng đứng tên người này (có ≥ 1 bài thì có trang tác giả)
                TextColumn::make('bai_viet_count')
                    ->label('Bài cẩm nang')
                    ->counts(['baiViet' => fn ($q) => $q->dangHienThi()])
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'warning'),
                TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ]),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([
                Action::make('trangTacGia')
                    ->label('Trang tác giả')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (TeamMember $record): bool => $record->status === 'published' && filled($record->slug) && $record->bai_viet_count > 0)
                    ->url(fn (TeamMember $record): string => rtrim((string) config('app.frontend_url', 'https://psvtravel.com'), '/').'/tac-gia/'.$record->slug)
                    ->openUrlInNewTab(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
