<?php

namespace App\Filament\Resources\Attendances;

use App\Filament\Pages\ThongKeDoanhSo;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Models\Attendance;
use App\Services\ChamCong\ChamCong;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Bảng chấm công của mọi nhân viên (quản lý): xem ảnh / vị trí từng lần chấm,
 * duyệt lý do trễ / về sớm / ngoài công ty / bổ sung công.
 */
class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'ngày chấm công';

    protected static ?string $pluralModelLabel = 'Bảng chấm công';

    protected static string|\UnitEnum|null $navigationGroup = 'Nhân sự';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'bang-cham-cong';

    public static function getNavigationBadge(): ?string
    {
        $so = Attendance::where('review_status', 'cho_duyet')->count();

        return $so ? (string) $so : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Lý do / bổ sung công chờ duyệt';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user:id,name', 'nguoiDuyet:id,name']))
            ->columns(BangChamCongCot::cot(true))
            ->defaultSort('work_date', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Nhân viên')
                    ->relationship('user', 'name', fn (Builder $query) => $query->whereHas('roles.permissions'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('thang')
                    ->label('Tháng')
                    ->options(ThongKeDoanhSo::cacThang())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereBetween('work_date', [
                            Carbon::createFromFormat('!Y-m', $data['value'])->startOfMonth()->toDateString(),
                            Carbon::createFromFormat('!Y-m', $data['value'])->endOfMonth()->toDateString(),
                        ])
                        : $query),
                Filter::make('tre_som')->label('Đi trễ / về sớm')->toggle()
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q->where('late_minutes', '>', 0)->orWhere('early_minutes', '>', 0))),
                Filter::make('ngoai')->label('Ngoài công ty / không rõ vị trí')->toggle()
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q->whereIn('in_location', ['ngoai', 'khong_ro'])->orWhereIn('out_location', ['ngoai', 'khong_ro']))),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->deferFilters(false)
            ->recordActions([
                self::nutXem(),
                self::nutDuyet(),
                self::nutGo(),
            ])
            ->paginated([25, 50, 100]);
    }

    /** Ảnh đăng ký cạnh ảnh lúc chấm + bản đồ, để đối chiếu. */
    public static function nutXem(): Action
    {
        return Action::make('xem')
            ->label('Xem')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (Attendance $record) => $record->user?->name.' — '.$record->work_date->format('d/m/Y'))
            ->modalContent(fn (Attendance $record) => view('filament.cham-cong.chi-tiet', ['a' => $record]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Đóng');
    }

    public static function nutDuyet(): Action
    {
        return Action::make('duyet')
            ->label('Duyệt')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('warning')
            ->visible(fn (Attendance $record) => $record->review_status !== null)
            ->modalHeading(fn (Attendance $record) => 'Duyệt lý do — '.$record->user?->name.' '.$record->work_date->format('d/m/Y'))
            ->modalDescription(fn (Attendance $record) => collect([
                $record->in_reason ? 'Vào ('.$record->in_at?->format('H:i').'): '.$record->in_reason : null,
                $record->out_reason ? 'Ra ('.$record->out_at?->format('H:i').'): '.$record->out_reason : null,
            ])->filter()->implode(' · '))
            ->fillForm(fn (Attendance $record) => ['ket_qua' => $record->review_status === 'tu_choi' ? 'tu_choi' : 'chap_nhan', 'ghi_chu' => $record->review_note])
            ->schema([
                Radio::make('ket_qua')->label('Kết quả')->options([
                    'chap_nhan' => 'Chấp nhận (trễ / sớm có phép; bổ sung công được tính)',
                    'tu_choi' => 'Không chấp nhận',
                ])->required(),
                Textarea::make('ghi_chu')->label('Ghi chú (nhân viên thấy)')->rows(2),
            ])
            ->modalSubmitActionLabel('Lưu')
            ->action(function (Attendance $record, array $data) {
                app(ChamCong::class)->duyet($record, $data['ket_qua'] === 'chap_nhan', $data['ghi_chu'] ?? null, auth()->user());
                Notification::make()->title('Đã lưu kết quả duyệt')->success()->send();
            });
    }

    /**
     * Gỡ lượt chấm bấm nhầm (VD vừa chấm vào đã bấm chấm ra). Chỉ người có quyền
     * "Gỡ lượt chấm công bấm nhầm" (Remove:Attendance) — mặc định Admin.
     */
    public static function nutGo(): Action
    {
        return Action::make('go')
            ->label('Gỡ')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (Attendance $record) => (bool) auth()->user()?->can('Remove:Attendance') && ($record->in_at || $record->out_at))
            ->authorize(fn () => (bool) auth()->user()?->can('Remove:Attendance'))
            ->modalHeading(fn (Attendance $record) => 'Gỡ lượt chấm — '.$record->user?->name.' '.$record->work_date->format('d/m/Y'))
            ->modalDescription('Dùng khi bấm nhầm (VD vừa chấm vào đã bấm chấm ra). Giờ, vị trí và ảnh của lượt bị gỡ sẽ xoá; nhân viên thấy ghi chú và lý do. '
                .'Gỡ trong ngày thì nhân viên chấm lại bình thường; ngày đã qua thì nhân viên bấm "Xin bổ sung công".')
            ->fillForm(fn (Attendance $record) => ['chon' => $record->out_at ? 'ra' : 'vao'])
            ->schema(fn (Attendance $record) => [
                Radio::make('chon')->label('Gỡ')->options(array_filter([
                    'ra' => $record->out_at ? 'Giờ ra '.$record->out_at->format('H:i') : null,
                    'vao' => $record->in_at ? 'Giờ vào '.$record->in_at->format('H:i') : null,
                    'ca_hai' => $record->in_at && $record->out_at ? 'Cả giờ vào và giờ ra' : null,
                ]))->required(),
                Textarea::make('ly_do')->label('Lý do (nhân viên thấy)')->placeholder('VD: bấm nhầm chấm ra ngay sau khi chấm vào')
                    ->required()->minLength(3)->maxLength(500)->rows(2),
            ])
            ->modalSubmitActionLabel('Gỡ lượt chấm')
            ->action(function (Attendance $record, array $data, Action $action) {
                try {
                    app(ChamCong::class)->goLuot($record, $data['chon'], $data['ly_do'], auth()->user());
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();
                }
                Notification::make()->title('Đã gỡ lượt chấm')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendances::route('/'),
        ];
    }
}
