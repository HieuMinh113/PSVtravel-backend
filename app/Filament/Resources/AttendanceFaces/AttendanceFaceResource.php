<?php

namespace App\Filament\Resources\AttendanceFaces;

use App\Filament\Pages\BangCongThang;
use App\Filament\Resources\AttendanceFaces\Pages\ListAttendanceFaces;
use App\Models\Attendance;
use App\Models\AttendanceFace;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Khuôn mặt nhân viên đã đăng ký chấm công. "Cho đăng ký lại" xoá khuôn mặt
 * cũ để nhân viên tự chụp lại ở lần chấm sau (đổi kiểu tóc, kính, ảnh lỗi…).
 */
class AttendanceFaceResource extends Resource
{
    protected static ?string $model = AttendanceFace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static ?string $modelLabel = 'khuôn mặt';

    protected static ?string $pluralModelLabel = 'Khuôn mặt nhân viên';

    protected static string|\UnitEnum|null $navigationGroup = 'Chấm công';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'khuon-mat-nhan-vien';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user:id,name,email'))
            ->description(function () {
                $chua = BangCongThang::nhanVien()->whereDoesntHave('khuonMat')->orderBy('name')->pluck('name');

                return $chua->isEmpty() ? 'Mọi nhân viên đã đăng ký khuôn mặt.' : 'Chưa đăng ký: '.$chua->implode(', ');
            })
            ->columns([
                ImageColumn::make('photo')->label('Ảnh')->disk(Attendance::DIA)->visibility('private')->circular()->imageSize(56),
                TextColumn::make('user.name')->label('Nhân viên')->searchable()->weight('bold')
                    ->description(fn (AttendanceFace $r) => $r->user?->email),
                TextColumn::make('consented_at')->label('Đồng ý dùng ảnh khuôn mặt lúc')->dateTime('H:i d/m/Y')->sortable(),
            ])
            ->defaultSort('consented_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->label('Cho đăng ký lại')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->modalHeading(fn (AttendanceFace $r) => 'Cho '.$r->user?->name.' đăng ký lại khuôn mặt?')
                    ->modalDescription('Xoá khuôn mặt cũ. Lần chấm công sau, nhân viên tự chụp đăng ký lại.')
                    ->modalSubmitActionLabel('Xoá để đăng ký lại')
                    ->successNotificationTitle('Đã mở lại đăng ký khuôn mặt'),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceFaces::route('/'),
        ];
    }
}
