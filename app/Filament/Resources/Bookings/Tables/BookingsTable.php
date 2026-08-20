<?php

namespace App\Filament\Resources\Bookings\Tables;

use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Booking;
use Modules\Tour\Models\TourDeparture;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking_code')
                    ->label('Mã đơn')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('customer_name')
                    ->label('Khách hàng')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->label('Điện thoại')
                    ->searchable(),
                TextColumn::make('tour.name')
                    ->label('Tour')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('departure.start_date')
                    ->label('Ngày đi')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('total_price')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'confirmed' => 'Đã xác nhận',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã huỷ',
                        default => 'Chờ xử lý',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('payment_status')
                    ->label('Thanh toán')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Đã trả',
                        'partial' => 'Trả một phần',
                        default => 'Chưa trả',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('cancelledBy.name')
                    ->label('Người huỷ')
                    ->placeholder('—')
                    ->tooltip(fn (Booking $record): ?string => $record->cancel_reason)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Ngày đặt')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái đơn')
                    ->options([
                        'pending' => 'Chờ xử lý',
                        'confirmed' => 'Đã xác nhận',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã huỷ',
                    ]),
                SelectFilter::make('payment_status')
                    ->label('Thanh toán')
                    ->options([
                        'unpaid' => 'Chưa trả',
                        'partial' => 'Trả một phần',
                        'paid' => 'Đã trả',
                    ]),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Xác nhận')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Xác nhận đơn đặt tour')
                    ->modalDescription('Hệ thống sẽ trừ số chỗ của đợt khởi hành đã chọn.')
                    ->action(function (Booking $record): void {
                        $guests = $record->adults + $record->children;

                        try {
                            DB::transaction(function () use ($record, $guests) {
                                if ($record->tour_departure_id) {
                                    // Khoá dòng để tránh 2 nhân viên xác nhận cùng lúc gây âm chỗ
                                    $departure = TourDeparture::whereKey($record->tour_departure_id)
                                        ->lockForUpdate()
                                        ->firstOrFail();

                                    if ($departure->seats_left < $guests) {
                                        throw new \RuntimeException(
                                            'Đợt này chỉ còn '.$departure->seats_left.' chỗ, không đủ cho '.$guests.' khách.'
                                        );
                                    }

                                    $departure->decrement('seats_left', $guests);
                                    $departure->refresh();

                                    if ($departure->seats_left <= 0) {
                                        $departure->update(['status' => 'full']);
                                    }
                                }

                                $record->update(['status' => 'confirmed']);
                            });
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Không thể xác nhận')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Đã xác nhận đơn')
                            ->body('Số chỗ đã được trừ khỏi đợt khởi hành.')
                            ->success()
                            ->send();
                    }),
                // BOOK-13: trước đây trạng thái "Hoàn thành" có trong danh sách
                // nhưng không có luồng nào chuyển đơn sang trạng thái đó.
                // Đơn đã đi xong phải đóng lại thì khách mới viết được đánh giá
                // (API đánh giá chỉ nhận đơn confirmed/completed) và báo cáo doanh
                // thu mới tách được đơn đã phục vụ với đơn còn đang chờ.
                Action::make('complete')
                    ->label('Hoàn thành')
                    ->color('success')
                    ->icon('heroicon-o-flag')
                    ->visible(fn (Booking $record): bool => $record->status === 'confirmed')
                    ->requiresConfirmation()
                    ->modalHeading('Đánh dấu đơn đã hoàn thành')
                    ->modalDescription(function (Booking $record): string {
                        // Đợt khởi hành chỉ lưu ngày đi, ngày về suy ra từ số ngày của tour
                        $ngayDi = $record->departure?->start_date;
                        $soNgay = (int) ($record->tour?->duration_days ?? 0);
                        $ngayVe = $ngayDi && $soNgay > 0 ? $ngayDi->copy()->addDays($soNgay - 1) : null;

                        if ($ngayVe && $ngayVe->isFuture()) {
                            return 'Lưu ý: đợt này tới ngày '.$ngayVe->format('d/m/Y')
                                .' mới kết thúc. Chỉ đánh dấu hoàn thành khi khách đã thực sự đi xong.';
                        }

                        return 'Đơn sẽ được đóng lại. Số chỗ đã trừ trước đó vẫn giữ nguyên, không hoàn lại.';
                    })
                    ->modalSubmitActionLabel('Xác nhận hoàn thành')
                    ->action(function (Booking $record): void {
                        // Chốt chặn ở tầng dữ liệu: chỉ đơn đã xác nhận mới được
                        // hoàn thành. Đơn chờ xử lý chưa trừ chỗ, đóng thẳng là sai.
                        if ($record->status !== 'confirmed') {
                            Notification::make()
                                ->title('Không thể hoàn thành')
                                ->body('Chỉ đơn đang ở trạng thái "Đã xác nhận" mới chuyển sang "Hoàn thành" được.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['status' => 'completed']);

                        Notification::make()
                            ->title('Đã đóng đơn')
                            ->body('Khách có thể gửi đánh giá cho tour này.')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                Action::make('cancel')
                    ->label('Huỷ đơn')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Booking $record): bool => ! in_array($record->status, ['cancelled', 'completed'], true))
                    ->schema([
                        Textarea::make('cancel_reason')
                            ->label('Lý do huỷ')
                            ->required()
                            ->rows(3),
                    ])
                    ->modalHeading('Huỷ đơn đặt tour')
                    ->modalSubmitActionLabel('Xác nhận huỷ')
                    ->action(function (Booking $record, array $data): void {
                        $guests = $record->adults + $record->children;
                        $wasConfirmed = $record->status === 'confirmed';

                        DB::transaction(function () use ($record, $data, $guests, $wasConfirmed) {
                            // Chỉ hoàn chỗ nếu trước đó đã xác nhận (tức đã bị trừ chỗ)
                            if ($wasConfirmed && $record->tour_departure_id) {
                                $departure = TourDeparture::whereKey($record->tour_departure_id)
                                    ->lockForUpdate()
                                    ->first();

                                if ($departure) {
                                    $departure->increment('seats_left', $guests);
                                    $departure->refresh();

                                    if ($departure->status === 'full' && $departure->seats_left > 0) {
                                        $departure->update(['status' => 'open']);
                                    }
                                }
                            }

                            $record->update([
                                'status' => 'cancelled',
                                'cancel_reason' => $data['cancel_reason'],
                                'cancelled_by' => Auth::id(),
                                'cancelled_at' => now(),
                            ]);
                        });

                        Notification::make()
                            ->title('Đã huỷ đơn')
                            ->body($wasConfirmed ? 'Số chỗ đã được hoàn lại đợt khởi hành.' : 'Đơn chưa xác nhận nên không cần hoàn chỗ.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ]);
    }
}