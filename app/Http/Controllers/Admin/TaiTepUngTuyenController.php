<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mở CV / portfolio của hồ sơ ứng tuyển ngay trên trình duyệt (PDF).
 * Kiểm tra quyền ở MỖI lần mở — không dùng link ký sẵn để link lỡ bị chuyển
 * tiếp cũng không mở được. Tên file tải về dễ đọc: "CV - Nguyen Van A - Sales.pdf".
 */
class TaiTepUngTuyenController extends Controller
{
    public function __invoke(JobApplication $hoSo, string $loai): StreamedResponse
    {
        Gate::authorize('view', $hoSo);

        $duong = $loai === 'cv' ? $hoSo->cv_path : $hoSo->portfolio_path;
        $dia = Storage::disk(JobApplication::DIA);
        abort_unless($duong && $dia->exists($duong), 404);

        $ten = Str::of(($loai === 'cv' ? 'CV' : 'Portfolio').' - '.$hoSo->full_name.' - '.$hoSo->job_title)
            ->ascii()->replaceMatches('/[^A-Za-z0-9 ._()-]+/', '')->squish()->limit(120, '').'.pdf';

        return $dia->response($duong, $ten, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
