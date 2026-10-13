<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Services\BaoHoSoUngTuyen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

/**
 * POST /api/v1/jobs/{slug}/apply — ứng viên gửi hồ sơ từ trang chi tiết tin
 * tuyển dụng: thông tin liên hệ + CV (PDF) + portfolio (PDF hoặc link, không
 * bắt buộc). Một lần gửi: CV ≤ 5MB + portfolio ≤ 10MB vẫn dưới giới hạn 20MB
 * mỗi lần gửi của nginx.
 */
class JobApplicationController extends Controller
{
    public const CV_TOI_DA_MB = 5;

    public const PORTFOLIO_TOI_DA_MB = 10;

    public function store(Request $request, string $slug, BaoHoSoUngTuyen $bao): JsonResponse
    {
        $tin = JobPosting::query()->dangHienThi()->where('slug', $slug)->first();
        if (! $tin) {
            return response()->json(['message' => 'Vị trí này đã đóng tuyển hoặc không còn trên website.'], 404);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9\s+.-]{8,20}$/'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'portfolio_url' => ['nullable', 'url:https,http', 'max:500'],
            // Kiểm tra theo NỘI DUNG file (không chỉ đuôi): file khác đổi tên thành .pdf bị từ chối
            'cv' => ['required', File::types(['pdf'])->max(self::CV_TOI_DA_MB * 1024)],
            'portfolio' => ['nullable', File::types(['pdf'])->max(self::PORTFOLIO_TOI_DA_MB * 1024)],
            'dong_y' => ['accepted'],
            // Ô bẫy: người thật không thấy nên luôn để trống
            'website' => ['nullable', 'size:0'],
        ], [
            'full_name.required' => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'message.max' => 'Lời nhắn tối đa 2000 ký tự.',
            'portfolio_url.url' => 'Link portfolio không hợp lệ (cần bắt đầu bằng https://).',
            'cv.required' => 'Vui lòng đính kèm CV (PDF).',
            'cv.mimes' => 'CV phải là file PDF.',
            'cv.max' => 'CV tối đa '.self::CV_TOI_DA_MB.'MB.',
            'cv.uploaded' => 'Không tải lên được CV — file tối đa '.self::CV_TOI_DA_MB.'MB.',
            'portfolio.mimes' => 'Portfolio phải là file PDF (hoặc dán link portfolio).',
            'portfolio.max' => 'Portfolio tối đa '.self::PORTFOLIO_TOI_DA_MB.'MB — file nặng hơn vui lòng dán link Google Drive.',
            'portfolio.uploaded' => 'Không tải lên được portfolio — tối đa '.self::PORTFOLIO_TOI_DA_MB.'MB, nặng hơn vui lòng dán link.',
            'dong_y.accepted' => 'Vui lòng đồng ý để PSV Travel dùng CV cho việc tuyển dụng.',
            'website.size' => 'Yêu cầu không hợp lệ.',
        ]);

        $xong = ['message' => 'Đã nhận hồ sơ. Bộ phận nhân sự sẽ liên hệ bạn nếu hồ sơ phù hợp.'];

        // Bấm gửi hai lần liền (mạng chậm) → không tạo hồ sơ trùng, không lưu file thừa
        $trung = JobApplication::query()
            ->where('job_posting_id', $tin->id)
            ->where('phone', $data['phone'])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();
        if ($trung) {
            return response()->json($xong, 201);
        }

        $cv = $request->file('cv');
        $portfolio = $request->file('portfolio');
        $duongCv = $cv->store(JobApplication::THU_MUC, JobApplication::DIA);
        $duongPortfolio = $portfolio?->store(JobApplication::THU_MUC, JobApplication::DIA);
        if (! $duongCv || ($portfolio && ! $duongPortfolio)) {
            Storage::disk(JobApplication::DIA)->delete(array_filter([$duongCv, $duongPortfolio]));

            return response()->json(['message' => 'Không lưu được file. Vui lòng thử lại sau ít phút.'], 500);
        }

        // forceFill: các cột này không nằm trong $fillable (trang quản trị không được sửa)
        $hs = tap((new JobApplication)->forceFill([
            'job_posting_id' => $tin->id,
            'job_title' => $tin->title,
            'full_name' => trim($data['full_name']),
            'phone' => trim($data['phone']),
            'email' => trim($data['email']),
            'message' => $data['message'] ?? null,
            'cv_path' => $duongCv,
            'cv_name' => self::tenGoc($cv),
            'portfolio_path' => $duongPortfolio,
            'portfolio_name' => $portfolio ? self::tenGoc($portfolio) : null,
            'portfolio_url' => $data['portfolio_url'] ?? null,
            'status' => 'moi',
            'ip' => $request->ip(),
        ]))->save();

        $bao->guiNoiBo($hs);
        $bao->guiUngVien($hs);

        return response()->json($xong, 201);
    }

    /** Tên file ứng viên đặt — chỉ để hiển thị; bỏ đường dẫn, ký tự điều khiển. */
    private static function tenGoc(UploadedFile $tep): string
    {
        $ten = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $tep->getClientOriginalName())));

        return Str::limit($ten !== '' ? $ten : 'tai-lieu.pdf', 120, '');
    }
}
