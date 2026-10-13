<?php

namespace App\Filament\Resources\JobApplications\Tables;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobApplication;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Nhận lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Ứng viên')
                    ->weight('semibold')
                    ->description(fn (JobApplication $r) => $r->email)
                    ->searchable(['full_name', 'email']),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->copyable()
                    ->copyMessage('Đã chép số điện thoại')
                    ->searchable(),
                TextColumn::make('job_title')
                    ->label('Vị trí')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('tai_lieu')
                    ->label('Đính kèm')
                    ->state(fn (JobApplication $r) => array_values(array_filter([
                        'CV',
                        $r->portfolio_path ? 'Portfolio' : null,
                        $r->portfolio_url ? 'Link portfolio' : null,
                    ])))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => JobApplication::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => JobApplication::MAU_TRANG_THAI[$state] ?? 'gray'),
                TextColumn::make('handledBy.name')
                    ->label('Người xử lý')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(JobApplication::TRANG_THAI),
                SelectFilter::make('job_posting_id')
                    ->label('Vị trí')
                    ->relationship('jobPosting', 'title')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('cv')
                    ->label('Mở CV')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (JobApplication $r) => $r->linkTep('cv'), shouldOpenInNewTab: true),
                ViewAction::make(),
                JobApplicationResource::hanhDongXuLy(),
                DeleteAction::make()
                    ->modalDescription('Xoá hồ sơ sẽ xoá luôn CV và portfolio của ứng viên, không khôi phục được.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Xoá các hồ sơ đã chọn cùng CV / portfolio của họ, không khôi phục được.'),
                ]),
            ]);
    }
}
