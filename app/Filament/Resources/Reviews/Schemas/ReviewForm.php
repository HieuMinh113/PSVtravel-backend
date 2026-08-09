<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tour_id')
                    ->label('Tour')
                    ->relationship('tour', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('user_id')
                    ->label('Tài khoản khách')
                    ->helperText('Để trống nếu khách vãng lai')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                TextInput::make('customer_name')
                    ->label('Tên người đánh giá')
                    ->required()
                    ->maxLength(255),
                Select::make('rating')
                    ->label('Số sao')
                    ->options([
                        1 => '1 sao',
                        2 => '2 sao',
                        3 => '3 sao',
                        4 => '4 sao',
                        5 => '5 sao',
                    ])
                    ->required(),

                Textarea::make('content')
                    ->label('Nội dung đánh giá')
                    ->rows(4)
                    ->columnSpanFull(),

                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Chờ duyệt',
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                    ])
                    ->default('pending')
                    ->required()
                    ->disabledOn('edit')
                    ->helperText('Đổi trạng thái bằng nút Duyệt / Từ chối ở danh sách'),

                Textarea::make('admin_reply')
                    ->label('Phản hồi của công ty')
                    ->helperText('Hiện công khai dưới đánh giá của khách')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}