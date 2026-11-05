@props(['modalId'])

<x-filament::icon-button
    icon="heroicon-m-magnifying-glass"
    color="gray"
    :label="__('filament-model-search::record-palette.trigger')"
    x-on:click="$dispatch('open-modal', { id: {{ \Illuminate\Support\Js::from($modalId) }} })"
/>
