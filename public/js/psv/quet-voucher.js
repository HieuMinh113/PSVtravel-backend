/**
 * Trang Quét voucher — đọc QR / mã vạch bằng camera (html5-qrcode, chạy ngay
 * trong trình duyệt), hoặc nhận mã từ máy quét cầm tay (máy gõ mã + Enter vào ô).
 *
 * Đọc được mã: tắt camera, kêu "bíp", gửi lên máy chủ kiểm tra. Kết quả (còn
 * dùng / đã dùng / hết hạn…) do Livewire vẽ. "Quét mã khác" mở lại camera nếu
 * lần trước quét bằng camera, không thì đưa con trỏ về ô nhập.
 *
 * Nạp bằng x-load của Filament: <div x-load x-load-src="…/quet-voucher.js" x-data="quetVoucher({...})">
 */
export default function quetVoucher({ thuVien }) {
    let napLib = null
    let may = null

    const nap = () => {
        napLib ??= new Promise((xong, loi) => {
            if (window.Html5Qrcode) return xong()
            const s = document.createElement('script')
            s.src = thuVien
            s.onload = () => xong()
            s.onerror = () => {
                napLib = null
                loi(new Error('Không tải được bộ quét mã — kiểm tra mạng rồi thử lại.'))
            }
            document.head.appendChild(s)
        })

        return napLib
    }

    const bip = (tot) => {
        try {
            const am = new (window.AudioContext || window.webkitAudioContext)()
            const o = am.createOscillator()
            const g = am.createGain()
            o.frequency.value = tot ? 1046 : 330
            g.gain.value = 0.12
            o.connect(g).connect(am.destination)
            o.start()
            o.stop(am.currentTime + (tot ? 0.12 : 0.35))
            o.onended = () => am.close()
        } catch (e) {}
        navigator.vibrate?.(tot ? 60 : [80, 60, 80])
    }

    return {
        camera: false,
        dangMo: false,
        dungCamera: false,
        dangDoc: false,
        loi: '',

        init() {
            this.$nextTick(() => this.$refs.o?.focus())
            // Kết quả về: kêu bíp theo kết quả, chọn sẵn chữ trong ô để lần quét sau ghi đè
            window.addEventListener('voucher-da-kiem-tra', this.onKetQua = (e) => {
                const kq = e.detail?.ketQua
                if (kq) bip(kq === 'con_dung')
                this.$nextTick(() => this.$refs.o?.select())
            })
        },

        destroy() {
            window.removeEventListener('voucher-da-kiem-tra', this.onKetQua)
            this.tatCamera()
        },

        async moCamera() {
            this.loi = ''
            this.dangMo = true
            try {
                await nap()
                may ??= new window.Html5Qrcode('psv-voucher-camera', {
                    formatsToSupport: [window.Html5QrcodeSupportedFormats.QR_CODE, window.Html5QrcodeSupportedFormats.CODE_128],
                    verbose: false,
                })
                this.camera = true
                this.dungCamera = true
                await this.$nextTick()
                await may.start(
                    { facingMode: 'environment' },
                    {
                        fps: 12,
                        // vùng đọc gần trọn khung hình — không phải căn mã vào giữa
                        qrbox: (w, h) => ({ width: Math.floor(w * 0.92), height: Math.floor(h * 0.9) }),
                    },
                    (chu) => this.docDuoc(chu),
                    () => {},
                )
            } catch (e) {
                this.camera = false
                const ten = e?.name || String(e)
                this.loi = /NotAllowed|Permission/i.test(ten)
                    ? 'Chưa cho phép dùng camera — bấm biểu tượng ổ khoá cạnh địa chỉ trang, cho phép Camera rồi thử lại.'
                    : /NotFound|Overconstrained/i.test(ten)
                      ? 'Không tìm thấy camera trên máy này — gõ mã hoặc dùng máy quét.'
                      : e?.message || 'Không mở được camera.'
            } finally {
                this.dangMo = false
            }
        },

        async tatCamera() {
            try {
                if (may?.isScanning) await may.stop()
            } catch (e) {}
            this.camera = false
        },

        async docDuoc(chu) {
            if (this.dangDoc) return
            this.dangDoc = true
            await this.tatCamera()
            try {
                await this.$wire.kiemTra(chu)
            } finally {
                this.dangDoc = false
            }
        },

        async quetTiep() {
            await this.$wire.quetMoi()
            if (this.dungCamera) return this.moCamera()
            this.$nextTick(() => this.$refs.o?.focus())
        },
    }
}
