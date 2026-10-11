/**
 * Ô chọn vị trí trên bản đồ (trang quản trị — Bản đồ cẩm nang).
 *
 * Bốn cách đặt toạ độ, cách nào cũng cập nhật ngay ô Vĩ độ / Kinh độ:
 *  1. Bấm vào bản đồ (hoặc kéo ghim)
 *  2. Tìm theo tên địa điểm (Nominatim — dữ liệu OpenStreetMap)
 *  3. Dán link Google Maps hoặc toạ độ "31.2304, 121.4737"
 *  4. Gõ thẳng vào ô Vĩ độ / Kinh độ
 *
 * Nền bản đồ: OpenFreeMap (miễn phí, không cần khoá). Không tải được thì
 * dùng nền đơn giản (đất liền Natural Earth) — vẫn bấm chọn được.
 *
 * Nạp bằng x-load: <div x-load x-load-src="…/chon-vi-tri.js" x-data="chonViTri({...})">
 */
import * as maplibregl from '/vendor/maplibre-6.11.2/maplibre-gl.mjs'

const NEN_CHI_TIET = 'https://tiles.openfreemap.org/styles/liberty'

let cssDaNap = false
const napCss = () => {
    if (cssDaNap) return
    cssDaNap = true
    const l = document.createElement('link')
    l.rel = 'stylesheet'
    l.href = '/vendor/maplibre-6.11.2/maplibre-gl.css'
    document.head.appendChild(l)
}

/** Thử tải kiểu bản đồ chi tiết; mất mạng / bị chặn thì trả null sau tối đa 5 giây. */
const kieuChiTiet = async () => {
    try {
        const r = await fetch(NEN_CHI_TIET, { signal: AbortSignal.timeout(5000) })
        return r.ok ? await r.json() : null
    } catch (e) {
        return null
    }
}

const kieuDuPhong = () => ({
    version: 8,
    sources: { dat: { type: 'geojson', data: '/vendor/geo/dat-lien.json' } },
    layers: [
        { id: 'nen', type: 'background', paint: { 'background-color': '#bfe3f7' } },
        { id: 'dat', type: 'fill', source: 'dat', paint: { 'fill-color': '#f4efe2', 'fill-outline-color': '#c9bfa6' } },
    ],
})

const lamTron = (v) => Math.round(Number(v) * 1e7) / 1e7
const hopLe = (lat, lng) =>
    Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180

/**
 * Đọc toạ độ từ link Google Maps / Apple Maps / OpenStreetMap hoặc chữ "lat, lng".
 * @returns {{lat:number,lng:number,zoom?:number}|{loi:string}|null}
 */
export const docToaDo = (chu) => {
    const s = (chu || '').trim()
    if (!s) return null
    if (/maps\.app\.goo\.gl|goo\.gl\/maps/i.test(s)) {
        return { loi: 'Đây là link rút gọn. Mở link trên trình duyệt, đợi bản đồ hiện ra rồi copy link đầy đủ trên thanh địa chỉ và dán lại.' }
    }
    const mau = [
        /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/, // vị trí chính xác của địa điểm đã ghim
        /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)(?:,(\d+(?:\.\d+)?)z)?/, // tâm khung nhìn
        /[?&#](?:q|query|ll|destination|center|mlat)=(-?\d+(?:\.\d+)?)(?:,|%2C|\s|&mlon=)\s*(-?\d+(?:\.\d+)?)/i,
        /#map=(\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)/, // openstreetmap.org/#map=z/lat/lng
        /^\(?\s*(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)\s*\)?$/,
    ]
    for (const [i, m] of mau.map((re, i) => [i, s.match(re)])) {
        if (!m) continue
        const [lat, lng, zoom] = i === 3 ? [+m[2], +m[3], +m[1]] : [+m[1], +m[2], m[3] ? +m[3] : undefined]
        if (hopLe(lat, lng)) return { lat, lng, zoom }
    }
    return { loi: 'Không đọc được toạ độ. Dán link Google Maps đầy đủ hoặc gõ dạng "31.2304, 121.4737".' }
}

export default function chonViTri({ lat, lng, zoom = null, tam = null, diemKhac = [] }) {
    let map = null
    let ghim = null

    return {
        lat,
        lng,
        zoom,
        timKiem: '',
        ketQua: [],
        dangTim: false,
        dan: '',
        thongBao: '',
        loi: '',
        nenDuPhong: false,
        zoomHienTai: null,

        async init() {
            napCss()
            const kieu = await kieuChiTiet()
            this.nenDuPhong = !kieu
            const coSan = hopLe(+this.lat, +this.lng)
            map = new maplibregl.Map({
                container: this.$refs.khung,
                style: kieu || kieuDuPhong(),
                center: coSan ? [+this.lng, +this.lat] : tam ? [tam[1], tam[0]] : [108, 16],
                zoom: coSan ? Math.max(+this.zoom || 0, tam?.[2] ?? 13, 13) : (tam?.[2] ?? 3),
                attributionControl: { compact: true },
                cooperativeGestures: false,
                locale: {
                    'NavigationControl.ZoomIn': 'Phóng to',
                    'NavigationControl.ZoomOut': 'Thu nhỏ',
                    'NavigationControl.ResetBearing': 'Xoay về hướng bắc',
                },
            })
            map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right')
            this.zoomHienTai = map.getZoom().toFixed(1)
            map.on('zoomend', () => (this.zoomHienTai = map.getZoom().toFixed(1)))

            const el = document.createElement('div')
            el.style.cssText = 'width:26px;height:26px;border-radius:50% 50% 50% 0;background:#ea580c;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);cursor:grab'
            ghim = new maplibregl.Marker({ element: el, draggable: true, anchor: 'bottom-left', offset: [-4, 4] })
            if (coSan) ghim.setLngLat([+this.lng, +this.lat]).addTo(map)
            ghim.on('dragend', () => {
                const p = ghim.getLngLat()
                this.datViTri(p.lat, p.lng, false)
            })
            map.on('click', (e) => this.datViTri(e.lngLat.lat, e.lngLat.lng, false))

            // Các điểm khác cùng thành phố — chấm xám để dễ canh, tránh đặt trùng
            map.on('load', () => {
                if (!diemKhac.length) return
                map.addSource('diem-khac', {
                    type: 'geojson',
                    data: {
                        type: 'FeatureCollection',
                        features: diemKhac.map((d) => ({
                            type: 'Feature',
                            properties: { ten: d.ten },
                            geometry: { type: 'Point', coordinates: [d.lng, d.lat] },
                        })),
                    },
                })
                map.addLayer({
                    id: 'diem-khac',
                    type: 'circle',
                    source: 'diem-khac',
                    paint: { 'circle-radius': 6, 'circle-color': '#0169a9', 'circle-opacity': 0.55, 'circle-stroke-color': '#fff', 'circle-stroke-width': 2 },
                })
            })

            // Gõ tay vào ô Vĩ độ / Kinh độ → ghim chạy theo
            const theoO = () => {
                const a = +this.lat
                const b = +this.lng
                if (!hopLe(a, b)) return
                const p = ghim.getLngLat?.()
                if (p && Math.abs(p.lat - a) < 1e-7 && Math.abs(p.lng - b) < 1e-7) return
                ghim.setLngLat([b, a]).addTo(map)
                map.easeTo({ center: [b, a], duration: 400 })
            }
            this.$watch('lat', theoO)
            this.$watch('lng', theoO)
        },

        datViTri(a, b, bay = true, z = null) {
            if (!hopLe(a, b)) return
            this.lat = lamTron(a)
            this.lng = lamTron(b)
            ghim.setLngLat([this.lng, this.lat]).addTo(map)
            if (bay) map.flyTo({ center: [this.lng, this.lat], zoom: z ?? Math.max(map.getZoom(), 14), duration: 900 })
            this.loi = ''
            this.thongBao = `Đã đặt ghim: ${this.lat}, ${this.lng}`
        },

        async tim() {
            const q = this.timKiem.trim()
            if (q.length < 2) return
            this.dangTim = true
            this.loi = ''
            this.ketQua = []
            try {
                const tamNhin = map.getBounds()
                const url = new URL('https://nominatim.openstreetmap.org/search')
                url.search = new URLSearchParams({
                    q,
                    format: 'jsonv2',
                    limit: '6',
                    'accept-language': 'vi,en',
                    viewbox: [tamNhin.getWest(), tamNhin.getNorth(), tamNhin.getEast(), tamNhin.getSouth()].join(','),
                })
                const r = await fetch(url, { signal: AbortSignal.timeout(8000) })
                if (!r.ok) throw new Error()
                this.ketQua = (await r.json()).map((k) => ({ ten: k.display_name, lat: +k.lat, lng: +k.lon }))
                if (!this.ketQua.length) this.loi = 'Không tìm thấy. Thử tên tiếng Anh / tên địa phương, hoặc dán link Google Maps.'
            } catch (e) {
                this.loi = 'Không tìm được lúc này (mạng hoặc dịch vụ tìm kiếm bận). Dán link Google Maps hoặc bấm thẳng lên bản đồ.'
            } finally {
                this.dangTim = false
            }
        },

        chonKetQua(k) {
            this.ketQua = []
            this.datViTri(k.lat, k.lng, true, 16)
        },

        docDan() {
            const kq = docToaDo(this.dan)
            if (!kq) return
            if (kq.loi) {
                this.loi = kq.loi
                return
            }
            this.datViTri(kq.lat, kq.lng, true, kq.zoom ?? 16)
            this.dan = ''
        },

        layZoom() {
            this.zoom = Math.round(map.getZoom() * 10) / 10
            const c = map.getCenter()
            this.datViTri(c.lat, c.lng, false)
            this.thongBao = `Khi khách bấm vào, bản đồ sẽ mở đúng khung đang thấy (zoom ${this.zoom}).`
        },

        destroy() {
            map?.remove()
            map = null
        },
    }
}
