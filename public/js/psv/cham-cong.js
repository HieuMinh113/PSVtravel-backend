/**
 * Trang Chấm công: mở camera trước, lấy vị trí GPS, nhận diện khuôn mặt bằng
 * face-api (chạy ngay trong trình duyệt, ảnh không gửi ra dịch vụ ngoài), gửi
 * ảnh + 128 số đặc trưng khuôn mặt + toạ độ lên máy chủ. Máy chủ so khuôn mặt
 * với ảnh đăng ký, tính trễ / sớm / ngoài công ty và hỏi lý do nếu cần.
 *
 * Nạp bằng x-load của Filament: <div x-load x-load-src="…/cham-cong.js" x-data="chamCong({...})">
 */
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
            try {
                await faceapi.tf.setBackend('webgl')
            } catch (e) {
                await faceapi.tf.setBackend('cpu')
            }
            await faceapi.tf.ready()
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(moHinh),
                faceapi.nets.faceLandmark68Net.loadFromUri(moHinh),
                faceapi.nets.faceRecognitionNet.loadFromUri(moHinh),
            ])
        })
        taiMoHinh.catch(() => { taiMoHinh = null })

        return taiMoHinh
    }

    const tuyChon = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 })

    return {
        buoc: 'cho', // cho | camera | ly_do | xong
        loai: null, // vao | ra | dang_ky
        dongY: false,
        trangThai: '',
        loi: '',
        thongBao: '',
        canhBao: [],
        viSao: [],
        lyDo: '',
        dangGui: false,
        thayMat: false,
        choChupKhongMat: false,
        stream: null,
        viTri: null,
        hoiViTri: null,
        anh: null,
        dacTrung: null,
        _vong: null,
        _henGio: null,

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
            this.buoc = 'camera'
            this.trangThai = 'Đang mở camera…'
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
            this.trangThai = 'Đang tải bộ nhận diện khuôn mặt (lần đầu hơi lâu)…'
            try {
                await napThuVien()
            } catch (e) {
                this.trangThai = ''
                this.loi = e.message + ' Vẫn chụp được, quản lý sẽ xem ảnh.'
                this.choChupKhongMat = true
                return
            }
            this.trangThai = 'Nhìn thẳng vào camera…'
            this.doMat()
            // Đeo khẩu trang / thiếu sáng mãi không thấy mặt → cho chụp, quản lý xem lại
            if (loai !== 'dang_ky') this._henGio = setTimeout(() => { this.choChupKhongMat = true }, 8000)
        },

        doMat() {
            const video = this.$refs.video
            const lap = async () => {
                if (this.buoc !== 'camera' || !this.stream) return
                try {
                    const kq = await faceapi.detectSingleFace(video, tuyChon())
                    this.thayMat = !!kq
                    this.trangThai = kq ? 'Đã thấy khuôn mặt — bấm Chụp' : 'Chưa thấy khuôn mặt — nhìn thẳng, đủ sáng'
                } catch (e) {}
                this._vong = setTimeout(lap, 350)
            }
            lap()
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

        async chup() {
            const video = this.$refs.video
            if (!video.videoWidth) return
            const rong = Math.min(640, video.videoWidth)
            const canvas = document.createElement('canvas')
            canvas.width = rong
            canvas.height = Math.round(video.videoHeight * (rong / video.videoWidth))
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height)
            this.anh = canvas.toDataURL('image/jpeg', 0.85)
            this.trangThai = 'Đang nhận diện…'
            this.dacTrung = null
            if (faceapi) {
                try {
                    const kq = await faceapi.detectSingleFace(canvas, tuyChon()).withFaceLandmarks().withFaceDescriptor()
                    this.dacTrung = kq ? Array.from(kq.descriptor) : null
                } catch (e) {}
            }
            if (this.loai === 'dang_ky' && !this.dacTrung) {
                this.trangThai = 'Chưa nhận ra khuôn mặt trong ảnh — nhìn thẳng, bỏ khẩu trang, đủ sáng rồi chụp lại.'
                return
            }
            this.tatCamera()
            await this.gui()
        },

        async gui() {
            this.dangGui = true
            this.loi = ''
            try {
                if (this.loai === 'dang_ky') {
                    const kq = await this.$wire.dangKyKhuonMat(this.dacTrung, this.anh, this.dongY)
                    this.ketQua(kq)
                    return
                }
                this.trangThai = 'Đang lấy vị trí…'
                await this.hoiViTri
                const kq = await this.$wire.chamCong(this.loai, {
                    lat: this.viTri?.lat ?? null,
                    lng: this.viTri?.lng ?? null,
                    accuracy: this.viTri?.accuracy ?? null,
                    anh: this.anh,
                    descriptor: this.dacTrung,
                    ly_do: this.lyDo || null,
                })
                this.ketQua(kq)
            } catch (e) {
                this.buoc = 'cho'
                this.loi = 'Gửi không được, kiểm tra mạng rồi thử lại.'
            } finally {
                this.dangGui = false
            }
        },

        ketQua(kq) {
            if (kq?.can_ly_do) {
                this.buoc = 'ly_do'
                this.viSao = kq.vi_sao || []
                return
            }
            if (!kq?.ok) {
                this.buoc = 'cho'
                this.loi = kq?.loi || 'Có lỗi, thử lại.'
                return
            }
            this.buoc = 'xong'
            this.thongBao = kq.thong_bao
            this.canhBao = kq.canh_bao || []
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
            clearTimeout(this._henGio)
            this.stream?.getTracks().forEach((t) => t.stop())
            this.stream = null
            this.thayMat = false
        },

        datLai() {
            this.tatCamera()
            this.buoc = 'cho'
            this.loi = ''
            this.thongBao = ''
            this.canhBao = []
            this.viSao = []
            this.lyDo = ''
            this.anh = null
            this.dacTrung = null
            this.choChupKhongMat = false
            this.trangThai = ''
        },

        destroy() {
            this.tatCamera()
        },
    }
}
