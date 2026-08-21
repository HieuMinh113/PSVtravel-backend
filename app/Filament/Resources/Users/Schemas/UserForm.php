<?php

namespace App\Filament\Resources\Users\Schemas;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Họ và tên')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Mật khẩu')
                    ->password()
                    ->revealable()
                    ->helperText('Để trống nếu không muốn đổi mật khẩu')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->minLength(8),

                Select::make('roles')
                    ->label('Vai trò')
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: function ($query) {
                            /** @var \App\Models\User|null $user */
                            $user = Auth::user();

                            return $user?->hasRole('super_admin')
                                ? $query
                                : $query->where('name', '!=', 'super_admin');
                        },
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Nhân viên nội bộ cần có vai trò admin hoặc staff mới vào được trang quản trị'),
                TextInput::make('phone')
                    ->label('Số điện thoại')
                    ->tel()
                    ->maxLength(255),
                FileUpload::make('avatar')
                    ->label('Ảnh đại diện')
                    ->image()
                    ->directory('avatars')
                    ->disk('public'),

                Select::make('locale')
                    ->label('Ngôn ngữ')
                    ->options(['vi' => 'Tiếng Việt', 'en' => 'English'])
                    ->default('vi')
                    ->required(),
                TextInput::make('loyalty_points')
                    ->label('Điểm tích luỹ')
                    ->numeric()
                    ->default(0)
                    ->required(),

                DateTimePicker::make('email_verified_at')
                    ->label('Thời điểm xác thực email')
                    ->helperText('Để trống nghĩa là email chưa xác thực'),
            ]);
    }
}