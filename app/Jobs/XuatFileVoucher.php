<?php

namespace App\Jobs;

use App\Filament\Resources\VoucherBatches\VoucherBatchResource;
use App\Models\User;
use App\Models\VoucherBatch;
use App\Services\Voucher\XuatVoucher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tạo file in / ZIP ảnh cho đợt voucher nhiều mã (1000 mã ~30 giây), xong báo
 * chuông cho người bấm xuất kèm nút tải (link 24 giờ, tải một lần).
 */
class XuatFileVoucher implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Dưới retry_after (90 giây) của hàng đợi để không bị chạy trùng */
    public int $timeout = 85;

    public int $tries = 1;

    /** @param list<int> $ids */
    public function __construct(public int $dot, public string $kieu, public array $ids, public int $nguoi) {}

    public function handle(): void
    {
        $d = VoucherBatch::query()->findOrFail($this->dot);
        $u = User::query()->findOrFail($this->nguoi);
        [$tep, $ten] = XuatVoucher::tao($d, $this->kieu, $this->ids);

        Notification::make()
            ->title('File voucher đã sẵn sàng')
            ->body($d->name.' · '.count($this->ids).' mã · '.(XuatVoucher::kieu()[$this->kieu] ?? $this->kieu).'<br>Link tải dùng được 24 giờ, tải một lần.')
            ->icon('heroicon-o-printer')
            ->success()
            ->actions([
                Action::make('tai')->label('Tải file')->button()->markAsRead()
                    ->url(XuatVoucher::cat($tep, $ten, $u->id, 24 * 60)),
            ])
            ->sendToDatabase($u, isEventDispatched: true);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Xuất file voucher lỗi', ['dot' => $this->dot, 'kieu' => $this->kieu, 'loi' => $e?->getMessage()]);
        $u = User::query()->find($this->nguoi);
        if (! $u) {
            return;
        }
        Notification::make()
            ->title('Không tạo được file voucher')
            ->body(($e?->getMessage() ?: 'Lỗi không rõ').'<br>Thử lại với ít mã hơn (chọn theo số thứ tự), hoặc báo kỹ thuật.')
            ->danger()
            ->actions([
                Action::make('mo')->label('Mở đợt voucher')->markAsRead()
                    ->url(VoucherBatchResource::getUrl('edit', ['record' => $this->dot], panel: 'admin')),
            ])
            ->sendToDatabase($u, isEventDispatched: true);
    }
}
