<?php

namespace App\Filament\Resources\VisaCases\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Models\VisaChecklist;
use Modules\Visa\Models\VisaCountry;
use Modules\Visa\Models\VisaProvider;

class VisaCaseForm
{
    /** File scan khách gửi: ảnh, PDF, Word, Excel. */
    public const LOAI_FILE = [
        'image/jpeg', 'image/png', 'image/webp', 'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Khách hàng')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Họ tên (như hộ chiếu)')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Điện thoại / Zalo')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        DatePicker::make('birth_date')
                            ->label('Ngày sinh')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        TextInput::make('passport_no')
                            ->label('Số hộ chiếu')
                            ->maxLength(30),
                        DatePicker::make('passport_expiry')
                            ->label('Hộ chiếu hết hạn')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->live()
                            ->helperText(fn (Get $get) => self::canhBaoHoChieu($get)),
                        TextInput::make('group_name')
                            ->label('Nhóm / đi cùng')
                            ->placeholder('VD: Gia đình chị Lan (4 người)')
                            ->helperText('Cả nhà / cả đoàn ghi cùng một tên để lọc ra đủ người.')
                            ->maxLength(255),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Visa')
                    ->schema([
                        TextInput::make('country')
                            ->label('Nước')
                            ->required()
                            ->maxLength(100)
                            ->datalist(fn () => self::danhSachNuoc())
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::tuChonMau($get, $set)),
                        Select::make('purpose')
                            ->label('Mục đích')
                            ->options(VisaCase::MUC_DICH)
                            ->default('du_lich')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::tuChonMau($get, $set)),
                        Select::make('profile')
                            ->label('Đối tượng')
                            ->options(VisaCase::DOI_TUONG)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::tuChonMau($get, $set)),
                        DatePicker::make('travel_date')
                            ->label('Ngày dự kiến đi')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->live(),
                        Select::make('visa_provider_id')
                            ->label('Đơn vị nộp / đối tác visa')
                            ->options(fn () => VisaProvider::dangHoatDong()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('PSV tự nộp'),
                        // Chỉ admin (quyền xem mọi hồ sơ) được giao / chuyển hồ sơ.
                        // Nhân viên thấy ô khoá; trang tạo / sửa cũng bỏ giá trị
                        // gửi lên nếu không có quyền — không tin vào ô bị khoá.
                        Select::make('assigned_to')
                            ->label('Nhân viên phụ trách')
                            ->options(fn () => self::nhanVienVisa())
                            ->searchable()
                            ->placeholder('Chưa ai nhận')
                            ->default(fn () => auth()->id())
                            ->disabled(fn () => ! auth()->user()?->can('giao', VisaCase::class))
                            ->helperText(fn () => auth()->user()?->can('giao', VisaCase::class) ? null : 'Chỉ quản trị viên được chuyển hồ sơ cho người khác.'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Giấy tờ')
                    ->description('Chọn mẫu để chép danh sách giấy tờ, rồi thêm / bớt / sửa cho đúng case này. Sửa ở đây không làm đổi mẫu.')
                    ->schema([
                        Select::make('visa_checklist_id')
                            ->label('Mẫu checklist')
                            ->options(fn () => VisaChecklist::dangDung()->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->helperText('Nhập nước + mục đích + đối tượng là máy tự chọn mẫu khớp nhất (nếu có).')
                            ->afterStateUpdated(fn ($state, Get $get, Set $set) => self::chepMau($state, $get, $set)),
                        Text::make(fn (Get $get) => VisaChecklist::find($get('visa_checklist_id'))?->note ?? '')
                            ->color('warning')
                            ->visible(fn (Get $get) => filled(VisaChecklist::find($get('visa_checklist_id'))?->note)),
                        Text::make(fn (Get $get) => self::tomTatGiayTo($get('checklist')))
                            ->weight('bold'),
                        Repeater::make('checklist')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Giấy tờ')->markAsRequired(),
                                TableColumn::make('Ghi chú'),
                                TableColumn::make('Tình trạng')->width('17rem'),
                            ])
                            ->schema([
                                TextInput::make('ten')
                                    ->required(),
                                TextInput::make('ghi_chu'),
                                ToggleButtons::make('trang_thai')
                                    ->options(VisaCase::GIAY)
                                    ->colors(['thieu' => 'danger', 'da_nhan' => 'success', 'khong_can' => 'gray'])
                                    ->default('thieu')
                                    ->grouped()
                                    ->live(),
                                Hidden::make('nhom'),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Thêm giấy tờ')
                            ->reorderable(),
                        FileUpload::make('files')
                            ->label('File scan / ảnh giấy tờ khách gửi')
                            ->helperText('Chỉ người có quyền hồ sơ visa mới mở được. Ảnh, PDF, Word, Excel — tối đa 10MB mỗi file.')
                            ->multiple()
                            ->disk('rieng')
                            ->directory('ho-so-visa')
                            ->visibility('private')
                            ->acceptedFileTypes(self::LOAI_FILE)
                            ->maxSize(10240)
                            ->maxFiles(50)
                            ->storeFileNamesIn('file_names')
                            ->downloadable()
                            ->openable()
                            ->reorderable()
                            ->panelLayout('grid'),
                    ])
                    ->columnSpanFull(),

                Section::make('Tiến độ')
                    ->schema([
                        Select::make('status')
                            ->label('Trạng thái')
                            ->options(VisaCase::TRANG_THAI)
                            ->default('moi')
                            ->selectablePlaceholder(false)
                            ->required(),
                        DatePicker::make('submitted_on')
                            ->label('Ngày nộp')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DateTimePicker::make('appointment_at')
                            ->label('Lịch hẹn (nộp / lăn tay / phỏng vấn)')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i'),
                        DatePicker::make('result_expected_on')
                            ->label('Hẹn trả kết quả')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('result_on')
                            ->label('Ngày có kết quả')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('visa_expiry')
                            ->label('Visa hết hạn')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Tiền')
                    ->description('Mỗi case một giá — ghi đúng số đã báo khách.')
                    ->schema([
                        TextInput::make('fee')
                            ->label('Thu khách')
                            ->numeric()->minValue(0)->default(0)->suffix('₫')
                            ->live(onBlur: true),
                        TextInput::make('cost')
                            ->label('Chi phí (lãnh sự, đối tác…)')
                            ->numeric()->minValue(0)->default(0)->suffix('₫'),
                        TextInput::make('paid')
                            ->label('Khách đã trả')
                            ->numeric()->minValue(0)->default(0)->suffix('₫')
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get) => 'Còn phải thu: '.number_format(max(0, (int) $get('fee') - (int) $get('paid')), 0, ',', '.').'₫'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Textarea::make('customer_note')
                    ->label('Lời nhắn của khách (gửi kèm lúc nộp trên web)')
                    ->rows(3)
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (?VisaCase $record) => filled($record?->customer_note))
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label('Ghi chú nội bộ')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    /** Nước gợi ý: nước đã có mẫu, đã có hồ sơ, hoặc đang bán visa trên web. */
    private static function danhSachNuoc(): array
    {
        return collect()
            ->merge(VisaChecklist::query()->whereNotNull('country')->distinct()->pluck('country'))
            ->merge(VisaCase::query()->distinct()->pluck('country'))
            ->merge(VisaCountry::query()->pluck('name'))
            ->filter()->unique()->sort()->values()->all();
    }

    /** Người được giao hồ sơ: ai có quyền xem hồ sơ visa (qua vai trò hoặc trực tiếp). */
    private static function nhanVienVisa(): array
    {
        return User::query()
            ->where(fn ($q) => $q
                ->whereHas('roles.permissions', fn ($p) => $p->where('name', 'ViewAny:VisaCase'))
                ->orWhereHas('permissions', fn ($p) => $p->where('name', 'ViewAny:VisaCase')))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function canhBaoHoChieu(Get $get): ?string
    {
        $hetHan = $get('passport_expiry');
        if (! $hetHan) {
            return null;
        }
        $hs = new VisaCase(['passport_expiry' => $hetHan, 'travel_date' => $get('travel_date')]);

        return $hs->hoChieuSapHetHan() ? '⚠ Hộ chiếu còn hạn dưới 6 tháng tính từ ngày đi — đa số nước sẽ từ chối.' : null;
    }

    private static function tomTatGiayTo(?array $checklist): string
    {
        $hs = new VisaCase(['checklist' => array_values($checklist ?? [])]);
        [$co, $can] = $hs->tienDoGiayTo();

        return $can ? "Đã nhận {$co}/{$can} giấy tờ" : 'Chưa có danh sách giấy tờ';
    }

    /**
     * Đổi nước / mục đích / đối tượng → tự chọn mẫu khớp nhất. Chỉ tự đổi khi
     * chưa đánh dấu nhận giấy nào, để không xoá công sức đã làm.
     */
    private static function tuChonMau(Get $get, Set $set): void
    {
        if (self::daNhanGiay($get('checklist'))) {
            return;
        }
        $mau = VisaChecklist::timMau($get('country'), $get('purpose'), $get('profile'));
        if ($mau && (int) $get('visa_checklist_id') !== $mau->id) {
            $set('visa_checklist_id', $mau->id);
            $set('checklist', VisaCase::chepMau($mau));
        }
    }

    /**
     * Chọn mẫu bằng tay. Chưa nhận giấy nào → thay hẳn danh sách. Đã nhận vài
     * giấy → giữ nguyên, chỉ thêm giấy của mẫu mới mà danh sách chưa có.
     */
    private static function chepMau($id, Get $get, Set $set): void
    {
        $mau = $id ? VisaChecklist::find($id) : null;
        if (! $mau) {
            return;
        }
        $moi = VisaCase::chepMau($mau);
        $hienTai = array_values($get('checklist') ?? []);

        if (! self::daNhanGiay($hienTai)) {
            $set('checklist', $moi);

            return;
        }

        $daCo = collect($hienTai)->map(fn ($g) => mb_strtolower(trim($g['ten'] ?? '')));
        $them = collect($moi)->reject(fn ($g) => $daCo->contains(mb_strtolower(trim($g['ten']))));
        $set('checklist', [...$hienTai, ...$them->values()->all()]);
    }

    private static function daNhanGiay(?array $checklist): bool
    {
        return collect($checklist ?? [])->contains(fn ($g) => ($g['trang_thai'] ?? null) === 'da_nhan');
    }
}
