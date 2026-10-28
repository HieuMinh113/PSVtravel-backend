<?php

namespace App\Filament\Resources\VoucherBatches\Schemas;

use App\Models\VoucherBatch;
use App\Services\TepTaiLen;
use App\Services\Voucher\InVoucher;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Đợt voucher. Phần "Mẫu in": ảnh 2 mặt do thiết kế làm sẵn (khổ 155×100 mm),
 * hệ thống chỉ đặt thêm ô trắng chứa QR + mã ở góc đã chọn — xem thử ngay
 * trên ảnh thật khi đổi góc / cỡ / lề.
 */
class VoucherBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Thông tin đợt')->columns(2)->schema([
                TextInput::make('name')->label('Tên đợt')->required()->maxLength(255)
                    ->placeholder('Ốc & Du lịch 200K — 2026')->helperText('Khách thấy tên này khi kiểm tra mã.'),
                TextInput::make('value')->label('Mệnh giá')->numeric()->minValue(1000)->maxValue(1_000_000_000)->step(1000)->suffix('₫')->required()
                    ->helperText('Hệ thống không tự trừ tiền — khi khách dùng, nhân viên trừ vào đơn như bình thường.'),
                DatePicker::make('expires_at')->label('Hạn sử dụng (hết ngày)')->native(false)->displayFormat('d/m/Y')->required()
                    ->minDate(fn (?VoucherBatch $record) => $record ? null : today()),
                Select::make('status')->label('Trạng thái')->options(VoucherBatch::TRANG_THAI)->default('active')->required()->selectablePlaceholder(false)
                    ->helperText('Tạm ngưng: khách kiểm tra thấy "Đợt tạm ngưng", nhân viên không xác nhận dùng được.'),
                TextInput::make('applies_to')->label('Áp dụng cho')->maxLength(255)
                    ->placeholder('Dịch vụ ăn uống, tour trong nước…'),
                TextInput::make('code_prefix')->label('Chữ đầu mã')->default('PSV')->maxLength(5)
                    ->helperText('2–5 chữ in hoa / số, VD PSV → mã PSV-7KQ3-MZ8T. Không đổi được khi đợt đã có mã.')
                    ->rule('regex:/^[A-Za-z0-9]{2,5}$/')
                    ->disabled(fn (?VoucherBatch $record) => $record?->vouchers()->exists() ?? false),
                Textarea::make('conditions')->label('Điều kiện sử dụng')->rows(3)->columnSpanFull()
                    ->placeholder('Không quy đổi thành tiền mặt. Mỗi đơn dùng tối đa 1 voucher…')
                    ->helperText('Hiện cho khách ở trang kiểm tra voucher.'),
                Textarea::make('note')->label('Ghi chú nội bộ')->rows(2)->columnSpanFull()
                    ->helperText('Chỉ nhân viên thấy, VD: in 500 tờ cho hội chợ, phát ở quầy.'),
            ]),

            Section::make('Mẫu in')
                ->description('Ảnh do thiết kế làm sẵn, khổ thành phẩm 155×100 mm (hoặc có tràn lề 3 mm: 161×106 mm). Xuất JPG / PNG từ 1800 px ngang. Nên chừa trống một góc cho ô mã.')
                ->columns(4)
                ->schema([
                    FileUpload::make('front_image')->label('Mặt trước')->image()->acceptedFileTypes(TepTaiLen::ANH)
                        ->directory('voucher')->disk('public')->maxSize(15360)->columnSpan(2),
                    FileUpload::make('back_image')->label('Mặt sau (không bắt buộc)')->image()->acceptedFileTypes(TepTaiLen::ANH)
                        ->directory('voucher')->disk('public')->maxSize(15360)->columnSpan(2),
                    Select::make('code_side')->label('Ô mã nằm ở')->options(VoucherBatch::MAT)->default('front')->required()->selectablePlaceholder(false)->live(),
                    Select::make('code_corner')->label('Góc')->options(VoucherBatch::GOC)->default('bottom_right')->required()->selectablePlaceholder(false)->live(),
                    TextInput::make('code_size_mm')->label('Bề rộng ô mã')->numeric()->minValue(20)->maxValue(45)->step(1)->default(28)->suffix('mm')->required()->live(onBlur: true),
                    TextInput::make('code_margin_mm')->label('Cách mép cắt')->numeric()->minValue(3)->maxValue(20)->step(0.5)->default(7)->suffix('mm')->required()->live(onBlur: true),
                    Toggle::make('show_barcode')->label('In thêm mã vạch (cho máy quét đời cũ chỉ đọc mã vạch)')->live()->columnSpanFull()
                        ->helperText('Điện thoại và máy quét 2D đọc QR là đủ. Bật thì ô mã rộng tối thiểu 36 mm.'),
                    View::make('filament.voucher.xem-thu')->columnSpanFull()
                        ->viewData(fn (Get $get, ?VoucherBatch $record) => self::xemThu($get, $record)),
                ]),
        ]);
    }

    /** @return array<string, mixed> */
    private static function xemThu(Get $get, ?VoucherBatch $d): array
    {
        $mat = $get('code_side') === 'back' ? 'back' : 'front';
        $tep = $d ? ($mat === 'back' && $d->back_image ? $d->back_image : $d->front_image) : null;
        $coAnh = $tep && Storage::disk('public')->exists($tep);

        // Khung mô phỏng khi chưa có ảnh đã lưu: ô mã theo tỉ lệ thật
        $tam = new VoucherBatch([
            'code_corner' => $get('code_corner'), 'code_size_mm' => (float) $get('code_size_mm'),
            'code_margin_mm' => (float) $get('code_margin_mm'), 'show_barcode' => (bool) $get('show_barcode'),
        ]);
        $o = InVoucher::oMa($tam);

        return [
            'url' => $coAnh ? route('voucher.xem-thu', [
                'dot' => $d->id, 'mat' => $mat, 'goc' => $get('code_corner'),
                'co' => (float) $get('code_size_mm') ?: null, 'le' => (float) $get('code_margin_mm') ?: null,
                'vach' => (int) (bool) $get('show_barcode'), 't' => $d->updated_at?->timestamp,
            ]) : null,
            'o' => $o,
            'chuaLuu' => ! $d,
        ];
    }
}
