<?php

namespace App\Services\Voucher;

use App\Jobs\XuatFileVoucher;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Xuất file in / ảnh voucher cho trang quản trị.
 *
 * Ít mã: tạo ngay rồi chuyển trình duyệt sang link tải. Nhiều mã: tạo trong
 * hàng đợi, xong báo chuông kèm nút tải. File cất ở ổ riêng (không công khai —
 * mã voucher là "tiền"), link tải có chữ ký + id người xuất, tải xong xoá; file
 * không ai tải thì lịch hằng ngày dọn.
 */
class XuatVoucher
{
    public const THU_MUC = 'voucher-xuat';

    /** Kiểu file: 3 kiểu PDF (InVoucher::KIEU) + ZIP ảnh */
    public static function kieu(): array
    {
        return InVoucher::KIEU + ['anh' => 'Ảnh JPG từng voucher (ZIP) — gửi khách qua Zalo / Facebook'];
    }

    /**
     * @param  list<int>  $ids
     * @return array{url: ?string, nen: bool} url = link tải ngay; nen = đang tạo trong hàng đợi
     */
    public static function chay(VoucherBatch $d, string $kieu, array $ids, User $u): array
    {
        $gioiHan = $kieu === 'anh' ? 30 : InVoucher::TOI_DA_TAO_NGAY;
        if (count($ids) > $gioiHan) {
            XuatFileVoucher::dispatch($d->id, $kieu, $ids, $u->id);

            return ['url' => null, 'nen' => true];
        }
        [$tep, $ten] = self::tao($d, $kieu, $ids);

        return ['url' => self::cat($tep, $ten, $u->id, 10), 'nen' => false];
    }

    /**
     * @param  list<int>  $ids
     * @return array{0: string, 1: string} [file tạm, tên file tải về]
     */
    public static function tao(VoucherBatch $d, string $kieu, array $ids): array
    {
        $ds = Voucher::query()->whereIn('id', $ids)->where('batch_id', $d->id)->orderBy('serial')->get();
        $ten = 'voucher-'.(Str::slug($d->name) ?: 'psv').'-'.$ds->count().'-ma';
        $in = new InVoucher;
        if ($kieu === 'anh') {
            return [$in->zipAnh($d, $ds), $ten.'-anh.zip'];
        }
        $tep = tempnam(sys_get_temp_dir(), 'voucher');
        file_put_contents($tep, $in->pdf($d, $ds, $kieu));

        return [$tep, $ten.'-'.str_replace('_', '-', $kieu).'.pdf'];
    }

    /** Chuyển file tạm vào ổ riêng, trả link tải có hạn */
    public static function cat(string $tepTam, string $tenTai, int $nguoi, int $phut): string
    {
        $ma = (string) Str::uuid();
        Storage::disk('rieng')->makeDirectory(self::THU_MUC);
        rename($tepTam, Storage::disk('rieng')->path(self::duong($ma)));

        return URL::temporarySignedRoute('voucher.tai', now()->addMinutes($phut), ['ma' => $ma, 'u' => $nguoi, 'ten' => $tenTai]);
    }

    public static function duong(string $ma): string
    {
        return self::THU_MUC.'/'.$ma;
    }

    /** Xoá file xuất cũ hơn 1 ngày (chưa ai tải) */
    public static function donDep(): int
    {
        $dia = Storage::disk('rieng');
        $xoa = 0;
        clearstatcache();
        foreach ($dia->files(self::THU_MUC) as $f) {
            if ($dia->lastModified($f) < now()->subDay()->getTimestamp()) {
                $dia->delete($f);
                $xoa++;
            }
        }

        return $xoa;
    }
}
