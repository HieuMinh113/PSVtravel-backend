{{-- Trang "Kết nối AI (MCP)" (App\Filament\Pages\KetNoiAi) --}}
@php
    $ma = $maMoi ?? '<MÃ KHOÁ>';
    // Chuỗi JS cho nút Chép: @js() không chạy trong thuộc tính của thẻ <x-…>, phải tính trước
    $maJs = \Illuminate\Support\Js::from((string) $maMoi);
    $lenhClaudeCode = 'claude mcp add --transport http psv-noi-bo '.$diaChi.' --header "Authorization: Bearer '.$ma.'"';
    $cauHinhDesktop = json_encode(['mcpServers' => ['psv-noi-bo' => [
        'command' => 'npx',
        'args' => ['-y', 'mcp-remote', $diaChi, '--header', 'Authorization:${PSV_MCP}'],
        'env' => ['PSV_MCP' => 'Bearer '.$ma],
    ]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

<div style="display:flex;flex-direction:column;gap:1rem;font-size:.9rem;line-height:1.6">
    @if ($maMoi)
        <div x-data="{ daChep: false }" style="padding:1rem;border-radius:12px;border:2px solid rgb(22 163 74);background:rgba(22,163,74,.06)">
            <strong>Mã khoá mới — chép và cất ngay, mã chỉ hiện một lần:</strong>
            <code style="display:block;margin:.5rem 0;padding:.6rem;border-radius:8px;background:rgba(0,0,0,.05);word-break:break-all">{{ $maMoi }}</code>
            <x-filament::button size="sm" icon="heroicon-o-clipboard-document"
                x-on:click="navigator.clipboard.writeText({{ $maJs }}); daChep = true; setTimeout(() => daChep = false, 2000)">
                <span x-show="!daChep">Chép mã</span><span x-show="daChep" x-cloak>Đã chép</span>
            </x-filament::button>
            <div style="margin-top:.5rem;opacity:.75">Không gửi mã qua Zalo / email / nhóm chat. Lộ mã thì bấm <strong>Thu hồi</strong> bên dưới.</div>
        </div>
    @endif

    <div style="padding:1rem;border-radius:12px;border:1px solid rgba(0,0,0,.1)">
        <p style="margin:0 0 .5rem">
            Trợ lý AI trên máy tính của bạn (Claude Code, Claude Desktop…) đọc tài liệu, ảnh trong thư mục bạn chỉ định rồi
            <strong>tạo bài cẩm nang nháp</strong> trên website. AI <strong>không đăng được</strong> — bạn vào
            <strong>Nội dung website → Cẩm nang</strong> xem lại, chọn ảnh bìa rồi bấm Đăng.
        </p>
        <p style="margin:0">Địa chỉ máy chủ: <code>{{ $diaChi }}</code> · Mã khoá hết hạn sau {{ $soNgay }} ngày · Chỉ tài khoản Admin có quyền viết cẩm nang dùng được.</p>
    </div>

    <details style="padding:1rem;border-radius:12px;border:1px solid rgba(0,0,0,.1)" @if ($maMoi) open @endif>
        <summary style="cursor:pointer;font-weight:600">Cài vào Claude Code (khuyên dùng: đọc được thư mục, tải được ảnh)</summary>
        <ol style="margin:.75rem 0 0 1.25rem;list-style:decimal">
            <li>Mở cửa sổ dòng lệnh, chạy (thay <code>&lt;MÃ KHOÁ&gt;</code> nếu chưa tạo mã ở trên):</li>
        </ol>
        <code style="display:block;margin:.5rem 0;padding:.6rem;border-radius:8px;background:rgba(0,0,0,.05);word-break:break-all">{{ $lenhClaudeCode }}</code>
        <ol start="2" style="margin:0 0 0 1.25rem;list-style:decimal">
            <li>Vào thư mục chứa tài liệu bài viết, gõ <code>claude</code>, rồi nhờ: <em>"Đọc file kinh-nghiem-da-lat.docx và ảnh trong thư mục anh/, soạn bài cẩm nang nháp, ảnh dalat-1.jpg làm ảnh bìa."</em></li>
        </ol>
    </details>

    <details style="padding:1rem;border-radius:12px;border:1px solid rgba(0,0,0,.1)">
        <summary style="cursor:pointer;font-weight:600">Cài vào Claude Desktop</summary>
        <p style="margin:.75rem 0 .25rem">Claude Desktop → Settings → Developer → Edit Config, thêm vào tệp cấu hình (cần cài Node.js), lưu rồi mở lại Claude Desktop:</p>
        <pre style="margin:0;padding:.6rem;border-radius:8px;background:rgba(0,0,0,.05);white-space:pre-wrap;word-break:break-all;font-size:.8rem">{{ $cauHinhDesktop }}</pre>
        <p style="margin:.5rem 0 0;opacity:.75">Claude Desktop soạn được bài từ nội dung bạn dán hoặc đính kèm; muốn AI tự mở thư mục và tải ảnh lên thì dùng Claude Code.</p>
    </details>
</div>
