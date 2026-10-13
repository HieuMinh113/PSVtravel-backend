<?php

namespace App\Services;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Mail\UngTuyenMail;
use App\Models\JobApplication;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Ứng viên vừa gửi hồ sơ trên website:
 *  - chuông (kèm tiếng báo) trong trang quản trị cho người có quyền xem hồ sơ
 *    ứng tuyển (mặc định super_admin + admin)
 *  - thư xác nhận "đã nhận hồ sơ" cho ứng viên
 *
 * Lỗi gửi KHÔNG được làm hỏng việc nộp: hồ sơ đã lưu rồi, báo lỗi ra ngoài chỉ
 * khiến ứng viên tưởng thất bại và gửi lại lần nữa.
 */
class BaoHoSoUngTuyen
{
    public function guiNoiBo(JobApplication $hs): void
    {
        try {
            $nguoiNhan = User::permission('ViewAny:JobApplication')->get();
            if ($nguoiNhan->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('Hồ sơ ứng tuyển mới: '.$hs->full_name)
                ->body(collect([
                    'Vị trí: '.$hs->job_title,
                    'SĐT: '.$hs->phone.' · '.$hs->email,
                    $hs->portfolio_path || $hs->portfolio_url ? 'Có CV + portfolio' : 'Có CV',
                ])->implode('<br>'))
                ->icon('heroicon-o-document-text')
                ->status('info')
                ->actions([
                    Action::make('xem')->label('Xem hồ sơ')->button()->markAsRead()
                        ->url(JobApplicationResource::getUrl('view', ['record' => $hs], panel: 'admin')),
                ])
                ->sendToDatabase($nguoiNhan, isEventDispatched: true);
        } catch (\Throwable $e) {
            Log::warning('Hồ sơ ứng tuyển: không gửi được chuông', ['ho_so' => $hs->id, 'loi' => $e->getMessage()]);
        }
    }

    public function guiUngVien(JobApplication $hs): void
    {
        try {
            Mail::to($hs->email)->send(new UngTuyenMail($hs));
        } catch (\Throwable $e) {
            Log::error('Không gửi được thư xác nhận ứng tuyển #'.$hs->id.': '.$e->getMessage());
        }
    }
}
