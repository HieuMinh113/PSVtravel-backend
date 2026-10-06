<?php

namespace App\Filament\Resources\AllianceSources\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Alliance\Models\AllianceSource;
use Modules\Alliance\Services\TaiSheetLienMinh;

class AllianceSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên đối tác')
                    ->placeholder('VD: V1 Travel, Nhật Bản AZ, M Tour')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Đang theo dõi')
                    ->helperText('Tắt thì máy ngừng đọc sheet này (dữ liệu cũ vẫn giữ tới lần bật lại).')
                    ->default(true)
                    ->inline(false),
                TextInput::make('sheet_url')
                    ->label('Link sheet')
                    ->placeholder('https://docs.google.com/spreadsheets/d/…')
                    ->helperText('Dán nguyên link đối tác gửi. Sheet phải để chế độ “Bất kỳ ai có đường liên kết đều xem được”.')
                    ->url()
                    ->required()
                    ->rules([
                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                            if ($value && ! TaiSheetLienMinh::maFile($value)) {
                                $fail('Đây không phải link Google Sheet / Google Drive.');
                            }
                        },
                    ])
                    ->columnSpanFull(),
                Textarea::make('contact')
                    ->label('Liên hệ đối tác')
                    ->placeholder('Tên điều hành, SĐT, Zalo, email…')
                    ->rows(2),
                Textarea::make('note')
                    ->label('Ghi chú nội bộ')
                    ->rows(2),

                Section::make('Cách đọc sheet này')
                    ->description('Thường không cần chỉnh — máy tự nhận ra cột theo chữ tiêu đề. Chỉ chỉnh khi báo cáo bên dưới cho thấy đọc thiếu.')
                    ->collapsible()
                    ->schema([
                        Select::make('mau_do')
                            ->label('Ngày tô ĐỎ trong sheet có nghĩa là hết chỗ?')
                            ->options(AllianceSource::MAU_DO)
                            ->default('tat')
                            ->selectablePlaceholder(false)
                            ->helperText('Đa số đối tác tô đỏ để đánh dấu ngày lễ / Tết / khuyến mãi. Riêng sheet có ghi chú “đỏ hết chỗ” (như Hanvina) thì chọn mục thứ hai.'),
                        CheckboxList::make('tab_bo_qua')
                            ->label('Bỏ qua các tab')
                            ->helperText('Tab không phải lịch tour (phí visa, vé máy bay, tab năm cũ…). Danh sách tab có sau lần đọc đầu tiên.')
                            ->options(fn (?AllianceSource $record) => self::danhSachTab($record))
                            ->columns(3)
                            ->visible(fn (?AllianceSource $record) => (bool) self::danhSachTab($record)),
                        Repeater::make('cot_tay')
                            ->label('Khai cột bằng tay (tab không có dòng tiêu đề)')
                            ->helperText('Ghi chữ cột trong sheet (A, B, C…). Tab khai ở đây sẽ đọc theo đúng cột này, không tự dò tiêu đề nữa.')
                            ->schema([
                                Select::make('tab')
                                    ->label('Tab')
                                    ->options(fn (?AllianceSource $record) => self::danhSachTab($record))
                                    ->searchable()
                                    ->required()
                                    ->columnSpanFull(),
                                Grid::make(5)->schema(
                                    collect(AllianceSource::VAI_TRO_COT)->map(fn ($nhan, $vaiTro) => TextInput::make($vaiTro)
                                        ->label($nhan)
                                        ->placeholder('VD: F')
                                        ->maxLength(2)
                                        ->regex('/^[A-Za-z]{1,2}$/')
                                        ->required($vaiTro === 'ngay_di'))->values()->all()
                                ),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Khai cột cho một tab')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['tab'] ?? null),
                    ])
                    ->columnSpanFull(),

                Section::make('Kết quả lần đọc gần nhất')
                    ->schema([
                        Text::make(fn (?AllianceSource $record) => $record?->last_error
                            ? 'Lần đọc gần nhất bị lỗi: '.$record->last_error
                            : '')
                            ->color('danger')
                            ->visible(fn (?AllianceSource $record) => (bool) $record?->last_error),
                        Text::make(fn (?AllianceSource $record) => self::baoCaoHtml($record)),
                    ])
                    ->visible(fn (?AllianceSource $record) => (bool) ($record?->bao_cao || $record?->last_error))
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /** Tên các tab đọc được lần trước (cả tab đã bỏ qua) → dùng cho ô chọn. */
    private static function danhSachTab(?AllianceSource $record): array
    {
        $tab = collect($record?->bao_cao ?? [])->pluck('tab')->filter()->values();

        // Tab đang bỏ qua / đang khai tay vẫn phải có trong danh sách để bỏ chọn được
        $tab = $tab->merge($record?->tab_bo_qua ?? [])->merge(collect($record?->cot_tay ?? [])->pluck('tab'))->filter()->unique();

        return $tab->mapWithKeys(fn ($t) => [$t => $t])->all();
    }

    /**
     * Mỗi tab: đọc được bao nhiêu ngày đi, có bị bỏ qua / ẩn không, và những
     * ô ngày máy không hiểu (kèm số dòng) để điều hành đối chiếu sheet gốc.
     */
    private static function baoCaoHtml(?AllianceSource $record): HtmlString
    {
        $dong = [];
        foreach ($record?->bao_cao ?? [] as $b) {
            $ten = e($b['tab'] ?? '?');
            $trangThai = match ($b['tinh_trang'] ?? '') {
                'an' => '<span style="color:#6b7280">tab ẩn — không đọc</span>',
                'bo_qua' => '<span style="color:#6b7280">đang bỏ qua</span>',
                'khong_co_bang' => '<span style="color:#b45309">không thấy bảng lịch tour (không có cột ngày đi)</span>',
                default => '<strong>'.(int) ($b['so_ngay'] ?? 0).'</strong> ngày đi sắp tới'
                    .(! empty($b['mau_do']) ? ' · <span style="color:#dc2626">ngày tô đỏ = hết chỗ</span>' : '')
                    .(! empty($b['cot_tay']) ? ' · đọc theo cột khai tay' : ''),
            };
            $html = "<li><strong>{$ten}</strong>: {$trangThai}";
            if (! empty($b['khong_hieu'])) {
                $html .= '<br><span style="color:#b45309">Ô ngày chưa hiểu:</span> '.collect($b['khong_hieu'])
                    ->map(fn ($k) => 'dòng '.(int) $k['dong'].' “'.e($k['chu']).'”')->implode('; ');
            }
            $dong[] = $html.'</li>';
        }
        if (! $dong) {
            return new HtmlString('');
        }

        $luc = $record?->last_success_at ? ' (đọc lúc '.$record->last_success_at->format('H:i d/m/Y').')' : '';

        return new HtmlString('<div style="font-size:0.875rem;line-height:1.6">Từng tab trong sheet'.e($luc)
            .':<ul style="list-style:disc;padding-left:1.25rem;margin-top:0.25rem">'.implode('', $dong).'</ul>'
            .'<p style="color:#6b7280;margin-top:0.5rem">Thấy tab đọc thiếu, chụp phần này gửi bộ phận kỹ thuật để bổ sung cách đọc.</p></div>');
    }
}
