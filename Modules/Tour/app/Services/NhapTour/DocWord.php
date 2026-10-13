<?php

namespace Modules\Tour\Services\NhapTour;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Đọc file chương trình tour .docx thành một dãy "mục" theo đúng thứ tự trong
 * file: dòng chữ (kèm in đậm / gạch đầu dòng / ô bảng) và ảnh.
 *
 * Đọc thẳng XML của Word thay vì qua thư viện: cần biết ảnh nằm ở ĐÂU so với
 * chữ (để gắn ảnh vào đúng ngày), và bỏ ảnh ở đầu trang / chân trang (logo,
 * băng rôn công ty) — thư viện đọc Word không cho biết hai điều này.
 *
 * Mỗi mục:
 *  - ['loai' => 'chu', 'chu' => string, 'dam' => bool, 'gach' => bool, 'o' => ?list<string>]
 *    ('o' = các ô khi là một hàng bảng có nhiều ô)
 *  - ['loai' => 'anh', 'du_lieu' => bytes, 'ten' => string]
 */
class DocWord
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private ZipArchive $zip;

    private DOMXPath $xp;

    /** @var array<string,string> rId → đường dẫn trong file zip */
    private array $quanHe = [];

    /** @var list<array> */
    private array $muc = [];

    /** @return list<array> */
    public function doc(string $duong): array
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($duong) !== true || $this->zip->locateName('word/document.xml') === false) {
            throw new RuntimeException('File không phải Word .docx hợp lệ (có thể là .doc cũ — mở bằng Word rồi "Lưu thành" .docx).');
        }

        $rels = (string) $this->zip->getFromName('word/_rels/document.xml.rels');
        if (preg_match_all('/<Relationship\b[^>]*>/', $rels, $ds)) {
            foreach ($ds[0] as $the) {
                if (preg_match('/Id="([^"]+)"/', $the, $id) && preg_match('/Target="([^"]+)"/', $the, $dich)) {
                    $this->quanHe[$id[1]] = 'word/'.ltrim(str_replace('../', '', $dich[1]), '/');
                }
            }
        }

        $dom = new DOMDocument;
        $dom->loadXML((string) $this->zip->getFromName('word/document.xml'), LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        $this->xp = new DOMXPath($dom);
        foreach ([
            'w' => self::W,
            'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main',
            'r' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'v' => 'urn:schemas-microsoft-com:vml',
            'mc' => 'http://schemas.openxmlformats.org/markup-compatibility/2006',
        ] as $k => $ns) {
            $this->xp->registerNamespace($k, $ns);
        }

        $than = $this->xp->query('/w:document/w:body')->item(0);
        if ($than) {
            $this->khoi($than);
        }
        $this->zip->close();

        return $this->muc;
    }

    /** Duyệt các khối con: đoạn văn, bảng, khối nội dung (sdt). */
    private function khoi(DOMNode $cha): void
    {
        foreach ($cha->childNodes as $n) {
            if (! $n instanceof DOMElement) {
                continue;
            }
            if ($n->localName === 'p') {
                $this->doan($n);
            } elseif ($n->localName === 'tbl') {
                $this->bang($n);
            } elseif (in_array($n->localName, ['sdt', 'customXml'], true)) {
                $this->khoi($this->xp->query('w:sdtContent', $n)->item(0) ?? $n);
            }
        }
    }

    private function bang(DOMElement $bang): void
    {
        $soOTruoc = 0; // số ô của hàng vừa đọc dạng "hàng bảng" (0 = chưa có)
        foreach ($this->xp->query('w:tr', $bang) as $hang) {
            $o = [];
            $doanTrongO = [];
            foreach ($this->xp->query('w:tc', $hang) as $tc) {
                // Đoạn trực tiếp của ô (bảng lồng trong ô rất hiếm, bỏ qua)
                $doan = iterator_to_array($this->xp->query('w:p|w:sdt/w:sdtContent/w:p', $tc));
                $doanTrongO[] = $doan;
                // Ô chỉ có một gạch đầu dòng trống (cách các đối tác đánh dấu
                // "có bữa ăn" dưới cột Sáng / Trưa / Tối) → ghi thành "•"
                $o[] = trim(implode("\n", array_filter(array_map(
                    fn ($p) => trim($this->chuCua($p)[0]) === '' && $this->xp->query('w:pPr/w:numPr', $p)->length ? '•' : $this->chuCua($p)[0],
                    $doan,
                ), fn ($t) => trim($t) !== '')));
            }

            $coChu = array_values(array_filter($o, fn ($t) => $t !== ''));
            // Hàng chỉ có MỘT ô có chữ (bảng dùng để trang trí / chia cột): đọc
            // như các đoạn văn thường để giữ gạch đầu dòng từng dòng. Trừ khi
            // nó nối tiếp một hàng nhiều ô cùng khuôn — VD hàng đánh dấu bữa
            // ăn ("MB" dưới cột Tối) ngay sau hàng "NGÀY 1 | Sáng | Trưa | Tối"
            if (count($coChu) <= 1 && ! ($soOTruoc > 1 && $soOTruoc === count($o))) {
                $soOTruoc = 0;
                foreach ($doanTrongO as $doan) {
                    foreach ($doan as $p) {
                        $this->doan($p);
                    }
                }

                continue;
            }

            $dam = true;
            foreach ($doanTrongO as $doan) {
                foreach ($doan as $p) {
                    [$t, $d] = $this->chuCua($p);
                    if (trim($t) !== '' && ! $d) {
                        $dam = false;
                    }
                    $this->anhTrong($p);
                }
            }
            $this->muc[] = ['loai' => 'chu', 'chu' => implode(' | ', $o), 'dam' => $dam, 'gach' => false, 'o' => $o];
            $soOTruoc = count($o);
        }
    }

    private function doan(DOMElement $p): void
    {
        [$chu, $dam] = $this->chuCua($p);
        $this->anhTrong($p);
        $gach = $this->xp->query('w:pPr/w:numPr', $p)->length > 0;
        foreach (preg_split('/\n/', $chu) as $dong) {
            if (trim($dong) !== '') {
                $this->muc[] = ['loai' => 'chu', 'chu' => $dong, 'dam' => $dam, 'gach' => $gach, 'o' => null];
            }
        }
    }

    /** @return array{0: string, 1: bool} chữ của đoạn + có in đậm (phần lớn chữ đậm) không */
    private function chuCua(DOMElement $p): array
    {
        $chu = '';
        $soDam = 0;
        $tong = 0;
        // Bỏ nhánh mc:Fallback: Word lưu hộp chữ hai lần (bản mới + bản dự phòng)
        foreach ($this->xp->query('.//w:r[not(ancestor::mc:Fallback)]', $p) as $r) {
            $dam = false;
            $b = $this->xp->query('w:rPr/w:b', $r)->item(0);
            if ($b instanceof DOMElement) {
                $val = $b->getAttributeNS(self::W, 'val');
                $dam = ! in_array($val, ['0', 'false', 'off'], true);
            }
            foreach ($r->childNodes as $c) {
                if (! $c instanceof DOMElement) {
                    continue;
                }
                $them = match ($c->localName) {
                    't' => $c->textContent,
                    'tab' => "\t",
                    'br', 'cr' => "\n",
                    // Ký hiệu phông Wingdings (dấu tích đánh bữa ăn…) — mã vùng riêng U+F0xx
                    'sym' => ctype_xdigit($ma = $c->getAttributeNS(self::W, 'char')) ? mb_chr(hexdec($ma)) : '',
                    default => '',
                };
                $chu .= $them;
                $do = mb_strlen(trim($them));
                $tong += $do;
                $soDam += $dam ? $do : 0;
            }
        }

        return [$chu, $tong > 0 && $soDam / $tong >= 0.6];
    }

    /** Ảnh trong đoạn (DrawingML lẫn VML kiểu cũ), theo thứ tự xuất hiện. */
    private function anhTrong(DOMElement $p): void
    {
        $ds = $this->xp->query('.//a:blip[not(ancestor::mc:Fallback)]/@r:embed | .//v:imagedata[not(ancestor::mc:Choice)]/@r:id', $p);
        foreach ($ds as $thuocTinh) {
            $duong = $this->quanHe[$thuocTinh->value] ?? null;
            $duLieu = $duong ? $this->zip->getFromName($duong) : false;
            if ($duLieu !== false && $duLieu !== '') {
                $this->muc[] = ['loai' => 'anh', 'du_lieu' => $duLieu, 'ten' => basename($duong)];
            }
        }
    }
}
