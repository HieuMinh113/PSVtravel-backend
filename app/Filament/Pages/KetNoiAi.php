<?php

namespace App\Filament\Pages;

use App\Mcp\MaKhoaMcp;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Kết nối trợ lý AI trên máy tính (Claude Code, Claude Desktop…) vào máy chủ MCP
 * nội bộ (xem số liệu quản trị, soạn bài cẩm nang nháp). Mỗi Admin tự tạo mã khoá cho máy của mình,
 * thu hồi khi đổi máy / nghi lộ mã. Chỉ vai trò Admin.
 */
class KetNoiAi extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|\UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?string $navigationLabel = 'Kết nối AI (MCP)';

    protected static ?string $title = 'Kết nối AI (MCP)';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'ket-noi-ai';

    /** Mã vừa tạo — chỉ hiện đúng một lần, tải lại trang là mất */
    public ?string $maMoi = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->laAdmin();
    }

    public static function diaChi(): string
    {
        return url('/mcp/noi-bo');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.ket-noi-ai')->viewData(fn () => [
                'diaChi' => self::diaChi(),
                'maMoi' => $this->maMoi,
                'soNgay' => MaKhoaMcp::SO_NGAY_HIEU_LUC,
            ]),
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('taoMa')
                ->label('Tạo mã khoá')
                ->icon(Heroicon::OutlinedKey)
                ->modalHeading('Tạo mã khoá cho máy tính')
                ->modalDescription('Mã chỉ hiện một lần sau khi tạo. Mỗi máy tính nên có mã riêng để thu hồi riêng khi cần.')
                ->schema([
                    TextInput::make('ten')->label('Tên máy / ứng dụng')->placeholder('VD: Laptop văn phòng – Claude Code')
                        ->required()->maxLength(60),
                ])
                ->modalSubmitActionLabel('Tạo mã')
                ->action(function (array $data) {
                    $this->maMoi = MaKhoaMcp::tao(auth()->user(), $data['ten']);
                    Notification::make()->title('Đã tạo mã khoá — chép ngay, mã chỉ hiện một lần')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Mã khoá của tôi')
            ->query(fn () => MaKhoaMcp::cuaNguoi(auth()->user()))
            ->columns([
                TextColumn::make('name')->label('Máy / ứng dụng')
                    ->formatStateUsing(fn (string $state) => Str::after($state, MaKhoaMcp::TIEN_TO_TEN)),
                TextColumn::make('created_at')->label('Tạo lúc')->dateTime('d/m/Y H:i'),
                TextColumn::make('last_used_at')->label('Dùng lần cuối')->dateTime('d/m/Y H:i')->placeholder('Chưa dùng'),
                TextColumn::make('expires_at')->label('Hết hạn')->date('d/m/Y')
                    ->color(fn (PersonalAccessToken $r) => $r->expires_at?->isPast() ? 'danger' : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('thuHoi')
                    ->label('Thu hồi')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Thu hồi mã khoá?')
                    ->modalDescription('Trợ lý AI trên máy dùng mã này sẽ mất kết nối ngay.')
                    ->action(fn (PersonalAccessToken $record) => $record->delete()),
            ])
            ->paginated(false)
            ->emptyStateHeading('Chưa có mã khoá nào')
            ->emptyStateDescription('Bấm "Tạo mã khoá" để kết nối trợ lý AI trên máy tính của bạn.');
    }
}
