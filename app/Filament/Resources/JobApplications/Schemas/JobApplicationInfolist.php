<?php

namespace App\Filament\Resources\JobApplications\Schemas;

use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Models\JobApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Ứng viên')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('full_name')->label('Họ tên')->weight('semibold'),
                        TextEntry::make('job_title')
                            ->label('Vị trí ứng tuyển')
                            ->url(fn (JobApplication $r) => $r->job_posting_id && $r->jobPosting && ! $r->jobPosting->trashed()
                                ? JobPostingResource::getUrl('edit', ['record' => $r->job_posting_id]) : null),
                        TextEntry::make('phone')
                            ->label('Điện thoại / Zalo')
                            ->copyable()
                            ->copyMessage('Đã chép số điện thoại'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->copyable()
                            ->copyMessage('Đã chép email'),
                        TextEntry::make('created_at')->label('Gửi lúc')->dateTime('d/m/Y H:i'),
                        TextEntry::make('message')
                            ->label('Lời nhắn của ứng viên')
                            ->placeholder('Không có')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Hồ sơ đính kèm')
                    ->schema([
                        TextEntry::make('cv_name')
                            ->label('CV')
                            ->default('CV.pdf')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->url(fn (JobApplication $r) => $r->linkTep('cv'), shouldOpenInNewTab: true),
                        TextEntry::make('portfolio_name')
                            ->label('Portfolio (PDF)')
                            ->placeholder('Không gửi')
                            ->icon('heroicon-o-photo')
                            ->color('primary')
                            ->url(fn (JobApplication $r) => $r->linkTep('portfolio'), shouldOpenInNewTab: true),
                        TextEntry::make('portfolio_url')
                            ->label('Link portfolio')
                            ->placeholder('Không gửi')
                            ->color('primary')
                            ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                            ->helperText('Link ứng viên tự dán — kiểm tra kỹ trước khi mở.'),
                    ]),
                Section::make('Xử lý')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Trạng thái')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => JobApplication::TRANG_THAI[$state] ?? $state)
                            ->color(fn (string $state) => JobApplication::MAU_TRANG_THAI[$state] ?? 'gray'),
                        TextEntry::make('handledBy.name')
                            ->label('Người xử lý')
                            ->placeholder('Chưa ai xử lý')
                            ->suffix(fn (JobApplication $r) => $r->handled_at ? ' · '.$r->handled_at->format('d/m/Y H:i') : ''),
                        TextEntry::make('admin_note')
                            ->label('Ghi chú nội bộ')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
