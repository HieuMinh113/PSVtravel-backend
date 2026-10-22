<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BaoIndexNowTest extends TestCase
{
    public function test_gui_moi_trang_trong_sitemap_cho_indexnow(): void
    {
        config(['app.frontend_url' => 'https://psvtravel.com', 'indexnow.enabled' => true, 'indexnow.key' => 'abc123']);
        Http::fake([
            'frontend:3000/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset>'
                .'<url><loc>https://psvtravel.com/</loc></url>'
                .'<url><loc>https://psvtravel.com/tour-tet</loc></url>'
                .'<url><loc>https://psvtravel.com/cam-nang/a?x=1&amp;y=2</loc></url>'
                .'<url><loc>https://web-khac.com/z</loc></url></urlset>'),
            'api.indexnow.org/*' => Http::response('', 202),
        ]);

        $this->artisan('psv:bao-indexnow --xem')->expectsOutputToContain('Sitemap có 3 trang')->assertSuccessful();
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'indexnow')); // --xem chỉ đếm

        $this->artisan('psv:bao-indexnow')->expectsOutputToContain('đã nhận (202)')->assertSuccessful();
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.indexnow.org/indexnow'
            && $r['host'] === 'psvtravel.com'
            && $r['urlList'] === ['https://psvtravel.com/', 'https://psvtravel.com/tour-tet', 'https://psvtravel.com/cam-nang/a?x=1&y=2']
            && $r['keyLocation'] === 'https://psvtravel.com/abc123.txt');
    }
}
