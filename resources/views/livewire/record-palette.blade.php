<div
    x-data="{
        activeId: null,
        returnTo: null,
        unhook: null,
        init() {
            // After every Livewire update the highlighted option may be gone: forget it, so the field never points at a missing element.
            this.unhook = Livewire.hook('commit', ({ component, succeed }) => {
                if (component.id !== $wire.$id) return
                succeed(() => this.$nextTick(() => this.forgetStaleOption()))
            })
        },
        destroy() {
            this.unhook?.()
        },
        options() {
            return Array.from(this.$root.querySelectorAll('[role=option]'))
        },
        activeOption() {
            return this.activeId === null ? null : (document.getElementById(this.activeId) ?? null)
        },
        forgetStaleOption() {
            if (this.activeId !== null && this.activeOption() === null) this.activeId = null
        },
        move(event, step) {
            if (event.isComposing) return
            event.preventDefault()
            const options = this.options()
            if (options.length === 0) return
            const current = options.findIndex((option) => option.id === this.activeId)
            const next = current === -1
                ? (step > 0 ? 0 : options.length - 1)
                : (current + step + options.length) % options.length
            this.activeId = options[next].id
            options[next].scrollIntoView({ block: 'nearest' })
        },
        isThisModal(event) {
            return event.detail?.id === @js(\Happenv\FilamentModelSearch\Livewire\RecordPalette::MODAL_ID)
        },
        // Runs synchronously inside whatever dispatched open-modal, so inside a tap: iOS only raises the keyboard for focus given there.
        // The proxy is focused now and hands the focus to the real field once the modal is visible, which keeps the keyboard up.
        prepare(event) {
            if (!this.isThisModal(event) || this.$root.querySelector('.fi-modal-open')) return
            if (document.activeElement !== this.$refs.proxy) this.returnTo = document.activeElement
            this.$refs.proxy.focus({ preventScroll: true })
        },
        focusInput() {
            const input = this.$refs.phrase
            if (!input || document.activeElement === input) return
            input.focus({ preventScroll: true })
            input.select()
        },
        opened(event) {
            if (!this.isThisModal(event)) return
            this.activeId = null
            // The window is only visible after the next frame, and Filament's focus trap, which keeps focus that is already inside the modal, starts after two: try now, then once more.
            this.$nextTick(() => this.focusInput())
            requestAnimationFrame(() => requestAnimationFrame(() => this.focusInput()))
        },
        closed(event) {
            if (!this.isThisModal(event)) return
            // Filament would give the focus back to the proxy, which was the focused element when it opened: the page's own element gets it instead.
            this.$refs.proxy.blur()
            if (this.returnTo?.isConnected) this.returnTo.focus({ preventScroll: true })
            this.returnTo = null
        },
        submitOnEnter(event) {
            if (event.isComposing || event.ctrlKey || event.metaKey) return
            event.preventDefault()
            const option = this.activeOption()
            if (option) {
                option.click()
                return
            }
            $wire.submit()
        },
    }"
    x-on:open-modal.window="prepare($event)"
    x-on:x-modal-opened.document="opened($event)"
    x-on:modal-closed.window="closed($event)"
>
    <input
        x-ref="proxy"
        type="text"
        tabindex="-1"
        aria-hidden="true"
        autocomplete="off"
        style="position: fixed; top: 0; left: 0; width: 1px; height: 1px; padding: 0; border: 0; opacity: 0; font-size: 16px; pointer-events: none"
    />

    <style>
        [data-fi-modal-id='{{ \Happenv\FilamentModelSearch\Livewire\RecordPalette::MODAL_ID }}'] > .fi-modal-window-ctn {
            grid-template-rows: clamp(1rem, 8vh, 5rem) auto 1fr;
            align-content: start;
        }
    </style>

    @foreach ($keyBindings as $keyBinding)
        <span wire:ignore x-mousetrap.global.{{ $keyBinding }}="$dispatch('open-modal', { id: @js(\Happenv\FilamentModelSearch\Livewire\RecordPalette::MODAL_ID) })" hidden></span>
    @endforeach

    <x-filament::modal
        :id="\Happenv\FilamentModelSearch\Livewire\RecordPalette::MODAL_ID"
        width="2xl"
        :close-button="false"
        :restores-focus="false"
        :aria-label="__('filament-model-search::record-palette.title')"
    >
        <div
            x-mousetrap.global.mod-enter="if (isOpen) $wire.searchSimilar()"
            style="display: grid; gap: 0.5rem"
        >
            <x-filament::input.wrapper :prefix-icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass" inline-prefix>
                <input
                    x-ref="phrase"
                    type="search"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-haspopup="listbox"
                    aria-controls="filament-model-search-palette-results"
                    aria-expanded="{{ $groups !== [] ? 'true' : 'false' }}"
                    x-bind:aria-activedescendant="activeId"
                    x-on:input="activeId = null"
                    x-on:keydown.arrow-down="move($event, 1)"
                    x-on:keydown.arrow-up="move($event, -1)"
                    x-on:keydown.escape="$event.preventDefault()"
                    maxlength="1000"
                    autocomplete="off"
                    placeholder="{{ __('filament-model-search::record-palette.placeholder') }}"
                    aria-label="{{ __('filament-model-search::record-palette.search_label') }}"
                    wire:model.live.debounce.{{ $debounce }}ms="phrase"
                    x-on:keydown.enter="submitOnEnter($event)"
                    class="fi-input fi-input-has-inline-prefix"
                />
            </x-filament::input.wrapper>

            @if ($indexBuilding && $groups !== [])
                <p role="status" style="font-size: 0.875rem; opacity: 0.75">
                    {{ __('filament-model-search::record-palette.index_building_notice') }}
                </p>
            @endif

            @if ($timedOut && $groups !== [])
                <p role="status" style="font-size: 0.875rem; opacity: 0.75">
                    {{ __('filament-model-search::record-palette.timed_out_notice') }}
                </p>
            @endif

            @if ($similar && $groups !== [])
                <p style="font-size: 0.875rem; opacity: 0.75">
                    {{ __('filament-model-search::record-palette.similar_note') }}
                </p>
            @endif

            <div role="listbox" id="filament-model-search-palette-results" aria-label="{{ __('filament-model-search::record-palette.title') }}" style="display: {{ $groups === [] ? 'none' : 'grid' }}; gap: 0.5rem; max-height: 55vh; max-height: 55dvh; overflow-y: auto; overscroll-behavior: contain">
                @foreach ($groups as $group)
                <section wire:key="group-{{ $group['type'] }}" role="group" aria-labelledby="filament-model-search-palette-group-{{ $group['type'] }}" style="display: grid; gap: 0.125rem">
                    <h3 role="presentation" id="filament-model-search-palette-group-{{ $group['type'] }}" style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; opacity: 0.7">
                        @if ($group['icon'])
                            <x-filament::icon :icon="$group['icon']" style="width: 1rem; height: 1rem" />
                        @endif
                        {{ $group['label'] }}
                    </h3>

                    @foreach ($group['items'] as $item)
                        <a
                            wire:key="item-{{ $group['type'] }}-{{ $item['id'] }}"
                            id="filament-model-search-palette-option-{{ $group['type'] }}-{{ $item['id'] }}"
                            role="option"
                            href="{{ $item['url'] }}"
                            wire:navigate
                            tabindex="-1"
                            x-on:mousemove="if (activeId !== $el.id) activeId = $el.id"
                            x-bind:aria-selected="activeId === $el.id ? 'true' : 'false'"
                            x-bind:class="{ 'fi-selected': activeId === $el.id }"
                            style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem; padding: 0.25rem 0.5rem; border-radius: 0.5rem; white-space: normal; overflow-wrap: anywhere; outline: 1px solid transparent"
                            class="fi-dropdown-list-item"
                        >
                            <span style="font-weight: 500">{{ $item['title'] }}</span>
                            @if ($item['hint'])
                                <span style="font-size: 0.875rem; opacity: 0.7">{{ $item['hint']['label'] }}: {{ $item['hint']['value'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </section>
                @endforeach
            </div>

            @if ($groups === [])
                @if ($isShort)
                    <p style="font-size: 0.875rem; opacity: 0.7">{{ __('filament-model-search::record-palette.hint') }}</p>
                @elseif ($outcome)
                    <x-filament::empty-state
                        :heading="__($outcome->heading())"
                        :description="__($outcome->description())"
                        icon="heroicon-o-magnifying-glass"
                        icon-color="gray"
                        :contained="false"
                    />
                @endif
            @endif

            @if ($offersEveryStage || $offersSimilar)
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem">
                    @if ($offersEveryStage)
                        <x-filament::link tag="button" type="button" wire:click="searchEveryStage">
                            {{ __('filament-model-search::record-palette.search_every_stage') }}
                        </x-filament::link>
                        <span style="font-size: 0.875rem; opacity: 0.7">{{ __('filament-model-search::record-palette.stage_note') }}</span>
                    @endif

                    @if ($offersSimilar)
                        <x-filament::link tag="button" type="button" wire:click="searchSimilar">
                            {{ __('filament-model-search::record-palette.search_similar') }}
                        </x-filament::link>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::modal>
</div>
