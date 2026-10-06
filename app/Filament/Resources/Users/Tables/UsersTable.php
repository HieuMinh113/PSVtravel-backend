<?php

namespace App\Filament\Resources\Users\Tables;


use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('Ảnh')
                    ->circular(),
                TextColumn::make('name')
                    ->label('Họ và tên')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('roles.name')
                    ->label('Vai trò')
                    ->badge()
                    ->separator(',')
                    ->placeholder('Chưa gán'),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('email_verified_at')
                    ->label('Đã xác thực')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Chưa')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('roles')
                    ->label('Vai trò')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                // Nhân viên mất điện thoại (và mất mã khôi phục) → super admin tắt
                // 2FA giúp để họ đăng nhập rồi thiết lập lại. Chỉ super admin thấy.
                Action::make('tat2fa')
                    ->label('Tắt 2FA')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tắt xác thực 2 lớp?')
                    ->modalDescription('Dùng khi người này mất điện thoại. Nếu là quản trị viên, họ sẽ phải thiết lập lại 2FA ngay lần đăng nhập tới.')
                    ->visible(fn (User $record): bool => filled($record->getAppAuthenticationSecret())
                        && (bool) auth()->user()?->hasRole(config('filament-shield.super_admin.name')))
                    ->action(function (User $record): void {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);
                        $record->save();
                        activity('user')->performedOn($record)->log('Tắt 2FA từ trang quản trị');
                        Notification::make()->title('Đã tắt 2FA cho '.$record->email)->success()->send();
                    }),
            ]);
    }
}