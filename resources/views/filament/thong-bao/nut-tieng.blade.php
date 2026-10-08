{{-- Bật / tắt tiếng báo (nhớ theo từng máy). Bấm bật là phát thử một tiếng. --}}
<button
    type="button"
    x-data="{ bat: (() => { try { return localStorage.getItem('psv-tieng') !== 'tat' } catch (e) { return true } })() }"
    x-on:click="bat = ! bat; try { localStorage.setItem('psv-tieng', bat ? 'bat' : 'tat') } catch (e) {}; if (bat) window.psvTing?.(true)"
    x-bind:title="bat ? 'Tiếng báo: đang bật (bấm để tắt)' : 'Tiếng báo: đang tắt (bấm để bật)'"
    x-bind:aria-label="bat ? 'Tắt tiếng báo' : 'Bật tiếng báo'"
    class="fi-icon-btn fi-size-md"
    style="display:inline-flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;border-radius:.5rem;color:rgb(156 163 175)"
>
    <span x-show="bat">{{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedSpeakerWave) }}</span>
    <span x-show="! bat" x-cloak>{{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedSpeakerXMark) }}</span>
</button>
