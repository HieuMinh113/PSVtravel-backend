<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Booking\Models\Payment;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Thanh toán';

    // Hiện số tiền còn thiếu ngay trên tiêu đề khối
    public function getTableHeading(): string
    {
        $don = $this->getOwnerRecord();

        $daThu = $don->payments()->where('status', 'success')->sum('amount');
        $conThieu = max(0, (int) $don->total_price - (int) $daThu);

        return 'Thanh toán — đã thu '.number_format($daThu, 0, ',', '.')
            .'₫ / còn thiếu '.number_format($conThieu, 0, ',', '.').'₫';
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
                ->required(),

            DateTimePicker::make('paid_at')
                ->label('Thời điểm thu')
                ->native(false)
                ->displayFormat('d/m/Y H:i')
                ->seconds(false)
                ->default(now())
                ->required(),
            Select::make('status')
                ->label('Trạng thái')
                ->options([
                    'success' => 'Đã nhận tiền',
                    'pending' => 'Chờ xác nhận',
                    'failed' => 'Thất bại',
                ])
                ->default('success')
                ->required()
                ->helperText('Chỉ khoản "Đã nhận tiền" mới tính vào tổng đã thu'),

            TextInput::make('transaction_ref')
                ->label('Mã giao dịch / số phiếu thu')
                ->helperText('Để trống nếu thu tiền mặt')
                ->maxLength(255),

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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Đã nhận tiền',
                        'failed' => 'Thất bại',
                        default => 'Chờ xác nhận',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('receivedBy.name')
                    ->label('Người thu')
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
                    ->mutateDataUsing(function (array $data): array {
                        $data['received_by'] = Auth::id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}