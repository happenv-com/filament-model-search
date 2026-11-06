<?php

declare(strict_types=1);

use Happenv\FilamentModelSearch\Livewire\RecordPalette;

covers(RecordPalette::class);

// Alpine has initialised the modal: only then do the shortcut and the trigger work.
const PALETTE_READY = '!!window.Alpine && window.Alpine.$data(document.querySelector("[data-fi-modal-id]")).isOpen !== undefined';
const PALETTE_INPUT = 'input[role=combobox]';
const PALETTE_OPTIONS = 'document.querySelectorAll("[role=option]")';
// The offset inside the container, which the opening animation does not move.
const PALETTE_WINDOW_TOP = 'document.querySelector("[data-fi-modal-id=\"'.RecordPalette::MODAL_ID.'\"] .fi-modal-window").offsetTop';

beforeEach(function (): void {
    if (! is_file(dirname(__DIR__, 2).'/node_modules/.bin/playwright')) {
        $this->markTestSkipped('Playwright is not installed: run `npm ci && npx playwright install chromium`.');
    }

    config(['filament-model-search.readiness_cache_seconds' => 0]);

    // The panel's scripts and styles are served from the public directory of the test application: publish them once per run.
    static $published = false;
    if (! $published) {
        $this->artisan('filament:assets')->assertSuccessful();
        $published = true;
    }

    $this->signIn($this->allAbilities());
});

$focused = 'document.activeElement === document.querySelector("'.PALETTE_INPUT.'")';
$trigger = static fn (): string => 'button[aria-label="'.trans('filament-model-search::record-palette.trigger').'"]';
$active = static fn (string $js): string => 'document.querySelector("'.PALETTE_INPUT.'").getAttribute("aria-activedescendant") === '.$js;

it('focuses the phrase when the shortcut opens the palette, near the top of the page', function () use ($focused): void {
    $this->createOrder('OR-1001', '555123');
    $this->indexAll();

    $page = visit('/admin/orders')->assertScript(PALETTE_READY, true)->keys('html > body', 'ControlOrMeta+Shift+K')
        ->assertScript($focused, true)
        ->assertScript(PALETTE_WINDOW_TOP.' <= 128', true);
    $before = $page->script(PALETTE_WINDOW_TOP);

    $page->type(PALETTE_INPUT, 'OR-100')->assertScript(PALETTE_OPTIONS.'.length', 1);

    expect($page->script(PALETTE_WINDOW_TOP))->toBe($before);
});

it('focuses the phrase when the trigger opens the palette, and selects the previous phrase', function () use ($focused, $trigger): void {
    $page = visit('/admin/orders')->click($trigger())->assertScript($focused, true);

    $page->type(PALETTE_INPUT, '555123');
    // Closing by event is what clicking away does.
    $page->script('window.dispatchEvent(new CustomEvent("close-modal", { detail: { id: "'.RecordPalette::MODAL_ID.'" } }))');
    $page->click($trigger())
        ->assertScript($focused, true)
        ->assertScript('(() => { const i = document.querySelector("'.PALETTE_INPUT.'"); return i.value === "555123" && i.selectionStart === 0 && i.selectionEnd === i.value.length })()', true);
});

it('keeps the phrase when Escape closes the palette', function () use ($focused): void {
    $page = visit('/admin/orders')->assertScript(PALETTE_READY, true)->keys('html > body', 'ControlOrMeta+Shift+K')->assertScript($focused, true);

    $page->type(PALETTE_INPUT, '555123')->keys(PALETTE_INPUT, 'Escape')
        ->assertScript('getComputedStyle(document.querySelector("[data-fi-modal-id=\"'.RecordPalette::MODAL_ID.'\"]")).display', 'none')
        ->assertScript('document.querySelector("'.PALETTE_INPUT.'").value', '555123');

    $page->keys('html > body', 'ControlOrMeta+Shift+K')
        ->assertScript($focused, true)
        ->assertScript('(() => { const i = document.querySelector("'.PALETTE_INPUT.'"); return i.selectionStart === 0 && i.selectionEnd === 6 })()', true);
});

it('moves the highlight across both groups with the arrow keys, wraps, and opens exactly the highlighted record', function () use ($active): void {
    $this->createOrder('OR-1001', '555123');
    $this->createOrder('OR-1002', '555123');
    $this->createCustomer('Exact Holder', null, '555123');
    $this->indexAll();

    $page = visit('/admin/orders')->assertScript(PALETTE_READY, true)->keys('html > body', 'ControlOrMeta+Shift+K');
    $page->type(PALETTE_INPUT, '555123')->assertScript(PALETTE_OPTIONS.'.length', 3);
    $page->assertScript('document.querySelectorAll("[role=listbox] [role=group]").length', 2)
        ->assertScript('document.querySelector("'.PALETTE_INPUT.'").getAttribute("aria-expanded")', 'true');

    $options = static fn (string $nth): string => PALETTE_OPTIONS.'['.$nth.']';

    $page->keys(PALETTE_INPUT, 'ArrowDown')->assertScript($active($options('0').'.id'), true)
        ->keys(PALETTE_INPUT, 'ArrowDown')->assertScript($active($options('1').'.id'), true)
        ->keys(PALETTE_INPUT, 'ArrowDown')->assertScript($active($options('2').'.id'), true)
        ->assertScript($options('2').'.closest("[role=group]") !== '.$options('0').'.closest("[role=group]")', true)
        ->keys(PALETTE_INPUT, 'ArrowDown')->assertScript($active($options('0').'.id'), true)
        ->keys(PALETTE_INPUT, 'ArrowUp')->assertScript($active($options('2').'.id'), true)
        ->keys(PALETTE_INPUT, 'ArrowUp')->assertScript($active($options('1').'.id'), true);

    $href = (string) $page->script($options('1').'.href');
    $path = parse_url($href, PHP_URL_PATH);

    $page->keys(PALETTE_INPUT, 'Enter')->assertScript('window.location.pathname', $path);
});

it('keeps the layout of the highlighted row and drops a stale highlight', function (): void {
    $this->createOrder('OR-1003'.str_repeat('LONG', 15), '555123');
    $this->createOrder('OR-1002', '555124');
    $this->indexAll();

    $page = visit('/admin/orders')->on()->mobile()->assertScript(PALETTE_READY, true);
    $page->click('button[aria-label="'.trans('filament-model-search::record-palette.trigger').'"]')->type(PALETTE_INPUT, 'OR-100')->assertScript(PALETTE_OPTIONS.'.length', 2);

    $page->keys(PALETTE_INPUT, 'ArrowDown')
        ->assertScript('(() => { const o = '.PALETTE_OPTIONS.'[0]; const s = getComputedStyle(o); return o.classList.contains("fi-selected") && s.display === "flex" && s.paddingLeft === "8px" && s.flexWrap === "wrap" })()', true)
        ->assertScript('(() => { const o = '.PALETTE_OPTIONS.'[0]; const w = document.querySelector(".fi-modal-window").getBoundingClientRect(); return o.scrollWidth <= o.clientWidth && o.getBoundingClientRect().right <= w.right })()', true);

    // A new phrase replaces the rows: no highlight may point at a row that is gone.
    $page->type(PALETTE_INPUT, 'OR-1002')->assertScript(PALETTE_OPTIONS.'.length', 1)
        ->assertScript('document.querySelector("'.PALETTE_INPUT.'").getAttribute("aria-activedescendant")', null)
        ->type(PALETTE_INPUT, 'zzzzzz')->assertScript(PALETTE_OPTIONS.'.length', 0)
        ->assertScript('document.querySelector("'.PALETTE_INPUT.'").getAttribute("aria-expanded")', 'false');
});

it('works on a phone: focused, full width, in place, scrolling inside, no horizontal scroll', function () use ($focused, $trigger): void {
    config(['filament-model-search.palette.per_group' => 40]);
    foreach (range(1, 30) as $i) {
        $this->createOrder('OR-20'.$i, '55520'.$i);
    }
    $this->indexAll();

    // Each call on the pending page would open a new browser page, so the first assertion fixes it (and waits for Alpine).
    $page = visit('/admin/orders')->on()->mobile()->assertScript(PALETTE_READY, true);
    $page->click($trigger())->assertScript($focused, true);
    $before = $page->script(PALETTE_WINDOW_TOP);

    $page->type(PALETTE_INPUT, 'OR-20')->assertScript(PALETTE_OPTIONS.'.length', 30)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript('(() => { const w = document.querySelector(".fi-modal-window").getBoundingClientRect(); return w.left >= 8 && w.left <= 24 && Math.abs(window.innerWidth - w.right - w.left) < 2 })()', true)
        ->assertScript('(() => { const l = document.querySelector("[role=listbox]"); return getComputedStyle(l).overflowY === "auto" && l.scrollHeight > l.clientHeight && l.getBoundingClientRect().bottom <= window.innerHeight })()', true);

    expect($page->script(PALETTE_WINDOW_TOP))->toBe($before);
});

it('still searches for similar names with the shortcut after the palette updated', function (): void {
    $this->createCustomer('Jonathan Smith', null, '555999');
    $this->indexAll();

    $page = visit('/admin/orders')->assertScript(PALETTE_READY, true)->keys('html > body', 'ControlOrMeta+Shift+K');
    $page->type(PALETTE_INPUT, 'Jonathon')->assertScript(PALETTE_OPTIONS.'.length', 0)
        ->keys(PALETTE_INPUT, 'ControlOrMeta+Enter')
        ->assertScript(PALETTE_OPTIONS.'.length', 1);
});

it('gives the focus back to the element that had it when the palette closes', function () use ($trigger): void {
    $button = $trigger();
    $page = visit('/admin/orders')->assertScript(PALETTE_READY, true);
    $page->script('window.__opener = document.querySelector('.json_encode($button).'); window.__opener.focus()');
    $focusedField = 'document.activeElement === document.querySelector("'.PALETTE_INPUT.'")';

    // Opened with the shortcut and closed with Escape.
    $page->keys($button, 'ControlOrMeta+Shift+K')->assertScript($focusedField, true)
        ->keys(PALETTE_INPUT, 'Escape')->assertScript('document.activeElement === window.__opener', true);

    // Opened by a host's own event and closed with the event a click on the backdrop sends.
    $page->script('window.dispatchEvent(new CustomEvent("open-modal", { detail: { id: "'.RecordPalette::MODAL_ID.'" } }))');
    $page->assertScript($focusedField, true);
    $page->script('window.dispatchEvent(new CustomEvent("close-modal", { detail: { id: "'.RecordPalette::MODAL_ID.'" } }))');
    $page->assertScript('document.activeElement === window.__opener', true);
});

it('does not leave the focus on the hidden proxy or inside the closed modal after a tap on the trigger', function () use ($trigger): void {
    $page = visit('/admin/orders')->on()->mobile()->assertScript(PALETTE_READY, true);
    $page->click($trigger())->assertScript('document.activeElement === document.querySelector("'.PALETTE_INPUT.'")', true);

    $page->script('window.dispatchEvent(new CustomEvent("close-modal", { detail: { id: "'.RecordPalette::MODAL_ID.'" } }))');

    // WebKit never focuses a button on click, so the page element that "had" the focus may be the body there.
    $page->assertScript('!document.activeElement.closest("[data-fi-modal-id]") && document.activeElement.getAttribute("aria-hidden") !== "true"', true);
});
