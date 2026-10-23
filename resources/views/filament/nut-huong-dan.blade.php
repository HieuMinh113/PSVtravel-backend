{{-- Nút "Hướng dẫn" trên thanh trên cùng (App\Filament\Pages\HuongDan) --}}
<a href="{{ \App\Filament\Pages\HuongDan::getUrl() }}"
   class="fi-icon-btn fi-size-md"
   title="Hướng dẫn sử dụng"
   aria-label="Hướng dẫn sử dụng"
   style="display:inline-flex;align-items:center;gap:.35rem;height:2.25rem;padding:0 .6rem;border-radius:.5rem;font-size:.875rem;font-weight:500;white-space:nowrap;color:rgb(100 116 139)">
    {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedQuestionMarkCircle) }}
    <span class="psv-nut-huong-dan-chu">Hướng dẫn</span>
</a>
