<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Filament\Resources\Payments\KhoanThu;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Auth;
use Modules\Booking\Models\Payment;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Thanh toán';

    protected static ?string $modelLabel = 'khoản thu';

    // Thêm khoản thu: kế toán, hoặc người được sửa đơn này (đơn của mình /
    // đơn chưa ai nhận) — nhân viên khác chỉ xem.
    protected function getCreateAuthorizationResponse(): Response
    {
        $u = auth()->user();

        return $u && ($u->can('Approve:Payment') || $u->can('update', $this->getOwnerRecord()))
            ? Response::allow()
            : Response::deny();
    }

    // Kế toán (chỉ xem đơn) vẫn phải thấy và duyệt được khoản thu ở trang xem đơn
    public function isReadOnly(): bool
    {
        return false;
    }

    // Hiện số tiền còn thiếu ngay trên tiêu đề khối
    public function getTableHeading(): string
    {
        $don = $this->getOwnerRecord();

        $daThu = $don->payments()->where('status', 'success')->sum('amount');
        $conThieu = max(0, (int) $don->total_price - (int) $daThu);
        $choDuyet = $don->payments()->where('status', 'pending')->sum('amount');

        return 'Thanh toán — đã thu '.number_format($daThu, 0, ',', '.')
            .'₫ / còn thiếu '.number_format($conThieu, 0, ',', '.').'₫'
            .($choDuyet > 0 ? ' · chờ kế toán duyệt '.number_format($choDuyet, 0, ',', '.').'₫' : '');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('amount')
                ->label('Số tiền thu')
                ->numeric()
                ->minValue(1)
                ->suffix('₫')
                ->required(),
            Select::make('method')
                ->label('Hình thức')
                ->options([
                    'cash' => 'Tiền mặt',
                    'bank_transfer' => 'Chuyển khoản',
                    'card' => 'Quẹt thẻ',
                ])
                ->default('bank_transfer')
                ->live()
                ->required(),

            DateTimePicker::make('paid_at')
                ->label('Thời điểm thu')
                ->native(false)
                ->displayFormat('d/m/Y H:i')
                ->seconds(false)
                ->default(now())
                ->required(),
            // Chỉ kế toán chọn trạng thái. Nhân viên ghi khoản thu thì luôn ở
            // "Chờ kế toán duyệt" — chưa tính vào đã thu cho tới khi kế toán duyệt.
            Select::make('status')
                ->label('Trạng thái')
                ->options(Payment::TRANG_THAI)
                ->default('success')
                ->required()
                ->visible(fn () => KhoanThu::laKeToan())
                ->helperText('Chỉ khoản "Đã nhận tiền" mới tính vào tổng đã thu'),

            // Mã giao dịch là duy nhất (CSDL có ràng buộc unique) — một lần
            // chuyển khoản chỉ được ghi một lần. Kiểm tra trước để báo rõ mã đã
            // nằm ở đơn nào, thay vì để lỗi CSDL làm sập trang.
            TextInput::make('transaction_ref')
                ->label('Mã giao dịch / số phiếu thu')
                ->helperText('Để trống nếu thu tiền mặt')
                ->maxLength(255)
                ->rules([fn (?Payment $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                    $trung = Payment::query()
                        ->where('transaction_ref', $value)
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->with('booking:id,booking_code')
                        ->first();
                    if ($trung) {
                        $fail('Mã giao dịch này đã được ghi nhận ở đơn '.($trung->booking?->booking_code ?? '#'.$trung->booking_id)
                            .' ('.number_format($trung->amount, 0, ',', '.').'đ, '.$trung->paid_at?->format('d/m/Y').'). Kiểm tra lại, tránh ghi một lần chuyển khoản hai lần.');
                    }
                }]),

            KhoanThu::oChungTu(),

            Textarea::make('note')
                ->label('Ghi chú')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Thời điểm')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('method')
                    ->label('Hình thức')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Tiền mặt',
                        'card' => 'Quẹt thẻ',
                        default => 'Chuyển khoản',
                    }),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Payment::TRANG_THAI[$state] ?? $state)
                    ->description(fn (Payment $record): ?string => $record->approvedBy
                        ? $record->approvedBy->name.' · '.$record->approved_at?->format('d/m H:i')
                        : null)
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
                KhoanThu::cotChungTu(),
                TextColumn::make('receivedBy.name')
                    ->label('Người nhập')
                    ->placeholder('—'),
                TextColumn::make('transaction_ref')
                    ->label('Mã GD')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn (Payment $record): ?string => $record->note)
                    ->toggleable(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Ghi nhận khoản thu')
                    ->modalHeading('Ghi nhận khoản thu')
                    ->createAnother(false)
                    ->mutateDataUsing(function (array $data): array {
                        $data['received_by'] = Auth::id();
                        if (! KhoanThu::laKeToan()) {
                            $data['status'] = 'pending';
                        }

                        return $data;
                    })
                    ->after(function (Payment $record) {
                        if ($record->status === 'pending') {
                            KhoanThu::baoKeToan($record);
                        }
                    }),
            ])
            ->recordActions([
                KhoanThu::nutDuyet(),
                KhoanThu::nutTuChoi(),
                KhoanThu::xemChungTu()->iconButton(),
                EditAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        if (! KhoanThu::laKeToan()) {
                            unset($data['status']);
                        }

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}
