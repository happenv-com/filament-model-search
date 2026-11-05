<div
    x-data="{
        submitOnEnter(event) {
            if (event.isComposing || event.ctrlKey || event.metaKey) return
            event.preventDefault()
            $wire.submit()
        },
    }"
>
    @foreach ($keyBindings as $keyBinding)
        <span x-mousetrap.global.{{ $keyBinding }}="$dispatch('open-modal', { id: 'filament-model-search-record-palette' })" hidden></span>
    @endforeach

    <x-filament::modal
        id="filament-model-search-record-palette"
        width="2xl"
        :close-button="false"
        :aria-label="__('filament-model-search::record-palette.title')"
    >
        <div
            x-mousetrap.global.mod-enter="if (isOpen) $wire.searchSimilar()"
            style="display: grid; gap: 0.75rem"
        >
            <x-filament::input.wrapper :prefix-icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass" inline-prefix>
                <input
                    type="search"
                    maxlength="1000"
                    autocomplete="off"
                    placeholder="{{ __('filament-model-search::record-palette.placeholder') }}"
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

            @forelse ($groups as $group)
                <section wire:key="group-{{ $group['type'] }}" style="display: grid; gap: 0.25rem">
                    <h3 style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; opacity: 0.7">
                        @if ($group['icon'])
                            <x-filament::icon :icon="$group['icon']" style="width: 1rem; height: 1rem" />
                        @endif
                        {{ $group['label'] }}
                    </h3>

                    @foreach ($group['items'] as $item)
                        <a
                            wire:key="item-{{ $group['type'] }}-{{ $item['id'] }}"
                            href="{{ $item['url'] }}"
                            wire:navigate
                            style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem; padding: 0.375rem 0.5rem; border-radius: 0.5rem"
                            class="fi-dropdown-list-item"
                        >
                            <span style="font-weight: 500">{{ $item['title'] }}</span>
                            @if ($item['hint'])
                                <span style="font-size: 0.875rem; opacity: 0.7">{{ $item['hint']['label'] }}: {{ $item['hint']['value'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </section>
            @empty
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
            @endforelse

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
