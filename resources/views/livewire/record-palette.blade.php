<div
    x-data="{
        activeId: null,
        returnTo: null,
        unhook: null,
        inFlight: 0,
        typedAt: 0,
        typedWhileLoading: '',
        init() {
            // After every Livewire update the highlighted option may be gone: forget it, so the field never points at a missing element.
            this.unhook = Livewire.hook('commit', ({ component, succeed, fail }) => {
                if (component.id !== $wire.$id) return
                this.inFlight++
                succeed(() => {
                    this.inFlight--
                    this.$nextTick(() => this.settleHighlight())
                })
                fail(() => this.inFlight--)
            })
            // The palette is on the page only because it was just called: its loader opens it now that it can listen.
            this.$nextTick(() => window.dispatchEvent(new CustomEvent('filament-model-search-palette-ready')))
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
        activeRelated() {
            return this.activeOption()?.closest('.fi-record-palette-row-ctn')?.querySelector('.fi-record-palette-related') ?? null
        },
        enterLabel() {
            return this.activeOption()?.dataset.enterLabel ?? @js($enterLabel)
        },
        // While a phrase is on its way the rows on screen answer an older one: nothing is highlighted and Enter asks the server.
        isSettled() {
            return this.inFlight === 0 && Date.now() - this.typedAt >= @js($debounce)
        },
        settleHighlight() {
            if (this.activeId !== null && this.activeOption() === null) this.activeId = null
            if (this.activeId === null && this.isSettled()) this.activeId = this.$root.querySelector('[data-palette-result]')?.id ?? null
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
            const previous = event.detail?.returnTo ?? document.activeElement
            if (previous !== this.$refs.proxy) this.returnTo = previous
            this.typedWhileLoading = event.detail?.typed ?? ''
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
            this.$nextTick(() => this.settleHighlight())
            // The field only becomes focusable once the window has been shown, and that waits for animation frames, which some browsers (headless WebKit on Linux) deliver a second late or more. Timers are not throttled that way: try on a timer until it takes, while the modal is still open.
            let attempts = 0
            const attempt = () => {
                this.focusInput()
                if (document.activeElement === this.$refs.phrase && this.typedWhileLoading !== '') {
                    $wire.set('phrase', this.typedWhileLoading)
                    this.typedWhileLoading = ''
                }
                if (document.activeElement !== this.$refs.phrase && attempts++ < 200 && this.$root.querySelector('.fi-modal-open')) setTimeout(attempt, 50)
            }
            this.$nextTick(attempt)
        },
        closed(event) {
            if (!this.isThisModal(event)) return
            // Filament's focus trap is still active while this runs: hand the focus back on the next tick.
            const target = this.returnTo
            this.returnTo = null
            this.$nextTick(() => {
                if (target?.isConnected) target.focus({ preventScroll: true })
            })
        },
        submitOnEnter(event) {
            if (event.isComposing || event.ctrlKey || event.metaKey) return
            event.preventDefault()
            const option = this.isSettled() ? this.activeOption() : null
            if (option) {
                option.click()
                return
            }
            $wire.submit()
        },
        searchRelatedOnTab(event) {
            if (event.isComposing || event.shiftKey) return
            const related = this.activeRelated()
            if (!related) return
            event.preventDefault()
            related.click()
        },
        clearPhrase() {
            this.activeId = null
            $wire.set('phrase', '')
            this.$refs.phrase.focus({ preventScroll: true })
        },
        // Backspace in an empty field takes the narrowing off, the way a tag is taken off a search field.
        clearScopeOnBackspace(event) {
            if (event.isComposing || event.target.value !== '' || $wire.scope === null) return
            event.preventDefault()
            $wire.clearScope()
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

    @include('filament-model-search::livewire.record-palette.styles')

    <x-filament::modal
        :id="\Happenv\FilamentModelSearch\Livewire\RecordPalette::MODAL_ID"
        width="3xl"
        :close-button="false"
        :restores-focus="false"
        :aria-label="__('filament-model-search::record-palette.title')"
    >
        <div class="fi-record-palette" x-mousetrap.global.mod-enter="if (isOpen) $wire.searchSimilar()">
            <div class="fi-record-palette-search">
                <x-filament::icon
                    :icon="\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlass"
                    :alias="\Filament\View\PanelsIconAlias::GLOBAL_SEARCH_FIELD"
                    class="fi-record-palette-search-icon"
                />

                <input
                    x-ref="phrase"
                    type="search"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-haspopup="listbox"
                    aria-controls="filament-model-search-palette-results"
                    aria-expanded="{{ $groups !== [] ? 'true' : 'false' }}"
                    x-bind:aria-activedescendant="activeId"
                    x-on:input="activeId = null; typedAt = Date.now()"
                    x-on:keydown.arrow-down="move($event, 1)"
                    x-on:keydown.arrow-up="move($event, -1)"
                    x-on:keydown.tab="searchRelatedOnTab($event)"
                    x-on:keydown.backspace="clearScopeOnBackspace($event)"
                    x-on:keydown.escape="if (!$event.isComposing) $event.preventDefault()"
                    maxlength="1000"
                    autocomplete="off"
                    placeholder="{{ __($placeholder) }}"
                    aria-label="{{ __('filament-model-search::record-palette.search_label') }}"
                    wire:model.live.debounce.{{ $debounce }}ms="phrase"
                    x-on:keydown.enter="submitOnEnter($event)"
                    class="fi-record-palette-input"
                />

                {{-- One place: the spinner stands where the reset button is for as long as a search runs. --}}
                <div class="fi-record-palette-search-tail">
                    <x-filament::loading-indicator
                        wire:loading
                        wire:target="phrase, searchSimilar, searchEveryStage, searchRelated, scopeTo, clearScope"
                    />

                    <span
                        wire:loading.remove
                        wire:target="phrase, searchSimilar, searchEveryStage, searchRelated, scopeTo, clearScope"
                        class="fi-record-palette-clear"
                    >
                        <button
                            type="button"
                            x-cloak
                            x-show="$wire.phrase !== ''"
                            x-on:click="clearPhrase()"
                            aria-label="{{ __('filament-model-search::record-palette.clear') }}"
                            class="fi-record-palette-icon-button"
                        >
                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::XMark" />
                        </button>
                    </span>
                </div>
            </div>

            @if ($chips !== [])
                <nav class="fi-record-palette-chips" aria-label="{{ __('filament-model-search::record-palette.scope_label') }}">
                    <button
                        type="button"
                        wire:key="chip-all"
                        wire:click="clearScope"
                        x-on:click="activeId = null; if ($event.detail > 0) $refs.phrase.focus({ preventScroll: true })"
                        aria-pressed="{{ $scope === null ? 'true' : 'false' }}"
                        @class(['fi-record-palette-chip', 'fi-active' => $scope === null])
                    >
                        {{ __('filament-model-search::record-palette.all') }}
                    </button>

                    @foreach ($chips as $chip)
                        <button
                            type="button"
                            wire:key="chip-{{ $chip['type'] }}"
                            wire:click="scopeTo(@js($chip['type']))"
                            x-on:click="activeId = null; if ($event.detail > 0) $refs.phrase.focus({ preventScroll: true })"
                            aria-pressed="{{ $chip['current'] ? 'true' : 'false' }}"
                            @class(['fi-record-palette-chip', 'fi-active' => $chip['current']])
                        >
                            @if ($chip['icon'])
                                <x-filament::icon :icon="$chip['icon']" class="fi-record-palette-chip-icon" />
                            @endif

                            {{ $chip['label'] }}
                        </button>
                    @endforeach
                </nav>
            @endif

            @if ($indexBuilding && $groups !== [])
                <p role="status" class="fi-record-palette-notice">
                    {{ __('filament-model-search::record-palette.index_building_notice') }}
                </p>
            @endif

            @if ($timedOut && $groups !== [])
                <p role="status" class="fi-record-palette-notice">
                    {{ __('filament-model-search::record-palette.timed_out_notice') }}
                </p>
            @endif

            @if ($similar && $groups !== [])
                <p class="fi-record-palette-notice">
                    {{ __('filament-model-search::record-palette.similar_note') }}
                </p>
            @endif

            <div
                role="listbox"
                id="filament-model-search-palette-results"
                aria-label="{{ __('filament-model-search::record-palette.title') }}"
                @if ($groups === []) hidden @endif
                class="fi-record-palette-results"
            >
                @foreach ($groups as $group)
                    <section wire:key="group-{{ $group['type'] }}" role="group" aria-labelledby="filament-model-search-palette-group-{{ $group['type'] }}">
                        <h3 role="presentation" id="filament-model-search-palette-group-{{ $group['type'] }}" class="fi-record-palette-group-heading">
                            {{ $group['label'] }}
                        </h3>

                        <div class="fi-record-palette-rows">
                            @foreach ($group['items'] as $item)
                                @php($slots = $rows[$group['type']][$item['id']] ?? null)
                                <div wire:key="item-{{ $group['type'] }}-{{ $item['id'] }}" class="fi-record-palette-row-ctn">
                                    <a
                                        id="filament-model-search-palette-option-{{ $group['type'] }}-{{ $item['id'] }}"
                                        role="option"
                                        href="{{ $item['url'] }}"
                                        wire:navigate
                                        tabindex="-1"
                                        data-palette-result
                                        data-enter-label="{{ __('filament-model-search::record-palette.open_record') }}"
                                        x-on:mousemove="if (activeId !== $el.id) activeId = $el.id"
                                        x-bind:aria-selected="activeId === $el.id ? 'true' : 'false'"
                                        x-bind:class="{ 'fi-selected': activeId === $el.id }"
                                        @class(['fi-record-palette-row', 'fi-record-palette-row-slotted' => $slots !== null && ! $slots->isEmpty()])
                                    >
                                        @if ($slots !== null && $slots->leading !== '')
                                            <span class="fi-record-palette-row-leading">{!! $slots->leading !!}</span>
                                        @elseif ($item['image'])
                                            <img src="{{ $item['image'] }}" alt="" loading="lazy" class="fi-record-palette-row-image" />
                                        @elseif ($group['icon'])
                                            <x-filament::icon :icon="$group['icon']" class="fi-record-palette-row-icon" />
                                        @endif

                                        <span class="fi-record-palette-row-main">
                                            @if ($slots !== null && $slots->aboveTitle !== '')
                                                <span class="fi-record-palette-row-above" style="display: block">{!! $slots->aboveTitle !!}</span>
                                            @endif

                                            @if ($slots !== null && $slots->besideTitle !== '')
                                                <span class="fi-record-palette-row-heading" style="display: flex">
                                                    <span class="fi-record-palette-row-title">{{ $item['title'] }}</span>
                                                    <span class="fi-record-palette-row-beside">{!! $slots->besideTitle !!}</span>
                                                </span>
                                            @else
                                                <span class="fi-record-palette-row-title" style="display: block">{{ $item['title'] }}</span>
                                            @endif

                                            @if ($slots !== null && $slots->belowTitle !== '')
                                                <span class="fi-record-palette-row-below" style="display: block">{!! $slots->belowTitle !!}</span>
                                            @elseif ($item['details'] !== [])
                                                <span class="fi-record-palette-row-details" style="display: block">
                                                    @foreach ($item['details'] as $detail)
                                                        <b>{{ $detail['label'] }}:</b> {{ $detail['value'] }}@if (! $loop->last) · @endif
                                                    @endforeach
                                                </span>
                                            @endif
                                        </span>

                                        @if ($slots !== null && $slots->trailing !== '')
                                            <span class="fi-record-palette-row-trailing">{!! $slots->trailing !!}</span>
                                        @endif

                                        @if ($item['hint'])
                                            <span class="fi-record-palette-row-hint" title="{{ $item['hint']['label'] }}: {{ $item['hint']['value'] }}">{{ $item['hint']['label'] }}: {{ $item['hint']['value'] }}</span>
                                        @endif
                                    </a>

                                    @if (filled($item['relatedTerm']))
                                        <button
                                            type="button"
                                            tabindex="-1"
                                            wire:click="searchRelated(@js($group['type']), @js($item['id']))"
                                            x-on:click="activeId = null; $refs.phrase.focus({ preventScroll: true })"
                                            aria-label="{{ __('filament-model-search::record-palette.search_term', ['term' => $item['relatedTerm']]) }}"
                                            title="{{ __('filament-model-search::record-palette.search_term', ['term' => $item['relatedTerm']]) }}"
                                            class="fi-record-palette-icon-button fi-record-palette-related"
                                        >
                                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlass" />
                                        </button>
                                    @endif
                                </div>
                            @endforeach

                            @if ($group['more'])
                                <button
                                    type="button"
                                    wire:key="more-{{ $group['type'] }}"
                                    id="filament-model-search-palette-option-more-{{ $group['type'] }}"
                                    role="option"
                                    tabindex="-1"
                                    wire:click="scopeTo(@js($group['type']))"
                                    x-on:click="activeId = null; $refs.phrase.focus({ preventScroll: true })"
                                    data-enter-label="{{ __('filament-model-search::record-palette.narrow') }}"
                                    x-on:mousemove="if (activeId !== $el.id) activeId = $el.id"
                                    x-bind:aria-selected="activeId === $el.id ? 'true' : 'false'"
                                    x-bind:class="{ 'fi-selected': activeId === $el.id }"
                                    class="fi-record-palette-row fi-record-palette-row-action"
                                >
                                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::ArrowRight" class="fi-record-palette-row-icon" />

                                    <span class="fi-record-palette-row-main">
                                        <span class="fi-record-palette-row-title" style="display: block">{{ __('filament-model-search::record-palette.show_all', ['label' => $group['pluralLabel']]) }}</span>
                                    </span>
                                </button>
                            @endif
                        </div>
                    </section>
                @endforeach

                @if ($listUrl !== null)
                    <div class="fi-record-palette-more">
                        <a
                            id="filament-model-search-palette-option-list"
                            role="option"
                            href="{{ $listUrl }}"
                            wire:navigate
                            tabindex="-1"
                            data-enter-label="{{ __('filament-model-search::record-palette.open_list_enter') }}"
                            x-on:mousemove="if (activeId !== $el.id) activeId = $el.id"
                            x-bind:aria-selected="activeId === $el.id ? 'true' : 'false'"
                            x-bind:class="{ 'fi-selected': activeId === $el.id }"
                            class="fi-record-palette-row fi-record-palette-row-action"
                        >
                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::ArrowRight" class="fi-record-palette-row-icon" />

                            <span class="fi-record-palette-row-main">
                                <span class="fi-record-palette-row-title" style="display: block">{{ __('filament-model-search::record-palette.open_list', ['label' => $listLabel, 'phrase' => $phraseShown]) }}</span>
                            </span>
                        </a>
                    </div>
                @endif

                @if ($groups !== [] && ($offersEveryStage || $offersSimilar))
                    <div class="fi-record-palette-more">
                        @if ($offersEveryStage)
                            <button
                                type="button"
                                id="filament-model-search-palette-option-every-stage"
                                role="option"
                                tabindex="-1"
                                wire:click="searchEveryStage"
                                data-enter-label="{{ __('filament-model-search::record-palette.run') }}"
                                x-on:mousemove="if (activeId !== $el.id) activeId = $el.id"
                                x-bind:aria-selected="activeId === $el.id ? 'true' : 'false'"
                                x-bind:class="{ 'fi-selected': activeId === $el.id }"
                                class="fi-record-palette-row"
                            >
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlassPlus" class="fi-record-palette-row-icon" />

                                <span class="fi-record-palette-row-main">
                                    <span class="fi-record-palette-row-title" style="display: block">{{ __('filament-model-search::record-palette.search_every_stage') }}</span>
                                    <span class="fi-record-palette-row-details" style="display: block">{{ __('filament-model-search::record-palette.stage_note') }}</span>
                                </span>
                            </button>
                        @endif

                        @if ($offersSimilar)
                            <button
                                type="button"
                                id="filament-model-search-palette-option-similar"
                                role="option"
                                tabindex="-1"
                                wire:click="searchSimilar"
                                data-enter-label="{{ __('filament-model-search::record-palette.run') }}"
                                x-on:mousemove="if (activeId !== $el.id) activeId = $el.id"
                                x-bind:aria-selected="activeId === $el.id ? 'true' : 'false'"
                                x-bind:class="{ 'fi-selected': activeId === $el.id }"
                                class="fi-record-palette-row"
                            >
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlassPlus" class="fi-record-palette-row-icon" />

                                <span class="fi-record-palette-row-main">
                                    <span class="fi-record-palette-row-title" style="display: block">{{ __('filament-model-search::record-palette.search_similar') }}</span>
                                </span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>

            @if ($groups === [] && ! $isShort && $outcome)
                <div class="fi-record-palette-empty">
                    <x-filament::empty-state
                        :heading="__($emptyHeading)"
                        :description="__($emptyDescription)"
                        :icon="$outcome->icon()"
                        icon-color="gray"
                        :contained="false"
                    >
                        @if ($offersWiden)
                            <x-slot name="footer">
                                <x-filament::button wire:click="searchSimilar" :icon="\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlassPlus">
                                    {{ __('filament-model-search::record-palette.widen') }}
                                </x-filament::button>
                            </x-slot>
                        @endif
                    </x-filament::empty-state>
                </div>
            @endif

            <div class="fi-record-palette-footer">
                <div class="fi-record-palette-key">
                    <kbd>esc</kbd>
                    <span>{{ __('filament-model-search::record-palette.close') }}</span>
                </div>

                <div class="fi-record-palette-keys">
                    @if ($isShort && filled(trim($phrase)))
                        <span class="fi-record-palette-footer-hint">{{ __('filament-model-search::record-palette.hint') }}</span>
                    @endif

                    @if ($groups !== [])
                        <div class="fi-record-palette-key">
                            <kbd>↑</kbd>
                            <kbd>↓</kbd>
                            <span>{{ __('filament-model-search::record-palette.select') }}</span>
                        </div>
                    @endif

                    <div x-cloak x-show="activeRelated() !== null" class="fi-record-palette-key">
                        <kbd>Tab</kbd>
                        <span>{{ __('filament-model-search::record-palette.search_related') }}</span>
                    </div>

                    <div x-cloak x-show="enterLabel() !== null" class="fi-record-palette-key">
                        <kbd>↵</kbd>
                        <span x-text="enterLabel()"></span>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::modal>
</div>
