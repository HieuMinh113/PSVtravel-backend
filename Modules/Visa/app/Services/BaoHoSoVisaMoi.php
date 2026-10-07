<?php

namespace Modules\Visa\Services;

use App\Filament\Resources\VisaCases\VisaCaseResource;
use App\Mail\HoSoVisaMail;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Visa\Models\VisaCase;

/**
 * Khách vừa nộp hồ sơ visa trên web:
 *  - chuông trong trang quản trị cho mọi người có quyền hồ sơ visa (nhân viên
 *    visa + admin) — ai nhận trước thì của người đó
 *  - email xác nhận cho khách (nếu có email)
 *
 * Lỗi gửi KHÔNG được làm hỏng việc nộp: hồ sơ đã lưu rồi, báo lỗi ra ngoài chỉ
 * khiến khách tưởng thất bại và nộp lại lần nữa.
 */
class BaoHoSoVisaMoi
{
    public function guiNoiBo(VisaCase $hs): void
    {
        try {
            $nguoiNhan = User::permission('ViewAny:VisaCase')->get();
            if ($nguoiNhan->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('Hồ sơ visa mới từ website: '.$hs->full_name)
                ->body(collect([
                    $hs->code.' · '.$hs->country.' · '.(VisaCase::MUC_DICH[$hs->purpose] ?? ''),
                    'SĐT: '.$hs->phone,
                    'Chưa ai nhận — vào mục “Chưa ai nhận” để nhận hồ sơ.',
                ])->implode('<br>'))
                ->icon('heroicon-o-identification')
                ->status('warning')
                ->actions([
                    Action::make('xem')->label('Xem hồ sơ chưa ai nhận')->button()->markAsRead()
                        ->url(VisaCaseResource::getUrl('index', ['tab' => 'chua_nhan'], panel: 'admin')),
                ])
                ->sendToDatabase($nguoiNhan, isEventDispatched: true);
        } catch (\Throwable $e) {
            Log::warning('Hồ sơ visa: không gửi được thông báo', ['ho_so' => $hs->code, 'loi' => $e->getMessage()]);
        }
    }

    public function guiKhach(VisaCase $hs): void
    {
        if (! $hs->email) {
            return;
        }

        try {
            Mail::to($hs->email)->send(new HoSoVisaMail($hs));
        } catch (\Throwable $e) {
            Log::error('Không gửi được mail hồ sơ visa '.$hs->code.': '.$e->getMessage());
        }
    }
}
