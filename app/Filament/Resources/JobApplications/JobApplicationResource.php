<?php

namespace App\Filament\Resources\JobApplications;

use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Filament\Resources\JobApplications\Pages\ViewJobApplication;
use App\Filament\Resources\JobApplications\Schemas\JobApplicationInfolist;
use App\Filament\Resources\JobApplications\Tables\JobApplicationsTable;
use App\Models\JobApplication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Hồ sơ ứng tuyển ứng viên gửi từ trang chi tiết tin tuyển dụng trên website
 * (CV + portfolio PDF). Không ai tạo tay; người xử lý mở CV, gọi / email cho
 * ứng viên rồi cập nhật trạng thái.
 */
class JobApplicationResource extends Resource
{
    protected static ?string $model = JobApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $modelLabel = 'hồ sơ ứng tuyển';

    protected static ?string $pluralModelLabel = 'Hồ sơ ứng tuyển';

    protected static ?string $navigationLabel = 'Hồ sơ ứng tuyển';

    protected static ?string $slug = 'ho-so-ung-tuyen';

    protected static string|\UnitEnum|null $navigationGroup = 'Nội dung';

    // Ngay dưới "Tuyển dụng"
    protected static ?int $navigationSort = 66;

    public static function canCreate(): bool
    {
        return false;
    }

    // Số hồ sơ mới chưa ai xử lý — hiện cạnh menu, tự cập nhật không cần F5
    public static function getNavigationBadge(): ?string
    {
        $so = JobApplication::query()->where('status', 'moi')->count();

        return $so > 0 ? (string) $so : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Hồ sơ mới chưa xử lý';
    }

    public static function infolist(Schema $schema): Schema
    {
        return JobApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobApplicationsTable::configure($table);
    }

    /** Nút "Cập nhật xử lý": đổi trạng thái + ghi chú, ghi lại ai xử lý lúc nào. */
    public static function hanhDongXuLy(): Action
    {
        return Action::make('xuLy')
            ->label('Cập nhật xử lý')
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->authorize('update')
            ->modalHeading(fn (JobApplication $record) => 'Xử lý hồ sơ: '.$record->full_name)
            ->modalWidth('lg')
            ->fillForm(fn (JobApplication $record) => [
                'status' => $record->status === 'moi' ? 'da_lien_he' : $record->status,
                'admin_note' => $record->admin_note,
            ])
            ->schema([
                Select::make('status')
                    ->label('Trạng thái')
                    ->options(JobApplication::TRANG_THAI)
                    ->native(false)
                    ->required(),
                Textarea::make('admin_note')
                    ->label('Ghi chú nội bộ')
                    ->placeholder('VD: đã gọi 14/10, hẹn phỏng vấn 9h thứ Sáu…')
                    ->rows(4)
                    ->maxLength(2000),
            ])
            ->action(function (JobApplication $record, array $data): void {
                $record->forceFill([
                    'status' => $data['status'],
                    'admin_note' => $data['admin_note'] ?? null,
                    'handled_by' => Auth::id(),
                    'handled_at' => now(),
                ])->save();

                Notification::make()->title('Đã cập nhật hồ sơ')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobApplications::route('/'),
            'view' => ViewJobApplication::route('/{record}'),
        ];
    }
}
