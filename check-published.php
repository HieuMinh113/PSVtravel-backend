<?php

$all = \Modules\Tour\Models\Tour::count();
$pub = \Modules\Tour\Models\Tour::where('status', 'published')->count();

echo "Tong so tour: $all".PHP_EOL;
echo "Dang ban (published): $pub".PHP_EOL;
echo PHP_EOL."Danh sach:".PHP_EOL;

foreach (\Modules\Tour\Models\Tour::get(['slug', 'status']) as $t) {
    echo str_pad($t->slug, 30).' | '.$t->status.PHP_EOL;
}