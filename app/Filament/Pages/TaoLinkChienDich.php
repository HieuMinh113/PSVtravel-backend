<?php

namespace App\Filament\Pages;

use App\Models\ContactMessage;
use App\Services\MaQrTour;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Modules\Booking\Models\Booking;

/**
 * Tạo link có mã chiến dịch (utm_*) + mã QR cho bài đăng, clip, quảng cáo, tờ
 * rơi. Chọn kênh / loại từ danh sách cố định để mọi người đặt tên giống nhau —
 * gõ tay dễ thành "Facebook", "facebook", "fb" và báo cáo tách làm ba dòng.
 *
 * Kết quả đọc ở: Bán hàng → Nguồn khách (đơn, liên hệ theo kênh / chiến dịch /
 * clip) và Google Analytics (Thu nạp → Chiến dịch).
 */
class TaoLinkChienDich extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?string $navigationLabel = 'Tạo link chiến dịch';

    protected static ?string $title = 'Tạo link chiến dịch';

    protected static ?int $navigationSort = 32;

    protected static ?string $slug = 'tao-link-chien-dich';

    /** utm_source — trùng cách website phân loại kênh (App\Services\NguonKhach) */
    public const KENH = [
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'zalo' => 'Zalo',
        'youtube' => 'YouTube',
        'instagram' => 'Instagram',
        'google' => 'Google',
        'email' => 'Email',
        'qr' => 'Mã QR in ấn',
    ];

    /** utm_medium — "paid" thì báo cáo tách riêng thành "Quảng cáo Facebook / Google" */
    public const LOAI = [
        'social' => 'Đăng bài thường (không trả tiền)',
        'paid' => 'Quảng cáo trả tiền',
        'email' => 'Email',
        'qr' => 'Mã QR (tờ rơi, hồ sơ, standee…)',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    // Như trang Nguồn khách: quản lý đơn và Marketing
    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can('giao', Booking::class) || $u?->hasRole('marketing'));
    }

    public function mount(): void
    {
        $this->form->fill(['trang' => self::web().'/', 'kenh' => 'facebook', 'loai' => 'social']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(2)->components([
            TextInput::make('trang')
                ->label('Trang đích')
                ->helperText('Mở trang muốn khách vào (tour, trang theo mùa, team building…) trên psvtravel.com, chép địa chỉ dán vào đây.')
                ->required()
                ->live(onBlur: true)
                ->columnSpanFull(),
            Select::make('kenh')
                ->label('Đăng ở đâu')
                ->options(self::KENH)
                ->required()
                ->selectablePlaceholder(false)
                ->live()
                // Email / QR in ấn chỉ có một loại tương ứng
                ->afterStateUpdated(fn (?string $state, Set $set) => in_array($state, ['email', 'qr'], true) ? $set('loai', $state) : null),
            Select::make('loai')
                ->label('Hình thức')
                ->options(self::LOAI)
                ->required()
                ->selectablePlaceholder(false)
                ->live(),
            TextInput::make('chien_dich')
                ->label('Tên chiến dịch')
                ->helperText('Đợt bán / chủ đề, VD: Tết 2027, Hè Phú Quốc, Hồ sơ năng lực. Dùng lại đúng tên cũ (gợi ý khi gõ) để số liệu gộp chung.')
                ->datalist(fn () => self::chienDichDaCo())
                ->required()
                ->maxLength(60)
                ->live(onBlur: true),
            TextInput::make('noi_dung')
                ->label('Mã clip / bài đăng (không bắt buộc)')
                ->helperText('Phân biệt các clip, bài trong cùng chiến dịch, VD: clip-01, bai-ghim. Biết clip nào ra đơn.')
                ->maxLength(60)
                ->live(onBlur: true),
            Section::make('Link để dùng')
                ->columnSpanFull()
                ->schema([
                    View::make('filament.link-chien-dich')->viewData(fn (Get $get) => $this->ketQua($get('trang'), $get('kenh'), $get('loai'), $get('chien_dich'), $get('noi_dung'))),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    /** "Tết 2027 Phú Quốc" → "tet-2027-phu-quoc" */
    public static function ma(?string $chu): string
    {
        return Str::slug((string) $chu);
    }

    /**
     * Link có utm; null nếu trang đích không thuộc website.
     */
    public static function taoLink(string $trang, string $kenh, string $loai, string $chienDich, ?string $noiDung = null): ?string
    {
        $trang = trim($trang);
        if (! preg_match('#^https?://#i', $trang)) {
            $trang = self::web().'/'.ltrim($trang, '/');
        }
        $u = parse_url($trang);
        $host = fn (?string $h) => preg_replace('/^www\./', '', strtolower((string) $h));
        if (! $u || $host($u['host'] ?? null) !== $host(parse_url(self::web(), PHP_URL_HOST))) {
            return null;
        }

        // Giữ tham số sẵn có của trang (VD ?category=…), thay utm cũ nếu có
        parse_str($u['query'] ?? '', $thamSo);
        $thamSo = array_filter($thamSo, fn ($k) => ! str_starts_with((string) $k, 'utm_'), ARRAY_FILTER_USE_KEY);
        $thamSo += array_filter([
            'utm_source' => $kenh,
            'utm_medium' => $loai,
            'utm_campaign' => self::ma($chienDich),
            'utm_content' => self::ma($noiDung) ?: null,
        ]);

        return self::web().($u['path'] ?? '/').'?'.http_build_query($thamSo, '', '&', PHP_QUERY_RFC3986);
    }

    private function ketQua(?string $trang, ?string $kenh, ?string $loai, ?string $chienDich, ?string $noiDung): array
    {
        if (blank($trang) || blank($kenh) || blank($loai) || self::ma($chienDich) === '') {
            return ['link' => null, 'loi' => 'Điền tên chiến dịch để tạo link.'];
        }
        $link = self::taoLink($trang, $kenh, $loai, $chienDich, $noiDung);
        if (! $link) {
            return ['link' => null, 'loi' => 'Trang đích phải là một trang trên '.parse_url(self::web(), PHP_URL_HOST).'.'];
        }
        $qr = app(MaQrTour::class);
        $ten = 'ma-qr-'.self::ma($chienDich).(self::ma($noiDung) ? '-'.self::ma($noiDung) : '');

        return [
            'link' => $link,
            'loi' => null,
            'qrSvg' => 'data:image/svg+xml;base64,'.base64_encode($qr->svgTuNoiDung($link)),
            'qrPng' => 'data:image/png;base64,'.base64_encode($qr->pngTuNoiDung($link)),
            'tenTep' => $ten,
            'canhBaoQr' => $kenh === 'qr' ? null : 'Mã QR in trên giấy nên chọn "Đăng ở đâu: Mã QR in ấn" để báo cáo tách riêng lượt quét.',
        ];
    }

    /** Tên chiến dịch đã có trong đơn / liên hệ — gợi ý để đặt trùng tên */
    private static function chienDichDaCo(): array
    {
        return collect([Booking::class, ContactMessage::class])
            ->flatMap(fn ($m) => $m::query()->whereRaw("utm->>'campaign' is not null")
                ->selectRaw("distinct utm->>'campaign' as c")->limit(200)->pluck('c'))
            ->filter()->unique()->sort()->values()->all();
    }

    private static function web(): string
    {
        return rtrim((string) config('app.frontend_url', 'https://psvtravel.com'), '/');
    }
}
