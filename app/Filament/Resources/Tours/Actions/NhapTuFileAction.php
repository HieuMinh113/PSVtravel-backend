<?php

namespace App\Filament\Resources\Tours\Actions;

use App\Filament\Resources\Tours\TourResource;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Tour\Models\Tour;
use Modules\Tour\Services\NhapTour\NhapTourTuFile;
use RuntimeException;
use Throwable;

/**
 * Nút "Nhập từ file Word/PDF" ở danh sách tour: tải file chương trình tour
 * của đối tác lên → xem trước những gì máy đọc được → tạo tour NHÁP (hoặc cập
 * nhật giá + ngày khởi hành của tour cùng tên đã có) → mở trang sửa tour.
 *
 * Ai được tạo tour (quyền Create:Tour) thì dùng được.
 */
class NhapTuFileAction
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
            ->visible(fn (): bool => (bool) auth()->user()?->can('create', Tour::class))
            ->modalHeading('Nhập tour từ file chương trình (Word / PDF)')
            ->modalDescription('Tải file chương trình tour của đối tác lên: máy đọc tên tour, giá, ngày khởi hành, lịch trình từng ngày và ảnh trong file, rồi tạo một tour NHÁP (chưa hiện ngoài web) để bạn xem lại, sửa và đăng bán.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Nhập tour')
            ->schema([
                FileUpload::make('tep')
                    ->label('File chương trình tour')
                    ->acceptedFileTypes(self::LOAI_FILE)
                    ->maxSize(20480)
                    ->storeFiles(false)
                    ->required()
                    ->live()
                    ->helperText('File Word (.docx) hoặc PDF, tối đa 20MB. File Word cho kết quả chính xác hơn PDF.'),
                Text::make(fn (Get $get): HtmlString => self::xemTruoc(self::ketQua($get('tep'))))
                    ->visible(fn (Get $get): bool => self::tep($get('tep')) !== null),
                Radio::make('che_do')
                    ->label(fn (Get $get): string => 'Đã có tour "'.(self::ketQua($get('tep'))['ten_trung'] ?? '').'" — bạn muốn:')
                    ->options([
                        'cap_nhat' => 'Cập nhật giá + ngày khởi hành cho tour đã có (giữ nguyên nội dung, ảnh, số chỗ đã sửa)',
                        'tao_moi' => 'Tạo thêm một tour mới (nháp) từ file này',
                    ])
                    ->default('cap_nhat')
                    ->required(fn (Get $get): bool => (bool) (self::ketQua($get('tep'))['trung'] ?? null))
                    ->visible(fn (Get $get): bool => (bool) (self::ketQua($get('tep'))['trung'] ?? null)),
            ])
            ->action(function (array $data, Action $action, $livewire) {
                $tep = self::tep($data['tep'] ?? null);
                if (! $tep) {
                    Notification::make()->title('Chưa có file')->danger()->send();
                    $action->halt();
                }
                $dichVu = app(NhapTourTuFile::class);

                try {
                    $kq = $dichVu->doc($tep->getRealPath(), $tep->getClientOriginalName());
                    $trung = $dichVu->tourTrung($kq);

                    if ($trung && ($data['che_do'] ?? 'cap_nhat') === 'cap_nhat') {
                        if (! auth()->user()?->can('update', $trung)) {
                            throw new RuntimeException('Bạn không có quyền sửa tour "'.$trung->name.'".');
                        }
                        $viec = $dichVu->capNhat($trung, $kq);
                        $tour = $trung;
                        $tieuDe = 'Đã cập nhật tour "'.$trung->name.'"';
                    } else {
                        [$tour, $viec] = $dichVu->tao($kq);
                        $tieuDe = 'Đã tạo tour nháp "'.$tour->name.'"';
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

                Notification::make()
                    ->title($tieuDe)
                    ->body(new HtmlString(e(ucfirst(implode(', ', $viec))).'.'
                        .($kq['canh_bao'] ? '<br><strong>Cần xem lại:</strong> '.e(implode(' ', $kq['canh_bao'])) : '')
                        .($tour->status === 'draft' ? '<br>Tour đang ở trạng thái <strong>Nháp</strong> — kiểm tra rồi đổi sang "Đang bán" để hiện ngoài web.' : '')))
                    ->success()
                    ->persistent()
                    ->send();

                $livewire->redirect(TourResource::getUrl('edit', ['record' => $tour]));
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
     * @return array{loi?: string, ten?: string, dong?: list<string>, ngay?: list<string>, canh_bao?: list<string>, trung?: ?int, ten_trung?: ?string}
     */
    private static function ketQua(mixed $trangThai): array
    {
        $tep = self::tep($trangThai);
        if (! $tep) {
            return [];
        }

        $kq = Cache::remember('nhap-tour:'.md5_file($tep->getRealPath()), now()->addMinutes(30), function () use ($tep) {
            $dichVu = app(NhapTourTuFile::class);
            try {
                $kq = $dichVu->doc($tep->getRealPath(), $tep->getClientOriginalName());
            } catch (RuntimeException $e) {
                return ['loi' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);

                return ['loi' => 'File có cấu trúc lạ, máy chưa đọc được. Gửi file cho bộ phận kỹ thuật để bổ sung cách đọc.'];
            }
            $tien = fn (?int $v) => $v ? number_format($v, 0, ',', '.').'đ' : null;
            $dot = collect($kq['dot']);
            $soAnhNgay = collect($kq['anh'])->whereNotNull('ngay')->count();

            return [
                'ten' => $kq['ten'],
                'dong' => array_values(array_filter([
                    implode(' · ', array_filter([
                        $kq['loai'] === 'abroad' ? 'Tour nước ngoài' : 'Tour trong nước',
                        $kq['quoc_gia'],
                        $kq['so_ngay'] ? "{$kq['so_ngay']} ngày {$kq['so_dem']} đêm" : null,
                        $kq['khoi_hanh_tu'] ? 'khởi hành từ '.$kq['khoi_hanh_tu'] : null,
                        $kq['nhan'],
                    ])),
                    'Giá: '.(implode(' · ', array_filter([
                        $tien($kq['gia_nguoi_lon']) ? 'người lớn '.$tien($kq['gia_nguoi_lon']) : null,
                        $tien($kq['gia_tre_em']) ? 'trẻ em '.$tien($kq['gia_tre_em']) : null,
                        $tien($kq['gia_em_be']) ? 'em bé '.$tien($kq['gia_em_be']) : null,
                    ])) ?: 'chưa thấy'),
                    'Ngày khởi hành: '.($dot->isEmpty() ? 'chưa thấy ngày nào còn đi được' : $dot->count().' ngày, từ '
                        .Carbon::parse($dot->first()['ngay'])->format('d/m/Y').' đến '.Carbon::parse($dot->last()['ngay'])->format('d/m/Y')
                        .' — mỗi ngày '.NhapTourTuFile::SO_CHO.' chỗ'),
                    $kq['han_visa_ngay'] ? "Hạn nộp hồ sơ visa: trước ngày khởi hành {$kq['han_visa_ngay']} ngày" : null,
                    'Ảnh: '.count($kq['anh']).' ảnh ('.$soAnhNgay.' ảnh gắn vào từng ngày)',
                    count($kq['noi_bat']).' điểm nổi bật · '.count($kq['bao_gom']).' mục bao gồm · '.count($kq['khong_bao_gom']).' mục không bao gồm · '.count($kq['luu_y']).' mục lưu ý',
                ])),
                'ngay' => array_map(fn ($n) => "Ngày {$n['ngay']}: {$n['tieu_de']}".($n['bua'] ? " ({$n['bua']})" : ''), $kq['lich_trinh']),
                'canh_bao' => $kq['canh_bao'],
            ];
        });

        // Tour trùng tên tra lại mỗi lần (không nhớ tạm): vừa nhập xong mà tải lại
        // đúng file đó thì phải hỏi "cập nhật hay tạo mới"
        $trung = isset($kq['ten']) ? app(NhapTourTuFile::class)->tourTrung($kq) : null;

        return $kq + ['trung' => $trung?->id, 'ten_trung' => $trung?->name];
    }

    private static function xemTruoc(array $kq): HtmlString
    {
        if (! $kq) {
            return new HtmlString('');
        }
        if (isset($kq['loi'])) {
            return new HtmlString('<div style="border-radius:0.5rem;padding:0.75rem 1rem;background:#fef2f2;color:#b91c1c;font-size:0.875rem">'.e($kq['loi']).'</div>');
        }

        $html = '<div style="font-size:0.875rem;line-height:1.6;border:1px solid #e5e7eb;border-radius:0.75rem;padding:0.875rem 1rem">'
            .'<p style="font-weight:700;font-size:1rem">'.e($kq['ten'] ?: '(chưa nhận ra tên tour)').'</p>'
            .'<ul style="list-style:disc;padding-left:1.25rem;margin-top:0.25rem">'
            .collect($kq['dong'])->map(fn ($d) => '<li>'.e($d).'</li>')->implode('')
            .'</ul>';
        if ($kq['ngay']) {
            $html .= '<p style="font-weight:600;margin-top:0.5rem">Lịch trình</p><ul style="list-style:none;padding-left:0;color:#374151">'
                .collect($kq['ngay'])->map(fn ($d) => '<li>'.e($d).'</li>')->implode('').'</ul>';
        }
        $html .= '</div>';
        if ($kq['canh_bao']) {
            $html .= '<div style="margin-top:0.75rem;border-radius:0.5rem;padding:0.75rem 1rem;background:#fffbeb;color:#92400e;font-size:0.875rem;line-height:1.6">'
                .'<strong>Cần xem lại sau khi nhập:</strong><ul style="list-style:disc;padding-left:1.25rem">'
                .collect($kq['canh_bao'])->map(fn ($c) => '<li>'.e($c).'</li>')->implode('').'</ul></div>';
        }
        $html .= '<p style="margin-top:0.75rem;color:#6b7280;font-size:0.8125rem">Ảnh lấy từ file của đối tác có thể có bản quyền / logo đối tác — xem lại và thay ảnh nếu cần trước khi đăng bán.</p>';

        return new HtmlString($html);
    }
}
