{{-- Trang "Hướng dẫn sử dụng" (App\Filament\Pages\HuongDan) — nội dung ở lớp trang --}}
@php
    // **chữ** → in đậm; còn lại thoát HTML như thường
    $dam = fn (string $t) => preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($t));
@endphp
<div x-data="{ tab: @js($macDinh), chon(t) { this.tab = t; try { history.replaceState(null, '', '?tab=' + t) } catch (e) {} } }" class="psv-hd">
    <nav class="psv-hd-tabs" role="tablist" aria-label="Bộ phận">
        @foreach ($tabs as $ma => $t)
            <button type="button" role="tab" class="psv-hd-tab"
                x-on:click="chon(@js($ma))"
                x-bind:aria-selected="tab === @js($ma)"
                x-bind:class="{ 'is-active': tab === @js($ma) }">
                {{ \Filament\Support\generate_icon_html($t['icon']) }}
                <span>{{ $t['ten'] }}</span>
            </button>
        @endforeach
    </nav>

    @foreach ($tabs as $ma => $t)
        <section role="tabpanel" x-show="tab === @js($ma)" @if ($ma !== $macDinh) x-cloak @endif class="psv-hd-panel">
            <p class="psv-hd-gioi-thieu">{{ $t['gioi_thieu'] }}</p>
            <div class="psv-hd-luoi">
                @foreach ($t['muc'] as $m)
                    <article class="psv-hd-muc">
                        <h3>{{ $m['tieu_de'] }}</h3>
                        @if (! empty($m['o_dau']))
                            <div class="psv-hd-o-dau">{{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedMapPin) }}<span>{{ $m['o_dau'] }}</span></div>
                        @endif
                        <ol>
                            @foreach ($m['buoc'] as $b)
                                <li>{!! $dam($b) !!}</li>
                            @endforeach
                        </ol>
                        @if (! empty($m['luu_y']))
                            <p class="psv-hd-luu-y">{{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedInformationCircle) }}<span>{{ $m['luu_y'] }}</span></p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
<style>
    .psv-hd-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1.25rem; }
    .psv-hd-tab { display: inline-flex; align-items: center; gap: .4rem; padding: .5rem .9rem; border-radius: .6rem; font-weight: 500;
        font-size: .9rem; color: var(--gray-600); background: var(--gray-50); border: 1px solid var(--gray-200); transition: background-color .2s, color .2s, border-color .2s; }
    .psv-hd-tab svg { width: 1.1rem; height: 1.1rem; }
    .psv-hd-tab:hover { border-color: var(--primary-300); color: var(--primary-700); }
    .psv-hd-tab:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 2px; }
    .psv-hd-tab.is-active { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
    .psv-hd-gioi-thieu { color: var(--gray-600); margin-bottom: 1rem; max-width: 65ch; }
    .psv-hd-luoi { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(22rem, 1fr)); align-items: start; }
    .psv-hd-muc { background: #fff; border: 1px solid var(--gray-200); border-radius: .85rem; padding: 1.1rem 1.25rem 1.2rem; }
    .psv-hd-muc h3 { font-weight: 600; font-size: 1.02rem; color: var(--gray-950); letter-spacing: -0.01em; text-wrap: balance; }
    .psv-hd-o-dau { display: inline-flex; align-items: flex-start; gap: .35rem; margin-top: .45rem; padding: .25rem .55rem; border-radius: .45rem;
        background: var(--primary-50); color: var(--primary-800); font-size: .8rem; font-weight: 500; line-height: 1.4; }
    .psv-hd-o-dau svg { width: .95rem; height: .95rem; flex: none; margin-top: .1rem; }
    .psv-hd-muc ol { margin-top: .75rem; padding-left: 0; list-style: none; counter-reset: buoc; display: grid; gap: .5rem; }
    .psv-hd-muc li { counter-increment: buoc; position: relative; padding-left: 2rem; font-size: .9rem; line-height: 1.55; color: var(--gray-700); }
    .psv-hd-muc li::before { content: counter(buoc); position: absolute; left: 0; top: .05rem; width: 1.4rem; height: 1.4rem; border-radius: .4rem;
        background: var(--gray-100); color: var(--gray-700); font-size: .75rem; font-weight: 600; display: grid; place-items: center; font-variant-numeric: tabular-nums; }
    .psv-hd-muc li strong { color: var(--gray-950); font-weight: 600; }
    .psv-hd-luu-y { display: flex; gap: .45rem; margin-top: .85rem; padding: .6rem .75rem; border-radius: .6rem; background: var(--warning-50);
        color: var(--warning-900); font-size: .82rem; line-height: 1.5; }
    .psv-hd-luu-y svg { width: 1.05rem; height: 1.05rem; flex: none; margin-top: .1rem; color: var(--warning-600); }
    .dark .psv-hd-tab { background: rgb(255 255 255 / .04); border-color: rgb(255 255 255 / .1); color: var(--gray-300); }
    .dark .psv-hd-tab.is-active { background: var(--primary-600); color: #fff; }
    .dark .psv-hd-muc { background: rgb(255 255 255 / .03); border-color: rgb(255 255 255 / .08); }
    .dark .psv-hd-muc h3, .dark .psv-hd-muc li strong { color: var(--gray-100); }
    .dark .psv-hd-muc li { color: var(--gray-300); }
    .dark .psv-hd-muc li::before { background: rgb(255 255 255 / .08); color: var(--gray-200); }
    .dark .psv-hd-o-dau { background: rgb(1 105 169 / .2); color: var(--primary-200); }
    .dark .psv-hd-luu-y { background: rgb(245 158 11 / .1); color: var(--warning-200); }
    .dark .psv-hd-gioi-thieu { color: var(--gray-400); }
    @media (max-width: 640px) { .psv-hd-luoi { grid-template-columns: 1fr; } }
</style>
