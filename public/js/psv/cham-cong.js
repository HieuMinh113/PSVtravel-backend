/**
 * Trang Chấm công — quét khuôn mặt kiểu Face ID: bấm Chấm công vào / ra, đưa
 * mặt vào khung, máy tự nhận ra và chấm, không có nút chụp.
 *
 *  1. Mở camera + lấy vị trí GPS cùng lúc.
 *  2. Liên tục dò khuôn mặt bằng face-api (chạy ngay trong trình duyệt, ảnh
 *     không gửi ra dịch vụ ngoài), nhắc "lại gần", "vào giữa khung", "giữ yên".
 *  3. Mặt đúng chỗ và đứng yên → tính 128 số đặc trưng, gửi lên máy chủ kèm
 *     chi_khi_khop. Máy chủ so với khuôn mặt đã đăng ký (trình duyệt không biết
 *     khuôn mặt gốc): khớp thì chấm luôn, chưa khớp thì quét tiếp.
 *  4. Quét lâu không khớp → cho quét lại hoặc gửi ảnh để quản lý duyệt.
 *
 * Đăng ký lần đầu cũng quét: lấy 5 mẫu liền nhau rồi lấy trung bình, so khớp
 * về sau ổn định hơn một tấm chụp.
 *
 * Nạp bằng x-load của Filament: <div x-load x-load-src="…/cham-cong.js" x-data="chamCong({...})">
 */
const SO_MAU_DANG_KY = 5
const THOI_GIAN_QUET = 25000 // ms — hết giờ mà chưa khớp thì dừng, đỡ tốn pin
const NHIP = 180 // ms giữa hai lần dò

const khoangCach = (a, b) => Math.sqrt(a.reduce((t, v, i) => t + (v - b[i]) ** 2, 0))
const trungBinh = (ds) => ds[0].map((_, i) => ds.reduce((t, d) => t + d[i], 0) / ds.length)

export default function chamCong({ thuVien, moHinh }) {
    let faceapi = null
    let taiMoHinh = null

    const napThuVien = () => {
        if (taiMoHinh) return taiMoHinh
        taiMoHinh = new Promise((xong, loi) => {
            if (window.faceapi) return xong(window.faceapi)
            const s = document.createElement('script')
            s.src = thuVien
            s.onload = () => xong(window.faceapi)
            s.onerror = () => loi(new Error('Không tải được bộ nhận diện khuôn mặt.'))
            document.head.appendChild(s)
        }).then(async (api) => {
            faceapi = api
            // WebGL (card đồ hoạ) nhanh nhất; máy không có thì chạy bằng CPU — chậm hơn nhưng vẫn được
            let coWebgl = false
            try {
                coWebgl = await faceapi.tf.setBackend('webgl')
            } catch (e) {}
            if (!coWebgl) await faceapi.tf.setBackend('cpu')
            await faceapi.tf.ready()
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(moHinh),
                faceapi.nets.faceLandmark68Net.loadFromUri(moHinh),
                faceapi.nets.faceRecognitionNet.loadFromUri(moHinh),
            ])
        })
        taiMoHinh.catch(() => {
            taiMoHinh = null
        })

        return taiMoHinh
    }

    const tuyChon = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 })

    const chupKhung = (video) => {
        if (!video?.videoWidth) return null
        const rong = Math.min(640, video.videoWidth)
        const canvas = document.createElement('canvas')
        canvas.width = rong
        canvas.height = Math.round(video.videoHeight * (rong / video.videoWidth))
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height)

        return canvas
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
        daKhop: false,
        khongMoHinh: false,
        tieuDeKhongNhan: '',
        stream: null,
        viTri: null,
        hoiViTri: null,
        anh: null,
        dacTrung: null,
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

        async batDau(loai) {
            this.datLai()
            this.loai = loai
            if (loai === 'dang_ky' && !this.dongY) {
                this.loi = 'Bạn cần tick đồng ý cho công ty dùng ảnh khuôn mặt để chấm công.'
                return
            }
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.loi = 'Trình duyệt chỉ cho mở camera trên trang https (hoặc localhost). Mở trang quản trị bằng địa chỉ https.'
                return
            }
            this.buoc = 'quet'
            this.pha = 'mo'
            this.goiY = 'Đang mở camera…'
            this.$nextTick(() => this.$refs.video?.closest('.psv-quet')?.scrollIntoView({ block: 'center', behavior: 'smooth' }))
            if (loai !== 'dang_ky') this.layViTri()
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false,
                })
            } catch (e) {
                this.buoc = 'cho'
                this.loi = 'Không mở được camera: hãy cho phép trình duyệt dùng camera (biểu tượng ổ khoá cạnh địa chỉ trang) rồi thử lại.'
                return
            }
            const video = this.$refs.video
            video.srcObject = this.stream
            await video.play().catch(() => {})
            this.goiY = 'Đang tải bộ nhận diện khuôn mặt (lần đầu hơi lâu)…'
            try {
                await napThuVien()
            } catch (e) {
                this.khongMoHinh = true
                this.dungQuet(e.message)
                return
            }
            if (this.buoc !== 'quet') return // đã bấm Huỷ trong lúc tải
            this.pha = 'tim'
            this.goiY = 'Đưa khuôn mặt vào trong khung'
            this._batDauLuc = Date.now()
            this.quet()
        },

        /** Vòng dò liên tục: chỉ dò vị trí khuôn mặt (nhẹ); đủ điều kiện mới xác minh. */
        quet() {
            const video = this.$refs.video
            const lap = async () => {
                if (this.buoc !== 'quet' || !this.stream) return
                if (!['xac_minh', 'khop'].includes(this.pha)) {
                    if (Date.now() - this._batDauLuc > THOI_GIAN_QUET * (this.loai === 'dang_ky' ? 2 : 1)) {
                        this.dungQuet()
                        return
                    }
                    let kq = null
                    try {
                        kq = await faceapi.detectSingleFace(video, tuyChon())
                    } catch (e) {}
                    if (this.buoc !== 'quet') return
                    const tot = this.danhGia(kq)
                    this._onDinh = tot ? this._onDinh + 1 : 0
                    if (this._onDinh >= 2 && Date.now() >= this._choDen) await this.xacMinh()
                }
                this._vong = setTimeout(lap, NHIP)
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

        /** Tính đặc trưng khuôn mặt từ khung hình hiện tại rồi gửi máy chủ so. */
        async xacMinh() {
            const canvas = chupKhung(this.$refs.video)
            if (!canvas) return
            this.pha = 'xac_minh'
            if (this.loai !== 'dang_ky') this.goiY = 'Đang xác minh…'
            let ds = null
            try {
                const kq = await faceapi.detectSingleFace(canvas, tuyChon()).withFaceLandmarks().withFaceDescriptor()
                ds = kq ? Array.from(kq.descriptor) : null
            } catch (e) {}
            if (this.buoc !== 'quet') return
            if (!ds) {
                this.pha = 'tim'
                this._onDinh = 0
                return
            }
            const anh = canvas.toDataURL('image/jpeg', 0.85)

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

            this.anh = anh
            this.dacTrung = ds
            this._chiKhiKhop = true
            if (!this.viTri) this.goiY = 'Đang lấy vị trí…'
            const kq = await this.gui()
            if (this.buoc !== 'quet') return
            if (kq?.khong_khop) {
                this._truot++
                this.pha = 'truot'
                this._onDinh = 0
                this.goiY = 'Chưa khớp với khuôn mặt đã đăng ký — nhìn thẳng, giữ yên…'
                this._choDen = Date.now() + 700
            }
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
            await new Promise((x) => setTimeout(x, 650))
        },

        /** Hết giờ / không tải được bộ nhận diện: dừng camera, giữ lại một ảnh để gửi quản lý nếu cần. */
        dungQuet(loi = '') {
            this.anh ??= chupKhung(this.$refs.video)?.toDataURL('image/jpeg', 0.85) ?? null
            this.tatCamera()
            this.buoc = 'khong_nhan'
            this.tieuDeKhongNhan =
                loi ||
                (this._truot
                    ? 'Khuôn mặt chưa khớp với ảnh đã đăng ký.'
                    : this.loai === 'dang_ky'
                      ? 'Chưa lấy được mẫu khuôn mặt.'
                      : 'Chưa thấy rõ khuôn mặt.')
        },

        layViTri() {
            this.viTri = null
            this.hoiViTri = new Promise((xong) => {
                if (!navigator.geolocation) {
                    this.viTri = { loi: 'Trình duyệt không hỗ trợ định vị' }
                    return xong()
                }
                navigator.geolocation.getCurrentPosition(
                    (vt) => {
                        this.viTri = { lat: vt.coords.latitude, lng: vt.coords.longitude, accuracy: vt.coords.accuracy }
                        xong()
                    },
                    (e) => {
                        this.viTri = { loi: e.code === 1 ? 'Bạn chưa cho phép lấy vị trí' : 'Không lấy được vị trí' }
                        xong()
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
                )
            })
        },

        /** Gửi máy chủ. Trả về kết quả thô khi khuôn mặt chưa khớp (để quét tiếp). */
        async gui() {
            this.dangGui = this.buoc !== 'quet'
            this.loi = ''
            try {
                if (this.loai === 'dang_ky') {
                    this.ketQua(await this.$wire.dangKyKhuonMat(this.dacTrung, this.anh, this.dongY))
                    return null
                }
                await this.hoiViTri
                const kq = await this.$wire.chamCong(this.loai, {
                    lat: this.viTri?.lat ?? null,
                    lng: this.viTri?.lng ?? null,
                    accuracy: this.viTri?.accuracy ?? null,
                    anh: this.anh,
                    descriptor: this.dacTrung,
                    ly_do: this.lyDo || null,
                    chi_khi_khop: this._chiKhiKhop,
                })
                if (kq?.khong_khop) return kq
                if (kq?.ok || kq?.can_ly_do) this.daKhop = this._chiKhiKhop
                if ((kq?.ok || kq?.can_ly_do) && this.buoc === 'quet') await this.thanhCong()
                this.ketQua(kq)
                return kq
            } catch (e) {
                this.tatCamera()
                this.buoc = 'cho'
                this.loi = 'Gửi không được, kiểm tra mạng rồi thử lại.'
                return null
            } finally {
                this.dangGui = false
            }
        },

        ketQua(kq) {
            this.tatCamera()
            if (kq?.can_ly_do) {
                this.buoc = 'ly_do'
                this.viSao = kq.vi_sao || []
                return
            }
            if (!kq?.ok) {
                // Quét không khớp quá nhiều lần → vẫn cho gửi ảnh cho quản lý
                this.buoc = kq?.qua_nhieu ? 'khong_nhan' : 'cho'
                if (kq?.qua_nhieu) this.tieuDeKhongNhan = kq.loi
                else this.loi = kq?.loi || 'Có lỗi, thử lại.'
                return
            }
            this.buoc = 'xong'
            this.thongBao = kq.thong_bao
            this.canhBao = kq.canh_bao || []
        },

        /** Quét mãi không khớp: chấm bằng ảnh vừa chụp, quản lý xem lại (đánh dấu nghi vấn). */
        async guiChoQuanLy() {
            if (!this.anh) return
            this._chiKhiKhop = false
            await this.gui()
        },

        async guiLyDo() {
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
        },

        datLai() {
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
            this.tienDo = 0
            this._chiKhiKhop = true
            this._onDinh = 0
            this._truoc = null
            this._truot = 0
            this._choDen = 0
            this._mau = []
        },

        destroy() {
            this.tatCamera()
        },
    }
}
