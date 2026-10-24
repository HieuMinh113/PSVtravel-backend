<?php

namespace App\Models\Concerns;

use App\Jobs\ThuNhoAnhNoiDung;
use App\Services\SuaLinkNoiDung;
use App\Services\ThuNhoAnh;

/**
 * Nội dung soạn bằng RichEditor (bài cẩm nang, điểm đến, visa…), mỗi lần lưu:
 *  - link về chính website: đưa về đường dẫn tương đối, trỏ thẳng trang đích
 *    (App\Services\SuaLinkNoiDung) — khỏi đi vòng qua chuyển hướng;
 *  - ảnh chèn trong bài: thu nhỏ trong hàng đợi (App\Services\ThuNhoAnh).
 *
 * Model khai báo các ô nội dung qua truongNoiDung().
 */
trait ChuanHoaNoiDung
{
    protected static function bootChuanHoaNoiDung(): void
    {
        static::saving(function (self $m) {
            foreach ($m->truongNoiDung() as $truong) {
                if ($m->isDirty($truong) && filled($m->getAttribute($truong))) {
                    $m->setAttribute($truong, SuaLinkNoiDung::sua($m->getAttribute($truong))['html']);
                }
            }
        });

        static::saved(function (self $m) {
            $anh = [];
            foreach ($m->truongNoiDung() as $truong) {
                if ($m->wasRecentlyCreated || $m->wasChanged($truong)) {
                    $anh = [...$anh, ...ThuNhoAnh::anhTrongNoiDung($m->getAttribute($truong))];
                }
            }
            if ($anh) {
                ThuNhoAnhNoiDung::dispatch(array_values(array_unique($anh)))->afterCommit();
            }
        });
    }

    /** @return list<string> */
    abstract public function truongNoiDung(): array;
}
