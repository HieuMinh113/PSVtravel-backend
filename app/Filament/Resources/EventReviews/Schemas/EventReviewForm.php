<?php

namespace App\Filament\Resources\EventReviews\Schemas;

use App\Models\EventReview;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EventReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Thông tin khách gửi: chỉ đọc
            TextInput::make('event.title')->label('Gói')->disabled(),
            TextInput::make('customer_name')->label('Tên khách')->disabled(),
            TextInput::make('rating')
                ->label('Số sao')
                ->formatStateUsing(fn (?int $state): string => $state ? str_repeat('★', $state).str_repeat('☆', 5 - $state) : '—')
                ->disabled(),
            Textarea::make('content')
                ->label('Nội dung khách viết')
                ->rows(5)
                ->disabled()
                ->columnSpanFull(),

            Select::make('status')
                ->label('Trạng thái')
                ->options(EventReview::TRANG_THAI)
                ->default('pending')
                ->required(),
            Textarea::make('admin_reply')
                ->label('Phản hồi của công ty (tuỳ chọn)')
                ->helperText('Hiển thị công khai dưới đánh giá của khách.')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }
}
