@props(['url', 'modalId', 'keyBindings'])

{{--
    Everything a page holds of the palette until the palette is called: the keyboard shortcut and the listener for the
    trigger. The Livewire component, its modal and its styles are fetched on the first call, once per page.
--}}
<span
    hidden
    data-model-search-palette-loader
    x-data="{
        state: 'idle',
        returnTo: null,
        proxy: null,
        // Inside a tap: iOS only raises the keyboard for focus given there. What is typed while the palette loads goes to the proxy and is handed over.
        holdFocus() {
            this.returnTo = document.activeElement
            this.proxy = document.createElement('input')
            this.proxy.setAttribute('aria-hidden', 'true')
            this.proxy.autocomplete = 'off'
            this.proxy.tabIndex = -1
            this.proxy.style.cssText = 'position: fixed; top: 0; left: 0; width: 1px; height: 1px; padding: 0; border: 0; opacity: 0; font-size: 16px; pointer-events: none'
            document.body.append(this.proxy)
            this.proxy.focus({ preventScroll: true })
        },
        releaseFocus() {
            this.proxy?.remove()
            this.proxy = null
        },
        async load(event) {
            if (event.detail?.id !== @js($modalId) || this.state !== 'idle') return
            this.state = 'loading'
            this.holdFocus()
            try {
                const response = await fetch(@js($url), { credentials: 'same-origin', headers: { Accept: 'text/html' } })
                if (!response.ok) throw new Error(`HTTP ${response.status}`)
                const html = await response.text()
                if (!this.$root.isConnected) return this.releaseFocus()
                const host = document.createElement('div')
                host.innerHTML = html
                document.body.append(host)
            } catch (error) {
                this.fail()
            }
        },
        fail() {
            this.state = 'idle'
            const target = this.returnTo
            this.releaseFocus()
            if (target?.isConnected) target.focus({ preventScroll: true })
            new FilamentNotification().title(@js(__('filament-model-search::record-palette.load_failed'))).danger().send()
        },
        open() {
            this.state = 'ready'
            window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: @js($modalId), returnTo: this.returnTo, typed: this.proxy?.value ?? '' } }))
            this.releaseFocus()
        },
    }"
    x-on:open-modal.window="load($event)"
    x-on:filament-model-search-palette-ready.window="open()"
>
    @foreach ($keyBindings as $keyBinding)
        <span x-mousetrap.global.{{ $keyBinding }}="$dispatch('open-modal', { id: @js($modalId) })"></span>
    @endforeach
</span>
