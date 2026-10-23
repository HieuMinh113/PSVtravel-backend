{{-- Tinh chỉnh giao diện admin không cần dựng lại CSS của Filament (AdminPanelProvider) --}}
<style>
    /* Số tiền, số lượng: chữ số rộng đều nhau để dò cột cho dễ */
    .fi-ta-text, .fi-wi-stats-overview-stat-value, .fi-badge { font-variant-numeric: tabular-nums; }
    /* Tiêu đề trang: chữ đậm, sát nhẹ, không để rớt một chữ xuống dòng */
    .fi-header-heading { letter-spacing: -0.015em; text-wrap: balance; }
    /* Nhãn nhóm menu dễ tách khỏi tên mục */
    .fi-sidebar-group-label { font-weight: 600; letter-spacing: .01em; }
    /* Logo màu xanh trên nền tối: đặt lên miếng nền trắng bo góc */
    .dark .fi-logo { background: #fff; border-radius: .5rem; padding: .2rem .45rem; }
    /* Màn hình hẹp: nút Hướng dẫn chỉ còn biểu tượng */
    @media (max-width: 640px) { .psv-nut-huong-dan-chu { display: none; } }
    .dark a[aria-label="Hướng dẫn sử dụng"] { color: rgb(148 163 184) !important; }

    /* ---- Trang Tổng quan: Việc cần làm ---- */
    .psv-viec-luoi { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); }
    .psv-viec { display: flex; align-items: center; gap: .85rem; padding: .8rem 1rem; border-radius: .75rem;
        background: var(--gray-50); border: 1px solid var(--gray-200); transition: border-color .2s, background-color .2s, transform .15s; }
    .psv-viec:hover { border-color: var(--primary-400); background: var(--primary-50); }
    .psv-viec:active { transform: translateY(1px); }
    .psv-viec:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 2px; }
    .psv-viec-so { flex: none; min-width: 2.6rem; height: 2.6rem; padding: 0 .5rem; border-radius: .6rem; display: inline-flex;
        align-items: center; justify-content: center; font-weight: 700; font-size: 1.15rem; font-variant-numeric: tabular-nums; }
    .psv-viec-danger .psv-viec-so { background: var(--danger-100); color: var(--danger-700); }
    .psv-viec-warning .psv-viec-so { background: var(--warning-100); color: var(--warning-700); }
    .psv-viec-info .psv-viec-so, .psv-viec-success .psv-viec-so { background: var(--primary-100); color: var(--primary-700); }
    .psv-viec-chu { display: flex; flex-direction: column; min-width: 0; flex: 1; }
    .psv-viec-nhan { font-weight: 600; color: var(--gray-950); }
    .psv-viec-mo-ta { font-size: .8rem; color: var(--gray-500); line-height: 1.35; }
    .psv-viec-mui { width: 1rem; height: 1rem; color: var(--gray-400); flex: none; }
    .psv-viec-trong { display: flex; align-items: center; gap: .6rem; color: var(--gray-500); font-size: .9rem; }
    .psv-viec-trong svg { width: 1.4rem; height: 1.4rem; color: var(--success-600); }
    .dark .psv-viec { background: rgb(255 255 255 / .03); border-color: rgb(255 255 255 / .08); }
    .dark .psv-viec:hover { background: rgb(255 255 255 / .06); border-color: var(--primary-500); }
    .dark .psv-viec-nhan { color: var(--gray-100); }
    .dark .psv-viec-mo-ta { color: var(--gray-400); }
    .dark .psv-viec-danger .psv-viec-so { background: rgb(239 68 68 / .15); color: var(--danger-400); }
    .dark .psv-viec-warning .psv-viec-so { background: rgb(245 158 11 / .15); color: var(--warning-400); }
    .dark .psv-viec-info .psv-viec-so, .dark .psv-viec-success .psv-viec-so { background: rgb(1 105 169 / .25); color: var(--primary-300); }

    /* ---- Trang Tổng quan: Lối tắt ---- */
    .psv-loi-tat { display: flex; flex-wrap: wrap; gap: .5rem; }

    @media (prefers-reduced-motion: reduce) { .psv-viec { transition: none; } .psv-viec:active { transform: none; } }
</style>
