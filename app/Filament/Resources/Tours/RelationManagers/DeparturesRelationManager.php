<?php

namespace App\Filament\Resources\Tours\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Modules\Alliance\Models\AllianceDeparture;
use Modules\Alliance\Services\DongBoLienMinh;
use Modules\Tour\Models\TourDeparture;
use Modules\Tour\Services\HanNopVisa;

class DeparturesRelationManager extends RelationManager
{
    protected static string $relationship = 'departures';

    protected static ?string $title = 'Lịch khởi hành';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('start_date')
                ->label('Ngày khởi hành')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->minDate(now()->startOfDay())
                ->maxDate(now()->addYears(3))
                ->helperText('Chỉ chọn được từ hôm nay trở đi. Mỗi tour chỉ có một đợt cho một ngày.')
                ->required()
                // Bảng tour_departures có ràng buộc duy nhất (tour_id, start_date).
                // Trước đây form không kiểm tra nên chọn trùng ngày là để cơ sở dữ
                // liệu ném lỗi ra — người dùng nhận nguyên trang 500 thay vì một
                // câu thông báo. Kiểm ở đây để báo ngay tại ô nhập.
                ->rules([
                    function (?TourDeparture $record): \Closure {
                        $tourId = $this->getOwnerRecord()->getKey();

                        return function (string $attribute, $value, \Closure $fail) use ($tourId, $record) {
                            if (! $value) {
                                return;
                            }

                            $daCo = TourDeparture::query()
                                ->where('tour_id', $tourId)
                                ->whereDate('start_date', $value)
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($daCo) {
                                $fail('Tour này đã có đợt khởi hành ngày '
                                    .Carbon::parse($value)->format('d/m/Y')
                                    .'. Chọn ngày khác hoặc sửa đợt đã có.');
                            }
                        };
                    },
                ]),
            // Hạn nộp visa riêng của đợt này (đối tác chốt ngày cụ thể). Trống =
            // theo quy tắc chung của tour "trước ngày khởi hành N ngày".
            DatePicker::make('visa_deadline')
                ->label('Hạn nộp hồ sơ visa (riêng đợt này)')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->placeholder(fn (): string => $this->quyTacVisa() ? 'Theo quy tắc chung' : 'Không đặt')
                ->helperText(fn (): string => ($this->quyTacVisa()
                    ? 'Để trống = '.$this->quyTacVisa().' (đặt ở thông tin tour).'
                    : 'Tour chưa đặt quy tắc chung.')
                    .(($n = HanNopVisa::soNgayTheoNuoc($this->getOwnerRecord()->country, $this->getOwnerRecord()->visa_kind)) && $this->getOwnerRecord()->type === 'abroad'
                        ? " Hạn theo nước: trước ngày bay {$n} ngày."
                        : '')
                    .' Lấy hạn sớm nhất; qua hạn thì đợt tự ẩn trên website.')
                ->before('start_date')
                ->validationMessages(['before' => 'Hạn nộp visa phải trước ngày khởi hành.'])
                ->visible(fn (?TourDeparture $record): bool => $this->getOwnerRecord()->type === 'abroad' || filled($record?->visa_deadline)),
            TextInput::make('price_override')
                ->label('Giá riêng đợt này')
                ->helperText('Để trống nếu dùng giá mặc định của tour')
                ->numeric()
                ->suffix('₫'),
            // Giá trẻ em / em bé riêng của đợt (sheet đối tác ghi giá từng ngày)
            TextInput::make('child_price_override')
                ->label('Giá trẻ em riêng đợt này')
                ->helperText('Để trống = giá trẻ em của tour')
                ->numeric()
                ->suffix('₫'),
            TextInput::make('infant_price_override')
                ->label('Giá em bé riêng đợt này')
                ->helperText('Để trống = giá em bé của tour')
                ->numeric()
                ->suffix('₫'),
            TextInput::make('seats_total')
                ->label('Tổng số chỗ')
                ->numeric()
                ->default(0)
                ->minValue(1)
                ->live(onBlur: true)
                ->required(),
            TextInput::make('seats_left')
                ->label('Số chỗ còn')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->rules([
                    fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if ((int) $value > (int) $get('seats_total')) {
                            $fail('Số chỗ còn không được lớn hơn tổng số chỗ.');
                        }
                    },
                ])
                ->required(),
            Select::make('status')
                ->label('Trạng thái')
                ->options([
                    'open' => 'Còn nhận',
                    'closed' => 'Đã đóng',
                    'full' => 'Hết chỗ',
                ])
                ->default('open')
                ->required(),
        ]);
    }

    /** "trước ngày khởi hành 30 ngày" — quy tắc chung của tour, null nếu chưa đặt */
    private function quyTacVisa(): ?string
    {
        $n = (int) $this->getOwnerRecord()->visa_deadline_days;

        return $n > 0 ? "trước ngày khởi hành {$n} ngày" : null;
    }

    /** @var array<string,AllianceDeparture>|null ngày => ngày đi trong sheet (nạp 1 lần cho cả bảng) */
    private ?array $ngayLienMinh = null;

    private function ngayLienMinh(): array
    {
        return $this->ngayLienMinh ??= AllianceDeparture::query()
            ->where('alliance_tour_id', $this->getOwnerRecord()->alliance_tour_id)
            ->whereNotNull('departure_date')
            ->get()
            ->keyBy(fn ($d) => $d->departure_date->toDateString())
            ->all();
    }

    /** "Theo sheet: còn 5" / "Theo sheet: còn nhận" / "Theo sheet: hết chỗ" */
    private function choTheoSheet(TourDeparture $dot): string
    {
        $d = $this->ngayLienMinh()[$dot->start_date->toDateString()] ?? null;
        if (! $d) {
            return 'Sheet không có ngày này';
        }

        return 'Theo sheet: '.($d->status === 'con_cho'
            ? ($d->seats_left !== null ? "còn {$d->seats_left}" : 'còn nhận')
            : mb_strtolower(AllianceDeparture::TRANG_THAI[$d->status] ?? $d->status));
    }

    /** Dưới cột giá riêng: giá trẻ em / em bé riêng, và giá đang theo sheet hay đã sửa tay. */
    private function moTaGia(TourDeparture $dot): ?string
    {
        $vnd = fn (?int $v) => $v !== null ? number_format($v, 0, ',', '.').'₫' : null;
        $phan = array_filter([
            $dot->child_price_override !== null ? 'Trẻ em '.$vnd($dot->child_price_override) : null,
            $dot->infant_price_override !== null ? 'Em bé '.$vnd($dot->infant_price_override) : null,
        ]);
        $d = $this->getOwnerRecord()->alliance_tour_id ? ($this->ngayLienMinh()[$dot->start_date->toDateString()] ?? null) : null;
        if ($d && $d->price && ! $d->price_net && $dot->price_override !== null) {
            $phan[] = $dot->price_override === $d->price ? 'theo sheet' : 'sửa tay (sheet '.$vnd($d->price).')';
        } elseif ($d && $d->price_net) {
            $phan[] = 'sheet ghi giá NET '.$vnd($d->price);
        }

        return $phan ? implode(' · ', $phan) : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_date')
                    ->label('Ngày khởi hành')
                    ->date('d/m/Y')
                    ->sortable(),
                // Hạn thực tế khách thấy: hạn riêng của đợt, không có thì theo quy tắc chung
                TextColumn::make('han_visa')
                    ->label('Hạn nộp visa')
                    ->state(fn (TourDeparture $record) => $record->hanVisa($this->getOwnerRecord()))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    // hạn sớm nhất trong: hạn riêng / quy tắc của tour / hạn theo nước (nhân viên visa)
                    ->description(fn (TourDeparture $record): ?string => HanNopVisa::nguonHan($record, $this->getOwnerRecord()))
                    ->color(fn (TourDeparture $record): ?string => match (true) {
                        ($n = TourDeparture::conNgay($record->hanVisa($this->getOwnerRecord()))) === null => null,
                        $n < 0 => 'danger',
                        $n <= 7 => 'warning',
                        default => null,
                    })
                    ->tooltip(fn (TourDeparture $record): ?string => ($n = TourDeparture::conNgay($record->hanVisa($this->getOwnerRecord()))) === null
                        ? null : ($n < 0 ? 'Đã qua hạn nộp visa — đợt này đã ẩn trên website' : "Còn {$n} ngày, qua hạn thì đợt tự ẩn trên website"))
                    ->visible(fn (): bool => $this->getOwnerRecord()->type === 'abroad'),
                TextColumn::make('price_override')
                    ->label('Giá riêng')
                    ->money('VND')
                    ->placeholder('—')
                    ->description(fn (TourDeparture $record): ?string => $this->moTaGia($record)),
                TextColumn::make('seats_total')->label('Tổng chỗ'),
                TextColumn::make('seats_left')->label('Còn')
                    ->description(fn (TourDeparture $record): ?string => $this->getOwnerRecord()->alliance_tour_id
                        ? $this->choTheoSheet($record)
                        : null),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    // Còn nhận nhưng quá hạn nộp visa → khách không thấy trên website
                    ->formatStateUsing(fn (string $state, TourDeparture $record): string => match (true) {
                        $state === 'closed' => 'Đã đóng',
                        $state === 'full' => 'Hết chỗ',
                        $record->quaHanVisa($this->getOwnerRecord()) => 'Ẩn trên web (quá hạn visa)',
                        default => 'Còn nhận',
                    })
                    ->color(fn (string $state, TourDeparture $record): string => match (true) {
                        $state === 'closed' => 'gray',
                        $state === 'full' => 'danger',
                        $record->quaHanVisa($this->getOwnerRecord()) => 'warning',
                        default => 'success',
                    }),
            ])
            ->defaultSort('start_date')
            ->headerActions([
                CreateAction::make()->label('Thêm đợt khởi hành'),
                // Tour đã nối với tour liên minh: thêm một lần mọi ngày đi trong
                // sheet đối tác mà web chưa có, kèm số chỗ theo sheet.
                Action::make('layTuLienMinh')
                    ->label('Lấy ngày đi từ liên minh')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (): bool => (bool) $this->getOwnerRecord()->alliance_tour_id
                        && auth()->user()?->can('create', TourDeparture::class))
                    ->requiresConfirmation()
                    ->modalDescription('Thêm các ngày khởi hành có trong sheet đối tác mà tour này chưa có, kèm số chỗ và giá từng ngày; ngày đã có thì cập nhật số chỗ / giá theo sheet (giá bạn tự sửa thì giữ). Sheet không ghi số chỗ thì lấy số chỗ mặc định của đối tác. Máy cũng tự làm việc này mỗi lần đọc sheet.')
                    ->action(function (): void {
                        $dv = app(DongBoLienMinh::class);
                        $doi = $dv->capNhatTourPsv([$this->getOwnerRecord()->alliance_tour_id]);
                        $kq = $dv->themNgayDi($this->getOwnerRecord());
                        Notification::make()
                            ->success()
                            ->title($kq['them'] ? "Đã thêm {$kq['them']} ngày khởi hành" : 'Không có ngày mới để thêm')
                            ->body($doi ? "Cập nhật số chỗ / giá {$doi} ngày đã có." : null)
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
