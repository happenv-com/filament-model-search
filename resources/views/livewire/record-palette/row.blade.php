{{--
    One record of the palette: a link to the record whose plain click goes through the component (so that the opening
    is noted in the history), and whose modified clicks (new tab, middle click) stay the browser's own.
    Needs $type, $item, $icon, $slots and $isResult (a search result has a match line and a related search).
--}}
<div wire:key="item-{{ $type }}-{{ $item['id'] }}" class="fi-record-palette-row-ctn">
    <a
        id="filament-model-search-palette-option-{{ $type }}-{{ $item['id'] }}"
        role="option"
        href="{{ $item['url'] }}"
        x-on:click="if ($event.button === 0 && ! $event.metaKey && ! $event.ctrlKey && ! $event.shiftKey && ! $event.altKey) { $event.preventDefault(); $wire.open(@js($type), @js($item['id'])) }"
        tabindex="-1"
        @if ($isResult) data-palette-result @endif
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
        @elseif ($icon)
            <x-filament::icon :icon="$icon" class="fi-record-palette-row-icon" />
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

        @if ($isResult && $item['hint'])
            <span class="fi-record-palette-row-hint" title="{{ $item['hint']['label'] }}: {{ $item['hint']['value'] }}">{{ $item['hint']['label'] }}: {{ $item['hint']['value'] }}</span>
        @endif
    </a>

    @if ($isResult && filled($item['relatedTerm']))
        <button
            type="button"
            tabindex="-1"
            wire:click="searchRelated(@js($type), @js($item['id']))"
            x-on:click="activeId = null; $refs.phrase.focus({ preventScroll: true })"
            aria-label="{{ __('filament-model-search::record-palette.search_term', ['term' => $item['relatedTerm']]) }}"
            title="{{ __('filament-model-search::record-palette.search_term', ['term' => $item['relatedTerm']]) }}"
            class="fi-record-palette-icon-button fi-record-palette-related"
        >
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlass" />
        </button>
    @endif
</div>
