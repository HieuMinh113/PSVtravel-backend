<?php

namespace App\Filament\Resources\MaldivesCombos\Actions;

use App\Filament\Resources\MaldivesCombos\MaldivesComboResource;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Maldives\Models\MaldivesResort;
use Modules\Maldives\Services\NhapCombo\NhapComboTuFile;
use RuntimeException;
use Throwable;

/**
 * Nút "Nhập từ file Word/PDF" ở danh sách combo Maldives: tải file chương
 * trình của đối tác lên → xem trước những gì máy đọc được (resort, hạng
 * phòng, bảng giá theo ngày, lịch trình) → tạo combo NHÁP, hoặc chỉ cập nhật
 * bảng giá + ngày khởi hành của combo đã có → mở trang sửa combo.
 *
 * Ai được tạo combo (quyền Create:MaldivesCombo) thì dùng được; tạo resort
 * mới / thêm hạng phòng cần thêm quyền tạo / sửa resort.
 */
class NhapComboTuFileAction
{
    private const LOAI_FILE = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/pdf',
    ];

    public static function make(): Action
    {
        return Action::make('nhapTuFile')
            ->label('Nhập từ file Word/PDF')
            ->icon(Heroicon::OutlinedDocumentArrowUp)
            ->color('gray')
            ->visible(fn (): bool => (bool) auth()->user()?->can('create', MaldivesCombo::class))
            ->modalHeading('Nhập combo Maldives từ file chương trình (Word / PDF)')
            ->modalDescription('Tải file chương trình của đối tác lên: máy đọc resort, hạng phòng, bảng giá USD theo từng ngày khởi hành, phụ thu theo nơi đi, lịch trình, dịch vụ bao gồm / không bao gồm, rồi tạo một combo NHÁP (chưa hiện ngoài web) để bạn xem lại và đăng bán.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Nhập combo')
            ->schema([
                FileUpload::make('tep')
                    ->label('File chương trình combo')
                    ->acceptedFileTypes(self::LOAI_FILE)
                    ->maxSize(20480)
                    ->storeFiles(false)
                    ->required()
                    ->live()
                    ->helperText('File Word (.docx) hoặc PDF, tối đa 20MB.'),
                Text::make(fn (Get $get): HtmlString => self::xemTruoc(self::ketQua($get('tep'))))
                    ->visible(fn (Get $get): bool => self::tep($get('tep')) !== null),
                Radio::make('che_do')
                    ->label(fn (Get $get): string => 'Đã có combo "'.(self::ketQua($get('tep'))['ten_trung'] ?? '').'" — bạn muốn:')
                    ->options([
                        'cap_nhat' => 'Chỉ cập nhật bảng giá + ngày khởi hành (giữ nguyên lịch trình, nội dung, ảnh đã sửa)',
                        'tao_moi' => 'Tạo thêm một combo mới (nháp) từ file này',
                    ])
                    ->default('cap_nhat')
                    ->required(fn (Get $get): bool => (bool) (self::ketQua($get('tep'))['trung'] ?? null))
                    ->visible(fn (Get $get): bool => (bool) (self::ketQua($get('tep'))['trung'] ?? null)),
                Toggle::make('lay_anh')
                    ->label('Lấy ảnh trong file')
                    ->default(false)
                    ->helperText('Mặc định KHÔNG lấy: ảnh trong file đối tác có thể có bản quyền / logo đối tác. Chỉ bật khi được phép dùng ảnh — sau đó vẫn nên thay bằng ảnh resort cấp chính thức.')
                    ->visible(fn (Get $get): bool => (self::ketQua($get('tep'))['so_anh'] ?? 0) > 0),
            ])
            ->action(function (array $data, Action $action, $livewire) {
                $tep = self::tep($data['tep'] ?? null);
                if (! $tep) {
                    Notification::make()->title('Chưa có file')->danger()->send();
                    $action->halt();
                }
                $dichVu = app(NhapComboTuFile::class);
                $nguoi = auth()->user();
                $xemLai = [];

                try {
                    $kq = $dichVu->doc($tep->getRealPath(), $tep->getClientOriginalName());
                    $trung = $dichVu->comboTrung($kq);

                    if ($trung && ($data['che_do'] ?? 'cap_nhat') === 'cap_nhat') {
                        if (! $nguoi?->can('update', $trung)) {
                            throw new RuntimeException('Bạn không có quyền sửa combo "'.$trung->name.'".');
                        }
                        [$viec, $xemLai] = $dichVu->capNhat($trung, $kq);
                        $combo = $trung;
                        $tieuDe = 'Đã cập nhật bảng giá combo "'.$trung->name.'"';
                    } else {
                        $resort = $dichVu->resortTrung($kq);
                        if (! $resort && ! $nguoi?->can('create', MaldivesResort::class)) {
                            throw new RuntimeException('Chưa có resort "'.$kq['resort']['ten'].'" và bạn không có quyền tạo resort — nhờ quản trị tạo resort trước.');
                        }
                        [$combo, $viec] = $dichVu->tao($kq, (bool) ($data['lay_anh'] ?? false));
                        $tieuDe = 'Đã tạo combo nháp "'.$combo->name.'"';
                    }
                } catch (RuntimeException $e) {
                    Notification::make()->title('Không nhập được file')->body($e->getMessage())->danger()->persistent()->send();
                    $action->halt();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Không nhập được file')->body('File có cấu trúc lạ, máy chưa đọc được. Gửi file cho bộ phận kỹ thuật để bổ sung cách đọc.')->danger()->persistent()->send();
                    $action->halt();
                } finally {
                    // File của đối tác chỉ cần lúc đọc — không giữ lại trên máy chủ
                    $tep?->delete();
                }

                $canXem = [...$kq['canh_bao'], ...$xemLai];
                Notification::make()
                    ->title($tieuDe)
                    ->body(new HtmlString(e(Str::ucfirst(implode(', ', $viec))).'.'
                        .($canXem ? '<br><strong>Cần xem lại:</strong> '.e(implode(' ', $canXem)) : '')
                        .($combo->status === 'draft' ? '<br>Combo đang ở trạng thái <strong>Nháp</strong> — kiểm tra rồi đổi sang "Đang bán" để hiện ngoài web.' : '')))
                    ->success()
                    ->persistent()
                    ->send();

                $livewire->redirect(MaldivesComboResource::getUrl('edit', ['record' => $combo]));
            });
    }

    /** File vừa tải lên (FileUpload không lưu file → giữ dạng file tạm của Livewire) */
    private static function tep(mixed $trangThai): ?TemporaryUploadedFile
    {
        $tep = is_array($trangThai) ? (array_values($trangThai)[0] ?? null) : $trangThai;
        if (is_string($tep) && TemporaryUploadedFile::canUnserialize($tep)) {
            $tep = TemporaryUploadedFile::unserializeFromLivewireRequest($tep);
        }

        return $tep instanceof TemporaryUploadedFile && $tep->exists() ? $tep : null;
    }

    /**
     * Đọc file để xem trước. Khung hộp thoại vẽ lại sau mỗi thao tác nên kết
     * quả (chỉ phần chữ, không giữ ảnh) được nhớ tạm theo nội dung file.
     *
     * @return array<string, mixed>
     */
    private static function ketQua(mixed $trangThai): array
    {
        $tep = self::tep($trangThai);
        if (! $tep) {
            return [];
        }

        $kq = Cache::remember('nhap-maldives:'.md5_file($tep->getRealPath()), now()->addMinutes(30), function () use ($tep) {
            try {
                $kq = app(NhapComboTuFile::class)->doc($tep->getRealPath(), $tep->getClientOriginalName());
            } catch (RuntimeException $e) {
                return ['loi' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);

                return ['loi' => 'File có cấu trúc lạ, máy chưa đọc được. Gửi file cho bộ phận kỹ thuật để bổ sung cách đọc.'];
            }

            // chỉ giữ phần chữ cần để xem trước / tra trùng (không giữ ảnh)
            return ['kq' => array_diff_key($kq, ['anh' => 1]), 'so_anh' => count($kq['anh']), 'so_anh_ngay' => collect($kq['anh'])->whereNotNull('ngay')->count()];
        });
        if (isset($kq['loi'])) {
            return $kq;
        }

        // Combo / resort trùng tra lại mỗi lần (không nhớ tạm): vừa nhập xong mà tải lại
        // đúng file đó thì phải hỏi "cập nhật hay tạo mới"
        $dichVu = app(NhapComboTuFile::class);
        $trung = $dichVu->comboTrung($kq['kq']);
        $resort = $dichVu->resortTrung($kq['kq']);

        return $kq + [
            'trung' => $trung?->id, 'ten_trung' => $trung?->name,
            'resort_co' => $resort?->name,
            'phong_co' => $resort ? $resort->roomTypes()->pluck('name')->map(fn ($t) => Str::slug($t))->all() : [],
        ];
    }

    private static function xemTruoc(array $kq): HtmlString
    {
        if (! $kq) {
            return new HtmlString('');
        }
        if (isset($kq['loi'])) {
            return new HtmlString('<div style="border-radius:0.5rem;padding:0.75rem 1rem;background:#fef2f2;color:#b91c1c;font-size:0.875rem">'.e($kq['loi']).'</div>');
        }
        $k = $kq['kq'];
        $r = $k['resort'];
        $usd = fn (?int $v) => $v === null ? 'NA' : '$'.number_format($v, 0, ',', '.');
        $phongMoi = array_filter($k['phong'], fn ($p) => ! in_array(Str::slug($p['ten']), $kq['phong_co'], true));

        $dong = array_values(array_filter([
            $kq['resort_co']
                ? 'Resort: đã có "'.$kq['resort_co'].'" — giữ nguyên giới thiệu / tiện ích / quy định'.($phongMoi ? ', thêm '.count($phongMoi).' hạng phòng mới' : '')
                : 'Resort mới: '.$r['ten'].($r['sao'] ? ' '.str_replace('.', ',', (string) $r['sao']).'*' : '').($r['dao'] ? ' · '.$r['dao'] : '')
                    .' · '.count($r['tien_ich']).' nhóm tiện ích · '.count($r['quy_dinh']).' mục quy định',
            implode(' · ', array_filter([
                $k['so_ngay'] ? "{$k['so_ngay']} ngày {$k['so_dem']} đêm" : null,
                $k['hang_bay'] ? 'bay '.$k['hang_bay'] : null,
                $k['van_chuyen'],
                $k['luu_tru'],
            ])),
            'Nơi khởi hành: '.(collect($k['noi_khoi_hanh'])->map(fn ($n) => $n['city'].($n['surcharge_usd'] === null ? ' (phụ thu: liên hệ)' : ($n['surcharge_usd'] ? ' (+$'.$n['surcharge_usd'].'/khách)' : '')))->implode(', ') ?: 'chưa thấy'),
            $k['tre_em_phan_tram'] ? 'Trẻ em'.($k['tuoi_tre_em'] ? " ({$k['tuoi_tre_em']})" : '').": {$k['tre_em_phan_tram']}% giá người lớn" : null,
            $r['chi_nguoi_lon'] ? 'Resort chỉ nhận khách từ 18 tuổi — các hạng phòng được đánh dấu "chỉ người lớn", website ẩn ô trẻ em / em bé' : null,
            count($k['lich_trinh']).' ngày lịch trình · '.count($k['bao_gom']).' mục bao gồm · '.count($k['khong_bao_gom']).' mục không bao gồm · '.count($k['luu_y']).' mục lưu ý',
            $kq['so_anh'] ? 'Ảnh trong file: '.$kq['so_anh'].' ảnh ('.$kq['so_anh_ngay'].' ảnh trong lịch trình) — chỉ lấy khi bật "Lấy ảnh trong file"' : null,
        ]));

        $html = '<div style="font-size:0.875rem;line-height:1.6;border:1px solid #e5e7eb;border-radius:0.75rem;padding:0.875rem 1rem">'
            .'<p style="font-weight:700;font-size:1rem">'.e($k['ten'] ?: '(chưa nhận ra tên combo)').'</p>'
            .'<ul style="list-style:disc;padding-left:1.25rem;margin-top:0.25rem">'
            .collect($dong)->map(fn ($d) => '<li>'.e($d).'</li>')->implode('')
            .'</ul>';

        // Bảng giá: hạng phòng × ngày khởi hành (bảng rộng thì cuộn ngang trong khung, không đẩy rộng hộp thoại)
        if ($k['phong'] && $k['ngay']) {
            $o = 'style="border:1px solid #e5e7eb;padding:0.25rem 0.5rem;white-space:nowrap"';
            $html .= '<p style="font-weight:600;margin-top:0.75rem">Bảng giá (USD / người lớn)</p><div style="overflow-x:auto;width:0;min-width:100%"><table style="border-collapse:collapse;font-size:0.8125rem;margin-top:0.25rem"><tr><th '.$o.'>Hạng phòng</th>'
                .collect($k['ngay'])->map(fn ($n) => '<th '.$o.'>'.e(Carbon::parse($n['ngay'])->format('d/m/Y')).($n['nhan'] ? '<br><span style="font-weight:400">'.e($n['nhan']).'</span>' : '').'</th>')->implode('')
                .'</tr>';
            foreach ($k['phong'] as $p => $phong) {
                $html .= '<tr><td '.$o.'>'.e($phong['ten']).($phong['chi_nguoi_lon'] ? ' <span style="color:#b45309">(chỉ người lớn)</span>' : '')
                    .(in_array(Str::slug($phong['ten']), $kq['phong_co'], true) ? '' : ($kq['resort_co'] ? ' <span style="color:#047857">(mới)</span>' : ''))
                    .($phong['suc_chua'] ? '<br><span style="color:#6b7280">'.e($phong['suc_chua']).'</span>' : '').'</td>'
                    .collect($k['ngay'])->map(fn ($n) => '<td '.$o.' align="right">'.e($usd($n['gia'][$p] ?? null)).'</td>')->implode('').'</tr>';
            }
            $html .= '</table></div><p style="color:#6b7280;font-size:0.8125rem">NA = ngày đó không bán hạng phòng đó.</p>';
        }
        if ($k['lich_trinh']) {
            $html .= '<p style="font-weight:600;margin-top:0.5rem">Lịch trình</p><ul style="list-style:none;padding-left:0;color:#374151">'
                .collect($k['lich_trinh'])->map(fn ($n) => '<li>'.e("Ngày {$n['ngay']}: {$n['tieu_de']}".($n['bua'] ? " ({$n['bua']})" : '')).'</li>')->implode('').'</ul>';
        }
        $html .= '</div>';
        if ($k['canh_bao']) {
            $html .= '<div style="margin-top:0.75rem;border-radius:0.5rem;padding:0.75rem 1rem;background:#fffbeb;color:#92400e;font-size:0.875rem;line-height:1.6">'
                .'<strong>Cần xem lại sau khi nhập:</strong><ul style="list-style:disc;padding-left:1.25rem">'
                .collect($k['canh_bao'])->map(fn ($c) => '<li>'.e($c).'</li>')->implode('').'</ul></div>';
        }

        return new HtmlString($html);
    }
}
