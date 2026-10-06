<?php

namespace App\Filament\Resources\AllianceDepartures\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Alliance\Models\AllianceDeparture;
use Modules\Alliance\Models\AllianceSource;

class AllianceDeparturesTable
{
    private const THU = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

    public static function configure(Table $table): Table
    {
        $nguong = (int) config('alliance.nguong_sap_het', 3);

        return $table
            ->columns([
                TextColumn::make('departure_date')
                    ->label('Ngày đi')
                    ->date('d/m/Y')
                    // Tour không có ngày cụ thể: "Thứ 5 hằng tuần" (VVT nội địa)
                    ->placeholder(fn (AllianceDeparture $record) => $record->weekly ?: '—')
                    ->description(fn (AllianceDeparture $record) => $record->departure_date
                        ? self::THU[$record->departure_date->dayOfWeek].' · '.self::conBaoLau($record->departure_date)
                        : 'khởi hành hằng tuần')
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderByRaw('departure_date is null')->orderBy('departure_date', $direction)),
                TextColumn::make('tour.name')
                    ->label('Tour')
                    ->wrap()
                    ->lineClamp(2)
                    ->description(fn (AllianceDeparture $record) => collect([
                        $record->tour?->source?->name,
                        $record->tour?->duration,
                        $record->tour?->hangNgan(),
                    ])->filter()->implode(' · '))
                    ->tooltip(fn (AllianceDeparture $record) => $record->tour?->details)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('tour', fn ($t) => $t
                        ->where(fn ($w) => $w
                            ->where('name', self::like(), "%{$search}%")
                            ->orWhere('details', self::like(), "%{$search}%")
                            ->orWhere('section', self::like(), "%{$search}%")
                            ->orWhere('sheet_tab', self::like(), "%{$search}%")))),
                TextColumn::make('status')
                    ->label('Chỗ')
                    ->badge()
                    ->formatStateUsing(fn (string $state, AllianceDeparture $record) => $state === 'con_cho'
                        ? "Còn {$record->seats_left}"
                        : (AllianceDeparture::TRANG_THAI[$state] ?? $state))
                    ->color(fn (string $state, AllianceDeparture $record) => match ($state) {
                        'con_cho' => $record->seats_left <= $nguong ? 'warning' : 'success',
                        'het_cho', 'huy' => 'danger',
                        'tam_ngung' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (AllianceDeparture $record) => $record->seats_total !== null
                        ? "Tổng {$record->seats_total}".($record->seats_sold !== null ? " · chốt {$record->seats_sold}" : '').($record->seats_hold ? " · giữ {$record->seats_hold}" : '')
                        : null)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('seats_left', $direction)),
                TextColumn::make('price')
                    ->label('Giá')
                    ->formatStateUsing(fn ($state) => self::tien($state))
                    ->placeholder(fn (AllianceDeparture $record) => $record->price_text ?: '—')
                    ->tooltip(fn (AllianceDeparture $record) => $record->price_text ? 'Trong sheet ghi: '.$record->price_text : null)
                    // Có giá khuyến mãi: giá chính là giá KM, giá gốc gạch ngang bên dưới
                    ->description(fn (AllianceDeparture $record) => $record->price_original
                        ? new \Illuminate\Support\HtmlString('<span style="text-decoration:line-through">'.e(self::tien($record->price_original)).'</span> · giá KM')
                        : ($record->price_child ? 'Trẻ em '.self::tien($record->price_child) : null))
                    ->color(fn (AllianceDeparture $record) => $record->price_original ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('commission')
                    ->label('COM')
                    ->formatStateUsing(fn ($state) => self::tien($state))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('visa_deadline')
                    ->label('Hạn visa')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tour_psv')
                    ->label('Tour PSV')
                    ->state(fn (AllianceDeparture $record) => $record->tour?->psvTours->pluck('name')->all() ?: null)
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('chuong_trinh')
                    ->label('CT')
                    ->tooltip('Mở chương trình tour của đối tác')
                    ->state(fn (AllianceDeparture $record) => (bool) $record->tour?->program_url)
                    ->icon(fn (bool $state) => $state ? Heroicon::OutlinedDocumentText : null)
                    ->url(fn (AllianceDeparture $record) => $record->tour?->program_url, shouldOpenInNewTab: true),
                TextColumn::make('tour_code')
                    ->label('Mã tour')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sheet_tab')
                    ->label('Tab / dòng')
                    ->formatStateUsing(fn ($state, AllianceDeparture $record) => $state.($record->sheet_row ? " · dòng {$record->sheet_row}" : ''))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(fn (Builder $query) => $query->orderByRaw('departure_date is null')->orderBy('departure_date')->orderBy('alliance_tour_id'))
            ->filters([
                Filter::make('ngay')
                    ->label('Khoảng ngày đi')
                    ->schema([
                        DatePicker::make('tu')->label('Đi từ ngày')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('den')->label('Đến ngày')->native(false)->displayFormat('d/m/Y'),
                    ])
                    // Tour hằng tuần (không có ngày) chạy mọi tuần nên luôn khớp khoảng ngày
                    // (chỉ lọc khi có nhập ngày — nhóm điều kiện rỗng bị Laravel bỏ đi,
                    // còn lại mỗi "hoặc không có ngày" sẽ giấu hết các ngày đi)
                    ->query(fn (Builder $query, array $data) => $query->when(
                        ($data['tu'] ?? null) || ($data['den'] ?? null),
                        fn ($q) => $q->where(fn ($w) => $w
                            ->where(fn ($n) => $n
                                ->when($data['tu'] ?? null, fn ($x, $v) => $x->whereDate('departure_date', '>=', $v))
                                ->when($data['den'] ?? null, fn ($x, $v) => $x->whereDate('departure_date', '<=', $v)))
                            ->orWhereNull('departure_date')),
                    ))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['tu'] ?? null) ? 'Từ '.Carbon::parse($data['tu'])->format('d/m/Y') : null,
                        ($data['den'] ?? null) ? 'Đến '.Carbon::parse($data['den'])->format('d/m/Y') : null,
                    ])),
                Filter::make('so_khach')
                    ->label('Đủ chỗ cho')
                    ->schema([
                        TextInput::make('khach')->label('Còn ít nhất (chỗ)')->numeric()->minValue(1),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        (int) ($data['khach'] ?? 0) > 0,
                        fn ($w) => $w->where('status', 'con_cho')->where('seats_left', '>=', (int) $data['khach']),
                    ))
                    ->indicateUsing(fn (array $data) => (int) ($data['khach'] ?? 0) > 0 ? 'Còn ≥ '.(int) $data['khach'].' chỗ' : null),
                SelectFilter::make('status')
                    ->label('Tình trạng')
                    ->options(AllianceDeparture::TRANG_THAI)
                    ->multiple(),
                SelectFilter::make('alliance_source_id')
                    ->label('Đối tác')
                    ->options(fn () => AllianceSource::orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('sheet_tab')
                    ->label('Tab trong sheet')
                    ->options(fn () => AllianceDeparture::query()->whereNotNull('sheet_tab')
                        ->distinct()->orderBy('sheet_tab')->pluck('sheet_tab', 'sheet_tab')->all())
                    ->searchable(),
                TernaryFilter::make('noi_web')
                    ->label('Tour PSV đang bán')
                    ->trueLabel('Chỉ tour đã nối với web')
                    ->falseLabel('Chỉ tour chưa nối')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('tour.psvTours'),
                        false: fn (Builder $query) => $query->whereDoesntHave('tour.psvTours'),
                    ),
            ])
            ->filtersFormColumns(2)
            ->deferFilters(false)
            ->poll('60s')
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('Chưa có ngày khởi hành nào')
            ->emptyStateDescription('Thêm link sheet đối tác ở mục “Sheet liên minh”, hoặc bỏ bớt bộ lọc.');
    }

    /** Postgres: LIKE phân biệt hoa thường ("seoul" không ra "SEOUL") → dùng ILIKE. */
    public static function like(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    private static function tien($so): ?string
    {
        return $so ? number_format((int) $so, 0, ',', '.').'đ' : null;
    }

    private static function conBaoLau(Carbon $ngay): string
    {
        $n = (int) today()->diffInDays($ngay, false);

        return match (true) {
            $n === 0 => 'hôm nay',
            $n === 1 => 'ngày mai',
            default => "còn {$n} ngày",
        };
    }
}
