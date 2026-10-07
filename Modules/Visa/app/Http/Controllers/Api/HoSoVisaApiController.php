<?php

namespace Modules\Visa\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Models\VisaChecklist;
use Modules\Visa\Models\VisaCountry;
use Modules\Visa\Services\BaoHoSoVisaMoi;
use Modules\Visa\Transformers\HoSoVisaKhachResource;

/**
 * Khách tự nộp hồ sơ visa trên website.
 *
 * Hai bước, vì giới hạn 20MB mỗi lần gửi của nginx không chứa nổi 10 file ×
 * 10MB trong một lần:
 *   1. POST visa-applications            → tạo hồ sơ, trả mã hồ sơ + mã tải file
 *   2. POST visa-applications/{mã}/files → gửi từng file (≤ 10MB), tối đa 10 file
 * Mã tải file chỉ dùng được cho đúng hồ sơ đó, trong 2 giờ.
 *
 * Không bắt buộc đăng nhập. Đang đăng nhập (token gửi kèm qua route của Next)
 * thì hồ sơ gắn vào tài khoản để khách theo dõi ở trang Tài khoản.
 */
class HoSoVisaApiController extends Controller
{
    public const TOI_DA_FILE = 10;

    public const LOAI_FILE = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    // POST /api/v1/visa-applications
    public function store(Request $request, BaoHoSoVisaMoi $bao): JsonResponse
    {
        $data = $request->validate([
            'visa_country' => ['required', 'string', Rule::exists('visa_countries', 'slug')
                ->where('status', 'published')->whereNull('deleted_at')],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9\s+.-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'purpose' => ['required', Rule::in(array_keys(VisaCase::MUC_DICH))],
            'profile' => ['nullable', Rule::in(array_keys(VisaCase::DOI_TUONG))],
            'travel_date' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
            'dong_y' => ['accepted'],
            // Ô bẫy: người thật không thấy nên luôn để trống
            'website' => ['nullable', 'size:0'],
        ], [
            'visa_country.*' => 'Nước làm visa không hợp lệ.',
            'full_name.required' => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'email.email' => 'Email không hợp lệ.',
            'birth_date.before' => 'Ngày sinh không hợp lệ.',
            'purpose.*' => 'Vui lòng chọn mục đích chuyến đi.',
            'profile.in' => 'Đối tượng không hợp lệ.',
            'travel_date.after_or_equal' => 'Ngày đi dự kiến phải từ hôm nay trở đi.',
            'dong_y.accepted' => 'Vui lòng đồng ý để PSV Travel dùng thông tin này làm hồ sơ visa.',
            'website.size' => 'Yêu cầu không hợp lệ.',
        ]);

        $nuoc = VisaCountry::where('slug', $data['visa_country'])->firstOrFail();
        $maTai = Str::random(48);

        // Bấm gửi hai lần liền (mạng chậm) → trả lại hồ sơ vừa tạo, không tạo trùng
        $trung = VisaCase::query()
            ->where('source', 'website')
            ->where('phone', $data['phone'])
            ->where('country', $nuoc->name)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest('id')
            ->first();

        if ($trung) {
            $trung->forceFill(['upload_token_hash' => hash('sha256', $maTai), 'upload_token_expires_at' => now()->addHours(2)])->save();

            return $this->daNhan($trung, $maTai);
        }

        $mau = VisaChecklist::timMau($nuoc->name, $data['purpose'], $data['profile'] ?? null);

        // forceFill: mã tải file / nguồn không nằm trong $fillable (form quản
        // trị không được đụng tới), nhưng ở đây là máy tự đặt.
        $hs = DB::transaction(fn () => tap((new VisaCase)->forceFill([
            'source' => 'website',
            'user_id' => auth('sanctum')->id(),
            'full_name' => mb_strtoupper(trim($data['full_name'])),
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'country' => $nuoc->name,
            'purpose' => $data['purpose'],
            'profile' => $data['profile'] ?? null,
            'travel_date' => $data['travel_date'] ?? null,
            'visa_checklist_id' => $mau?->id,
            'checklist' => VisaCase::chepMau($mau),
            'customer_note' => $data['note'] ?? null,
            'status' => 'moi',
            'upload_token_hash' => hash('sha256', $maTai),
            'upload_token_expires_at' => now()->addHours(2),
        ]))->save())->fresh();

        $bao->guiNoiBo($hs);
        $bao->guiKhach($hs);

        return $this->daNhan($hs, $maTai, 201);
    }

    // POST /api/v1/visa-applications/{code}/files — một file mỗi lần
    public function storeFile(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:100'],
            'file' => ['required', File::types(self::LOAI_FILE)->max(10 * 1024)],
        ], [
            'file.required' => 'Chưa chọn file.',
            'file.mimes' => 'Chỉ nhận ảnh (JPG, PNG, WEBP) hoặc PDF.',
            'file.max' => 'File lớn hơn 10MB.',
        ]);

        $hs = VisaCase::where('code', $code)->where('source', 'website')->first();
        $hopLe = $hs
            && $hs->upload_token_hash
            && hash_equals($hs->upload_token_hash, hash('sha256', (string) $request->input('token')))
            && $hs->upload_token_expires_at?->isFuture();

        if (! $hopLe) {
            return response()->json(['message' => 'Phiên tải file đã hết hạn. Vui lòng gửi giấy tờ qua Zalo cho nhân viên phụ trách.'], 403);
        }

        $tep = $request->file('file');
        $duong = $tep->store('ho-so-visa', 'rieng');

        // Khoá dòng hồ sơ khi đếm: khách gửi song song nhiều file cùng lúc vẫn
        // không vượt quá 10.
        $duoc = DB::transaction(function () use ($hs, $duong, $tep) {
            $hs = VisaCase::whereKey($hs->id)->lockForUpdate()->first();
            if ($hs->web_files_count >= self::TOI_DA_FILE) {
                return false;
            }
            $hs->forceFill([
                'files' => [...($hs->files ?? []), $duong],
                'file_names' => [...($hs->file_names ?? []), $duong => self::tenGoc($tep->getClientOriginalName())],
                'web_files_count' => $hs->web_files_count + 1,
            ])->save();

            return true;
        });

        if (! $duoc) {
            Storage::disk('rieng')->delete($duong);

            return response()->json(['message' => 'Đã đủ '.self::TOI_DA_FILE.' file. Giấy tờ khác vui lòng gửi cho nhân viên phụ trách.'], 422);
        }

        return response()->json(['message' => 'Đã nhận file.'], 201);
    }

    // GET /api/v1/auth/visa-cases — hồ sơ visa của khách đang đăng nhập
    public function mine(Request $request)
    {
        $ds = VisaCase::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(50)
            ->get();

        return HoSoVisaKhachResource::collection($ds);
    }

    private function daNhan(VisaCase $hs, string $maTai, int $ma = 200): JsonResponse
    {
        return response()->json([
            'message' => 'Đã nhận hồ sơ. Chuyên viên visa sẽ liên hệ bạn sớm.',
            'data' => [
                'code' => $hs->code,
                'upload_token' => $maTai,
                'max_files' => max(0, self::TOI_DA_FILE - $hs->web_files_count),
                'documents' => collect($hs->checklist ?? [])->pluck('ten')->values(),
            ],
        ], $ma);
    }

    /** Tên file khách đặt — chỉ để hiển thị; bỏ đường dẫn, ký tự điều khiển. */
    private static function tenGoc(string $ten): string
    {
        $ten = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $ten)));

        return Str::limit($ten !== '' ? $ten : 'giay-to', 120, '');
    }
}
