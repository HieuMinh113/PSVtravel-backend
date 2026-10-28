/**
 * Trang Chấm công — quét khuôn mặt kiểu Face ID: bấm Chấm công vào / ra, đưa
 * mặt vào khung, máy tự nhận ra và chấm, không có nút chụp.
 *
 *  1. Mở camera + lấy vị trí GPS cùng lúc.
 *  2. Liên tục dò khuôn mặt bằng face-api (chạy ngay trong trình duyệt, ảnh
 *     không gửi ra dịch vụ ngoài), nhắc "lại gần", "vào giữa khung", "giữ yên".
 *  3. Mặt đúng chỗ và đứng yên → tính 128 số đặc trưng, hỏi máy chủ có khớp
 *     không (soKhuonMat — chỉ gửi 128 số, không kèm ảnh: mạng yếu vẫn nhanh).
 *     Máy chủ so với khuôn mặt đã đăng ký (trình duyệt không biết khuôn mặt gốc).
 *  4. Khớp → gửi ảnh + vị trí để chấm (máy chủ so lại lần nữa). Chưa khớp → quét tiếp.
 *  5. Quét lâu không khớp → cho quét lại hoặc gửi ảnh để quản lý duyệt.
 *
 * Đăng ký lần đầu cũng quét: lấy 5 mẫu liền nhau rồi lấy trung bình, so khớp
 * về sau ổn định hơn một tấm chụp.
 *
 * Chống "đơ" trên điện thoại:
 *  - Bộ nhận diện (~8 MB) tải sẵn lúc mở trang, chạy thử một lượt cho card đồ
 *    hoạ dựng sẵn chương trình — bấm Chấm công là quét ngay, lượt đầu không khựng.
 *  - Mỗi lượt mở camera có số thứ tự: bấm Huỷ / bấm lại trong lúc camera đang mở
 *    thì luồng camera cũ được tắt (trước đây camera chạy ngầm, lần sau báo lỗi).
 *  - Chuyển ứng dụng / tắt màn hình / camera bị ngắt → dừng hẳn, mời quét lại.
 *  - Không có card đồ hoạ → chạy WebAssembly (nhanh hơn CPU nhiều lần); dò lỗi
 *    liên tục (WebGL hỏng) → chuyển chế độ; máy yếu → giãn nhịp dò.
 *  - Mọi lần gọi máy chủ có hạn chờ, vị trí GPS có hạn chờ — không treo vô hạn.
 *
 * Nạp bằng x-load của Filament: <div x-load x-load-src="…/cham-cong.js" x-data="chamCong({...})">
 */
const SO_MAU_DANG_KY = 5
const THOI_GIAN_QUET = 25000 // ms — hết giờ mà chưa khớp thì dừng, đỡ tốn pin
const NHIP = 180 // ms tối thiểu giữa hai lần dò
const CHO_MAY_CHU = 25000 // ms — quá thời gian này coi như mạng lỗi
const CHO_VI_TRI = 12000 // ms — chờ GPS tối đa sau khi đã nhận ra khuôn mặt

const khoangCach = (a, b) => Math.sqrt(a.reduce((t, v, i) => t + (v - b[i]) ** 2, 0))
const trungBinh = (ds) => ds[0].map((_, i) => ds.reduce((t, d) => t + d[i], 0) / ds.length)
const nghi = (ms) => new Promise((x) => setTimeout(x, ms))
const coHan = (p, ms) => Promise.race([p, new Promise((_, loi) => setTimeout(() => loi(new Error('het_gio')), ms))])

/** Lời báo theo loại lỗi camera (trước đây lỗi nào cũng báo "hãy cho phép camera") */
const loiCamera = (e) => {
    const ten = e?.name || ''
    if (/NotAllowed|Security|Permission/i.test(ten)) {
        return 'Bạn chưa cho phép dùng camera. iPhone: Cài đặt → Safari → Camera → Cho phép. Android: bấm biểu tượng ổ khoá cạnh địa chỉ trang → Quyền → Camera → Cho phép. Rồi tải lại trang.'
    }
    if (/NotFound|Overconstrained/i.test(ten)) return 'Không tìm thấy camera trên máy này.'
    if (/NotReadable|TrackStart|Abort/i.test(ten)) {
        return 'Camera đang bị ứng dụng khác dùng (Zalo, gọi video, máy ảnh…). Tắt ứng dụng đó rồi bấm lại.'
    }

    return 'Không mở được camera' + (e?.message ? ': ' + e.message : '') + '. Tải lại trang rồi thử lại.'
}

/** Chờ video có khung hình thật (iPhone: play() xong mà videoWidth vẫn 0 một lúc) */
const choKhungHinh = (v, ms = 6000) =>
    new Promise((xong) => {
        if (v.readyState >= 2 && v.videoWidth) return xong(true)
        const bo = () => {
            clearTimeout(het)
            v.removeEventListener('loadeddata', thu)
            v.removeEventListener('playing', thu)
        }
        const thu = () => {
            if (v.videoWidth) {
                bo()
                xong(true)
            }
        }
        const het = setTimeout(() => {
            bo()
            xong(!!v.videoWidth)
        }, ms)
        v.addEventListener('loadeddata', thu)
        v.addEventListener('playing', thu)
    })

export default function chamCong({ thuVien, moHinh, wasm }) {
    let faceapi = null
    let taiPhatHien = null // thư viện + bộ dò mặt (nhẹ) — đủ để nhắc lại gần / vào giữa
    let taiNhanDien = null // + bộ điểm mốc + bộ đặc trưng (~7 MB) — cần để xác minh
    let daCoNhanDien = false
    let coDo = 320 // kích thước ảnh đưa vào bộ dò; máy yếu hạ xuống 224

    const napScript = () =>
        new Promise((xong, loi) => {
            if (window.faceapi) return xong()
            const s = document.createElement('script')
            s.src = thuVien
            s.onload = () => xong()
            s.onerror = () => {
                s.remove()
                loi(new Error('Không tải được bộ nhận diện khuôn mặt — kiểm tra mạng rồi bấm Quét lại.'))
            }
            document.head.appendChild(s)
        })

    const napPhatHien = () => {
        taiPhatHien ??= (async () => {
            await napScript()
            faceapi = window.faceapi
            // Card đồ hoạ (WebGL) nhanh nhất; không có thì WebAssembly (nhanh gấp nhiều lần
            // CPU thuần — trước đây máy không có WebGL chạy CPU, mỗi lần dò đứng màn hình ~1 giây)
            if (wasm) faceapi.tf.setWasmPaths?.(wasm)
            await chonCheDo(['webgl', 'wasm', 'cpu'])
            await faceapi.nets.tinyFaceDetector.loadFromUri(moHinh)
        })()
        taiPhatHien.catch(() => {
            taiPhatHien = null
        })

        return taiPhatHien
    }

    const napDayDu = () => {
        taiNhanDien ??= (async () => {
            await napPhatHien()
            await Promise.all([faceapi.nets.faceLandmark68Net.loadFromUri(moHinh), faceapi.nets.faceRecognitionNet.loadFromUri(moHinh)])
            // Chạy thử một lượt trên ảnh trống: card đồ hoạ dựng sẵn chương trình
            // ngay lúc này — lượt quét thật đầu tiên không bị khựng 1–3 giây
            try {
                const c = document.createElement('canvas')
                c.width = c.height = 160
                await faceapi.detectSingleFace(c, tuyChon())
                await faceapi.nets.faceLandmark68Net.detectLandmarks(c)
                await faceapi.nets.faceRecognitionNet.computeFaceDescriptor(c)
            } catch (e) {}
            daCoNhanDien = true
        })()
        taiNhanDien.catch(() => {
            taiNhanDien = null
        })

        return taiNhanDien
    }

    /** Bật chế độ chạy đầu tiên dùng được trong danh sách */
    const chonCheDo = async (ds) => {
        for (const ten of ds) {
            try {
                if (await faceapi.tf.setBackend(ten)) {
                    await faceapi.tf.ready()
                    if (ten === 'cpu') coDo = 224

                    return ten
                }
            } catch (e) {}
        }

        return null
    }

    /** Dò lỗi liên tục (WebGL hỏng / bị trình duyệt thu hồi) → chuyển sang chế độ kế tiếp */
    const doiCheDo = async () => {
        const thu = { webgl: ['wasm', 'cpu'], wasm: ['cpu'] }[faceapi.tf.getBackend()] || []

        return !!(thu.length && (await chonCheDo(thu)))
    }

    const tuyChon = () => new faceapi.TinyFaceDetectorOptions({ inputSize: coDo, scoreThreshold: 0.5 })

    const chupKhung = (video) => {
        if (!video?.videoWidth) return null
        const rong = Math.min(640, video.videoWidth)
        const canvas = document.createElement('canvas')
        canvas.width = rong
        canvas.height = Math.round(video.videoHeight * (rong / video.videoWidth))
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height)

        return canvas
    }

    /** Ảnh gửi lên lưu (quản lý xem lại): 480 px là đủ rõ mặt, nhẹ hơn ~2 lần */
    const anhGui = (canvas) => {
        const rong = Math.min(480, canvas.width)
        if (rong === canvas.width) return canvas.toDataURL('image/jpeg', 0.8)
        const c = document.createElement('canvas')
        c.width = rong
        c.height = Math.round(canvas.height * (rong / canvas.width))
        c.getContext('2d').drawImage(canvas, 0, 0, c.width, c.height)

        return c.toDataURL('image/jpeg', 0.8)
    }

    const moCamera = async () => {
        try {
            return await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            })
        } catch (e) {
            // Máy không đáp ứng được độ phân giải / camera trước → lấy camera bất kỳ
            if (!/Overconstrained|NotFound|TypeError/i.test(e?.name || '')) throw e

            return navigator.mediaDevices.getUserMedia({ video: true, audio: false })
        }
    }

    return {
        buoc: 'cho', // cho | quet | khong_nhan | ly_do | xong
        pha: '', // trong lúc quét: mo | tim | chinh | giu | xac_minh | truot | khop
        loai: null, // vao | ra | dang_ky
        dongY: false,
        goiY: '',
        tienDo: 0,
        loi: '',
        thongBao: '',
        canhBao: [],
        viSao: [],
        lyDo: '',
        dangGui: false,
        dangMo: false,
        daKhop: false,
        khongMoHinh: false,
        tieuDeKhongNhan: '',
        moTaKhongNhan: '',
        stream: null,
        viTri: null,
        hoiViTri: null,
        anh: null,
        dacTrung: null,
        _luot: 0,
        _chiKhiKhop: true,
        _vong: null,
        _batDauLuc: 0,
        _onDinh: 0,
        _truoc: null,
        _truot: 0,
        _choDen: 0,
        _mau: [],

        get nhanLoai() {
            return { vao: 'Chấm công vào', ra: 'Chấm công ra', dang_ky: 'Đăng ký khuôn mặt' }[this.loai] || ''
        },

        init() {
            // Tải sẵn bộ nhận diện lúc máy rảnh (trừ khi bật chế độ tiết kiệm dữ liệu)
            if (!navigator.connection?.saveData) {
                const khiRanh = window.requestIdleCallback || ((f) => setTimeout(f, 1500))
                khiRanh(() => napDayDu().catch(() => {}), { timeout: 4000 })
            }
            // Chuyển sang ứng dụng khác / tắt màn hình: điện thoại ngắt camera, khung
            // hình đứng im — dừng hẳn, quay lại thì mời quét lại
            this._khiAn = () => {
                if (document.hidden && this.buoc === 'quet') this.tamDung('Đã tạm dừng vì bạn chuyển ứng dụng hoặc tắt màn hình.')
            }
            document.addEventListener('visibilitychange', this._khiAn)
        },

        destroy() {
            document.removeEventListener('visibilitychange', this._khiAn)
            this._luot++
            this.tatCamera()
        },

        async batDau(loai) {
            if (this.dangMo) return // bấm hai lần liền
            this.datLai()
            const luot = ++this._luot
            this.loai = loai
            if (loai === 'dang_ky' && !this.dongY) {
                this.loi = 'Bạn cần tick đồng ý cho công ty dùng ảnh khuôn mặt để chấm công.'
                return
            }
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.loi = 'Trình duyệt chỉ cho mở camera trên trang https. Mở trang quản trị bằng địa chỉ https (Safari / Chrome bản mới).'
                return
            }
            this.buoc = 'quet'
            this.pha = 'mo'
            this.goiY = 'Đang mở camera…'
            this.dangMo = true
            this.$nextTick(() => this.$refs.video?.closest('.psv-quet')?.scrollIntoView({ block: 'center', behavior: 'smooth' }))
            if (loai !== 'dang_ky') this.layViTri()
            napDayDu().catch(() => {}) // tải song song với lúc mở camera (nếu chưa tải sẵn)

            let stream
            try {
                stream = await moCamera()
            } catch (e) {
                if (luot === this._luot) {
                    this.buoc = 'cho'
                    this.loi = loiCamera(e)
                }
                return
            } finally {
                this.dangMo = false
            }
            // Bấm Huỷ / bấm lại trong lúc camera đang mở → tắt luồng này, không để camera chạy ngầm
            if (luot !== this._luot || this.buoc !== 'quet') {
                stream.getTracks().forEach((t) => t.stop())
                return
            }
            this.stream = stream
            stream.getVideoTracks()[0]?.addEventListener('ended', () => {
                if (this.stream === stream) this.tamDung('Camera bị ngắt — có thể ứng dụng khác vừa dùng camera.')
            })
            const video = this.$refs.video
            video.srcObject = stream
            await video.play().catch(() => {})
            if (!(await choKhungHinh(video))) {
                if (luot === this._luot) this.dungQuet('Camera không có hình.', 'Đóng ứng dụng khác đang dùng camera, rồi bấm Quét lại.')
                return
            }
            if (luot !== this._luot || this.buoc !== 'quet') return
            if (!faceapi) this.goiY = 'Đang tải bộ nhận diện khuôn mặt (lần đầu hơi lâu)…'
            try {
                await napPhatHien()
            } catch (e) {
                if (luot !== this._luot) return
                this.khongMoHinh = true
                this.dungQuet(e.message)
                return
            }
            if (luot !== this._luot || this.buoc !== 'quet') return
            this.pha = 'tim'
            this.goiY = 'Đưa khuôn mặt vào trong khung'
            this._batDauLuc = Date.now()
            this.quet(luot)
        },

        /** Vòng dò liên tục: chỉ dò vị trí khuôn mặt (nhẹ); đủ điều kiện mới xác minh. */
        quet(luot) {
            const video = this.$refs.video
            let loiLienTiep = 0
            const conChay = () => luot === this._luot && this.buoc === 'quet' && this.stream
            const lap = async () => {
                if (!conChay()) return
                const dau = performance.now()
                if (!['xac_minh', 'khop'].includes(this.pha)) {
                    // Hết giờ tính từ lúc bộ nhận diện sẵn sàng (đang tải thì chưa tính)
                    if (!daCoNhanDien) this._batDauLuc = Date.now()
                    if (Date.now() - this._batDauLuc > THOI_GIAN_QUET * (this.loai === 'dang_ky' ? 2 : 1)) {
                        this.dungQuet()
                        return
                    }
                    let kq = null
                    try {
                        kq = await faceapi.detectSingleFace(video, tuyChon())
                        loiLienTiep = 0
                    } catch (e) {
                        loiLienTiep++
                        if (loiLienTiep >= 3 && (await doiCheDo())) {
                            loiLienTiep = 0
                        } else if (loiLienTiep >= 8) {
                            this.khongMoHinh = true
                            this.dungQuet('Máy này chưa chạy được bộ nhận diện khuôn mặt.', 'Thử lại bằng Chrome hoặc Safari bản mới. Cần chấm gấp thì bấm "Gửi ảnh cho quản lý duyệt".')
                            return
                        }
                    }
                    if (!conChay()) return
                    const tot = this.danhGia(kq)
                    this._onDinh = tot ? this._onDinh + 1 : 0
                    if (this._onDinh >= 2 && Date.now() >= this._choDen) await this.xacMinh(luot)
                }
                // Máy yếu dò mất nhiều thời gian → nghỉ lâu hơn cho màn hình kịp vẽ, không bị đơ
                const ton = performance.now() - dau
                if (ton > 400 && coDo > 224) coDo = 224
                if (conChay()) this._vong = setTimeout(lap, Math.max(NHIP, Math.min(600, ton)))
            }
            lap()
        },

        /** Mặt đã đúng chỗ, đủ to, đứng yên chưa? Đồng thời đặt lời nhắc. */
        danhGia(kq) {
            if (this.pha === 'truot' && Date.now() < this._choDen) return false
            if (!kq) {
                this.pha = 'tim'
                this.goiY = this._truot ? 'Chưa khớp — nhìn thẳng vào camera, giữ yên' : 'Đưa khuôn mặt vào trong khung'
                this._truoc = null
                return false
            }
            // Video hiển thị kiểu "cover" (bị cắt hai bên) → quy về phần đang nhìn thấy
            const v = this.$refs.video
            if (!v.videoWidth || !v.videoHeight) return false
            const r = v.getBoundingClientRect()
            const s = r.width ? Math.max(r.width / v.videoWidth, r.height / v.videoHeight) : 1
            const thayW = r.width ? r.width / s : v.videoWidth
            const thayH = r.height ? r.height / s : v.videoHeight
            const b = kq.box
            const x = (b.x + b.width / 2 - (v.videoWidth - thayW) / 2) / thayW
            const y = (b.y + b.height / 2 - (v.videoHeight - thayH) / 2) / thayH
            const rong = b.width / thayW
            const truoc = this._truoc
            this._truoc = { x, y }

            let nhac = null
            if (rong < 0.28) nhac = 'Lại gần camera hơn'
            else if (rong > 0.85) nhac = 'Lùi ra xa một chút'
            else if (Math.abs(x - 0.5) > 0.16 || Math.abs(y - 0.48) > 0.2) nhac = 'Đưa mặt vào giữa khung'
            else if (truoc && Math.hypot(x - truoc.x, y - truoc.y) > 0.035) nhac = 'Giữ yên…'
            if (nhac) {
                this.pha = 'chinh'
                this.goiY = nhac
                return false
            }
            this.pha = 'giu'
            this.goiY = this.loai === 'dang_ky' ? 'Giữ yên, đang lấy mẫu…' : 'Giữ yên…'
            return true
        },

        /** Tính đặc trưng khuôn mặt từ khung hình hiện tại rồi hỏi máy chủ có khớp không. */
        async xacMinh(luot) {
            const canvas = chupKhung(this.$refs.video)
            if (!canvas) return
            const conChay = () => luot === this._luot && this.buoc === 'quet'
            this.pha = 'xac_minh'
            if (this.loai !== 'dang_ky') this.goiY = 'Đang xác minh…'
            if (!daCoNhanDien) {
                // Bộ đặc trưng (~7 MB) có thể vẫn đang tải trên mạng chậm — chờ, camera vẫn chạy
                this.goiY = 'Đang tải bộ nhận diện… giữ mặt trong khung'
                try {
                    await napDayDu()
                } catch (e) {
                    if (!conChay()) return
                    this.khongMoHinh = true
                    this.dungQuet(e.message)
                    return
                }
                if (!conChay()) return
                this._batDauLuc = Date.now()
            }
            let ds = null
            try {
                const kq = await faceapi.detectSingleFace(canvas, tuyChon()).withFaceLandmarks().withFaceDescriptor()
                ds = kq ? Array.from(kq.descriptor) : null
            } catch (e) {}
            if (!conChay()) return
            if (!ds) {
                this.pha = 'tim'
                this._onDinh = 0
                return
            }
            const anh = anhGui(canvas)

            if (this.loai === 'dang_ky') {
                this._mau.push({ ds, anh })
                this.tienDo = this._mau.length / SO_MAU_DANG_KY
                this.goiY = `Đang lấy mẫu ${this._mau.length}/${SO_MAU_DANG_KY} — giữ yên`
                this.pha = 'giu'
                this._onDinh = 0
                if (this._mau.length < SO_MAU_DANG_KY) return
                const tb = trungBinh(this._mau.map((m) => m.ds))
                if (this._mau.some((m) => khoangCach(m.ds, tb) > 0.45)) {
                    // Các mẫu lệch nhau (có người khác lọt vào khung, quay ngang…) → lấy lại
                    this._mau = []
                    this.tienDo = 0
                    this.pha = 'truot'
                    this.goiY = 'Mẫu chưa đồng đều — nhìn thẳng, giữ yên, lấy lại từ đầu'
                    this._choDen = Date.now() + 900
                    return
                }
                const tot = this._mau.reduce((a, m) => (khoangCach(m.ds, tb) < khoangCach(a.ds, tb) ? m : a))
                this.dacTrung = tb
                this.anh = tot.anh
                await this.thanhCong()
                await this.gui()
                return
            }

            // Hỏi máy chủ khớp chưa: chỉ gửi 128 số, không kèm ảnh
            let so
            try {
                so = await coHan(this.$wire.soKhuonMat(ds), CHO_MAY_CHU)
            } catch (e) {
                if (conChay()) this.loiMang()
                return
            }
            if (!conChay()) return
            if (so?.qua_nhieu || (!so?.khop && so?.loi)) {
                this.ketQua({ ok: false, qua_nhieu: so.qua_nhieu, loi: so.loi })
                return
            }
            if (!so?.khop) {
                this._truot++
                this.pha = 'truot'
                this._onDinh = 0
                this.goiY = 'Chưa khớp với khuôn mặt đã đăng ký — nhìn thẳng, giữ yên…'
                this._choDen = Date.now() + 700
                return
            }

            // Khớp: báo ngay "đã nhận ra", rồi mới gửi ảnh + vị trí để chấm
            this.anh = anh
            this.dacTrung = ds
            this._chiKhiKhop = true
            this.daKhop = true
            await this.thanhCong()
            if (!conChay()) return
            this.goiY = this.viTri ? 'Đang lưu chấm công…' : 'Đã nhận ra bạn — đang lấy vị trí…'
            await this.gui()
        },

        /** Hiệu ứng "đã nhận ra": vòng xanh + dấu tích + tiếng + rung nhẹ. */
        async thanhCong() {
            this.pha = 'khop'
            this.goiY = this.loai === 'dang_ky' ? 'Đã lấy đủ mẫu' : 'Đã nhận ra bạn'
            this.tienDo = 1
            try {
                window.psvTing?.()
                navigator.vibrate?.(80)
            } catch (e) {}
            await nghi(650)
        },

        /** Hết giờ / không tải được bộ nhận diện / bị ngắt: dừng camera, giữ lại một ảnh để gửi quản lý nếu cần. */
        dungQuet(loi = '', moTa = '', { chupAnh = true } = {}) {
            if (chupAnh) {
                try {
                    const c = chupKhung(this.$refs.video)
                    this.anh ??= c ? anhGui(c) : null
                } catch (e) {}
            }
            this._luot++
            this.tatCamera()
            this.buoc = 'khong_nhan'
            this.moTaKhongNhan = moTa
            this.tieuDeKhongNhan =
                loi ||
                (this._truot
                    ? 'Khuôn mặt chưa khớp với ảnh đã đăng ký.'
                    : this.loai === 'dang_ky'
                      ? 'Chưa lấy được mẫu khuôn mặt.'
                      : 'Chưa thấy rõ khuôn mặt.')
        },

        /** Rời trang giữa chừng (chuyển ứng dụng, tắt màn hình, camera bị ngắt) */
        tamDung(lyDo) {
            if (this.buoc !== 'quet') return
            this.dungQuet(lyDo, 'Bấm "Quét lại" để mở lại camera.', { chupAnh: false })
        },

        /** Mạng chậm / mất mạng: dừng hẳn thay vì treo ở "Đang xác minh…" */
        loiMang() {
            this._luot++
            this.tatCamera()
            this.buoc = 'cho'
            this.loi = 'Mạng chậm hoặc mất mạng — chưa chấm được. Kiểm tra wifi / 4G rồi bấm lại.'
        },

        layViTri() {
            this.viTri = null
            const hoi = (chinhXac, han, cu) =>
                new Promise((xong, loi) =>
                    navigator.geolocation.getCurrentPosition(xong, loi, { enableHighAccuracy: chinhXac, timeout: han, maximumAge: cu }),
                )
            const ghi = (vt) => {
                this.viTri = { lat: vt.coords.latitude, lng: vt.coords.longitude, accuracy: vt.coords.accuracy }
            }
            this.hoiViTri = (async () => {
                if (!navigator.geolocation) {
                    this.viTri = { loi: 'Trình duyệt không hỗ trợ định vị' }
                    return
                }
                try {
                    ghi(await hoi(true, 9000, 30000))
                } catch (e) {
                    if (e?.code === 1) {
                        this.viTri = { loi: 'Bạn chưa cho phép lấy vị trí' }
                        return
                    }
                    // GPS chưa bắt được (trong nhà) → lấy vị trí theo wifi / sóng điện thoại
                    try {
                        ghi(await hoi(false, 5000, 300000))
                    } catch (e2) {
                        this.viTri = { loi: e2?.code === 1 ? 'Bạn chưa cho phép lấy vị trí' : 'Không lấy được vị trí' }
                    }
                }
            })()
        },

        /** Gửi máy chủ để chấm / đăng ký. */
        async gui() {
            this.dangGui = this.buoc !== 'quet'
            this.loi = ''
            try {
                if (this.loai === 'dang_ky') {
                    this.ketQua(await coHan(this.$wire.dangKyKhuonMat(this.dacTrung, this.anh, this.dongY), CHO_MAY_CHU))
                    return null
                }
                await Promise.race([this.hoiViTri, nghi(CHO_VI_TRI)])
                const kq = await coHan(
                    this.$wire.chamCong(this.loai, {
                        lat: this.viTri?.lat ?? null,
                        lng: this.viTri?.lng ?? null,
                        accuracy: this.viTri?.accuracy ?? null,
                        anh: this.anh,
                        descriptor: this.dacTrung,
                        ly_do: this.lyDo || null,
                        chi_khi_khop: this._chiKhiKhop,
                    }),
                    CHO_MAY_CHU,
                )
                if (kq?.khong_khop) {
                    // Hiếm: vừa khớp ở bước so mà lần chấm lại không khớp → quét tiếp
                    if (this.buoc === 'quet') {
                        this.daKhop = false
                        this.pha = 'truot'
                        this._onDinh = 0
                        this._choDen = Date.now() + 700
                        this.goiY = 'Chưa khớp — nhìn thẳng, giữ yên…'
                    } else {
                        this.ketQua({ ok: false, loi: 'Khuôn mặt chưa khớp, quét lại.' })
                    }
                    return kq
                }
                this.ketQua(kq)
                return kq
            } catch (e) {
                this.loiMang()
                return null
            } finally {
                this.dangGui = false
            }
        },

        ketQua(kq) {
            this._luot++
            this.tatCamera()
            if (kq?.can_ly_do) {
                this.buoc = 'ly_do'
                this.viSao = kq.vi_sao || []
                return
            }
            if (!kq?.ok) {
                // Quét không khớp quá nhiều lần → vẫn cho gửi ảnh cho quản lý
                this.buoc = kq?.qua_nhieu ? 'khong_nhan' : 'cho'
                if (kq?.qua_nhieu) {
                    this.tieuDeKhongNhan = kq.loi
                    this.moTaKhongNhan = ''
                } else this.loi = kq?.loi || 'Có lỗi, thử lại.'
                return
            }
            this.buoc = 'xong'
            this.thongBao = kq.thong_bao
            this.canhBao = kq.canh_bao || []
        },

        /** Quét mãi không khớp: chấm bằng ảnh vừa chụp, quản lý xem lại (đánh dấu nghi vấn). */
        async guiChoQuanLy() {
            if (!this.anh || this.dangGui) return
            this._chiKhiKhop = false
            this.daKhop = false
            if (!this.hoiViTri) this.layViTri()
            await this.gui()
        },

        async guiLyDo() {
            if (this.dangGui) return
            if ((this.lyDo || '').trim().length < 3) {
                this.loi = 'Ghi lý do (ít nhất vài chữ).'
                return
            }
            await this.gui()
        },

        tatCamera() {
            clearTimeout(this._vong)
            this.stream?.getTracks().forEach((t) => t.stop())
            this.stream = null
            const v = this.$refs.video
            if (v) v.srcObject = null
        },

        datLai() {
            this._luot++
            this.tatCamera()
            this.buoc = 'cho'
            this.pha = ''
            this.loi = ''
            this.goiY = ''
            this.thongBao = ''
            this.canhBao = []
            this.viSao = []
            this.lyDo = ''
            this.anh = null
            this.dacTrung = null
            this.daKhop = false
            this.khongMoHinh = false
            this.moTaKhongNhan = ''
            this.tienDo = 0
            this._chiKhiKhop = true
            this._onDinh = 0
            this._truoc = null
            this._truot = 0
            this._choDen = 0
            this._mau = []
        },
    }
}
