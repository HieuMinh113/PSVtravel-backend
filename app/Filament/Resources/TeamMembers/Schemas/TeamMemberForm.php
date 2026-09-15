<?php

namespace App\Filament\Resources\TeamMembers\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TeamMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Họ tên')
                    ->required()
                    ->maxLength(255),
                TextInput::make('position')
                    ->label('Chức vụ')
                    ->placeholder('VD: Giám đốc Vận hành')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email liên hệ')
                    ->email()
                    ->maxLength(255),

                FileUpload::make('photo')
                    ->label('Ảnh')
                    ->image()
                    ->avatar()
                    ->imageEditor()
                    ->directory('team')
                    ->disk('public')
                    ->helperText('Nên dùng ảnh chân dung, cắt vuông cho đẹp')
                    ->columnSpanFull(),

                Textarea::make('bio')
                    ->label('Giới thiệu ngắn')
                    ->helperText('Không bắt buộc — vài dòng giới thiệu, hiện ở phần chữ bên cạnh ảnh.')
                    ->rows(4)
                    ->maxLength(600)
                    ->columnSpanFull(),

                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('published')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Thứ tự hiển thị')
                    ->helperText('Số nhỏ hiện trước')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
