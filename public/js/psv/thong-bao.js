/**
 * Trang quản trị: cứ 10 giây hỏi máy chủ (route filament.admin.psv.nhip).
 *  - Có thông báo mới → phát tiếng "ting ting", hiện popup góc màn hình, làm
 *    mới chuông ngay (khỏi đợi chuông tự hỏi 30 giây).
 *  - Cập nhật con số trên menu (Duyệt khoản thu, Đơn đặt tour…) tại chỗ.
 *  - Tiêu đề tab có "(3)" khi còn thông báo chưa đọc — nhìn thấy cả khi đang ở tab khác.
 *
 * Trình duyệt chỉ cho phát tiếng sau khi người dùng đã bấm vào trang ít nhất
 * một lần; trước đó vẫn hiện popup, chỉ không có tiếng.
 */
(() => {
    if (window.__psvNhip) return
    window.__psvNhip = true

    const script = document.currentScript
    const DIA_CHI = script?.dataset.nhip
    if (!DIA_CHI) return
    const KHOANG = 10000
    const KHOA_DA_THAY = 'psv-thong-bao-da-thay'
    const tieuDeGoc = document.title.replace(/^\(\d+\)\s*/, '')

    const doc = (k, macDinh) => {
        try {
            return sessionStorage.getItem(k) ?? macDinh
        } catch (e) {
            return macDinh
        }
    }
    const ghi = (k, v) => {
        try {
            sessionStorage.setItem(k, v)
        } catch (e) {}
    }
    const tiengBat = () => {
        try {
            return localStorage.getItem('psv-tieng') !== 'tat'
        } catch (e) {
            return true
        }
    }

    // Tab mới mở: những thông báo đang có coi như đã thấy (không kêu lúc vừa vào trang)
    const lanDauCuaTab = doc(KHOA_DA_THAY, null) === null
    const daThay = new Set(JSON.parse(doc(KHOA_DA_THAY, '[]')))
    let lanDau = true

    let am = null
    const moAm = () => {
        try {
            am ??= new (window.AudioContext || window.webkitAudioContext)()
            if (am.state === 'suspended') am.resume()
        } catch (e) {}
    }
    document.addEventListener('pointerdown', moAm, { once: true })
    document.addEventListener('keydown', moAm, { once: true })

    // Hai nốt ngắn, tạo bằng Web Audio — không cần file âm thanh
    window.psvTing = (boQuaTat = false) => {
        if (!boQuaTat && !tiengBat()) return
        moAm()
        if (!am) return
        const t = am.currentTime
        ;[
            [880, 0],
            [1320, 0.16],
        ].forEach(([tanSo, tre]) => {
            const o = am.createOscillator()
            const g = am.createGain()
            o.type = 'sine'
            o.frequency.value = tanSo
            g.gain.setValueAtTime(0.0001, t + tre)
            g.gain.exponentialRampToValueAtTime(0.3, t + tre + 0.02)
            g.gain.exponentialRampToValueAtTime(0.0001, t + tre + 0.35)
            o.connect(g).connect(am.destination)
            o.start(t + tre)
            o.stop(t + tre + 0.4)
        })
    }

    const mauPopup = { success: 'success', danger: 'danger', warning: 'warning', info: 'info' }

    const hienPopup = (n) => {
        if (!window.FilamentNotification) return
        const tb = new window.FilamentNotification().title(n.title || 'Thông báo mới').body(n.body || '').seconds(12)
        tb.status(mauPopup[n.status] || 'info')
        if (n.url && window.FilamentNotificationAction) {
            tb.actions([new window.FilamentNotificationAction('mo').label('Mở').url(n.url).button()])
        }
        tb.send()
    }

    const capNhatMenu = (ds) => {
        const nut = [...document.querySelectorAll('a.fi-sidebar-item-btn')]
        ds.forEach(({ url, html }) => {
            let dich
            try {
                dich = new URL(url, location.href).href
            } catch (e) {
                return
            }
            nut.filter((a) => a.href === dich).forEach((a) => {
                let o = a.querySelector('.fi-sidebar-item-badge-ctn')
                if (!html) {
                    o?.remove()
                    return
                }
                if (!o) {
                    o = document.createElement('span')
                    o.className = 'fi-sidebar-item-badge-ctn'
                    o.setAttribute('x-show', '$store.sidebar.isOpen')
                    a.appendChild(o)
                }
                if (o.dataset.psvHtml !== html) {
                    o.innerHTML = html
                    o.dataset.psvHtml = html
                }
            })
        })
    }

    const hoi = async () => {
        try {
            const r = await fetch(DIA_CHI, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            })
            if (!r.ok) return
            const d = await r.json()

            const moi = (d.moi || []).filter((n) => !daThay.has(n.id))
            moi.forEach((n) => daThay.add(n.id))
            ghi(KHOA_DA_THAY, JSON.stringify([...daThay].slice(-200)))

            if (moi.length && !(lanDau && lanDauCuaTab)) {
                window.psvTing()
                moi.slice(0, 3).forEach(hienPopup)
                window.Livewire?.dispatch('databaseNotificationsSent')
            }
            lanDau = false

            document.title = (d.chua_doc > 0 ? `(${d.chua_doc}) ` : '') + tieuDeGoc
            capNhatMenu(d.menu || [])
        } catch (e) {}
    }

    hoi()
    setInterval(hoi, KHOANG)
})()
