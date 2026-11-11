<style>
    [data-fi-modal-id='{{ \Happenv\FilamentModelSearch\Livewire\RecordPalette::MODAL_ID }}'] {
        & > .fi-modal-close-overlay {
            background-color: color-mix(in oklab, var(--gray-500) 25%, transparent);
            backdrop-filter: blur(4px);
        }

        &:where(.dark *) > .fi-modal-close-overlay {
            background-color: color-mix(in oklab, var(--gray-950) 45%, transparent);
        }

        /* The space over the window, and the height the window may take: what the visual viewport leaves (see fitToViewport). */
        --fi-record-palette-gap: clamp(0.75rem, 8vh, 5rem);

        @media (max-width: 40rem) {
            --fi-record-palette-gap: 0.75rem;
        }

        & > .fi-modal-window-ctn {
            grid-template-rows: var(--fi-record-palette-gap) auto 1fr;
            align-content: start;
            /* The gap row is the only space over the window, so the height below can be counted from it. */
            padding-block: 0;
            translate: 0 var(--fi-record-palette-viewport-top, 0px);
        }

        & > .fi-modal-window-ctn > .fi-modal-window {
            display: flex;
            flex-direction: column;
            max-height: calc(var(--fi-record-palette-viewport-height, 100dvh) - var(--fi-record-palette-gap) - 0.75rem);
            overflow: hidden;
            border-radius: 0.75rem;
        }

        & .fi-modal-content {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            min-height: 0;
            padding: 0;
            gap: 0;
        }
    }

    .fi-record-palette {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        color: var(--gray-700);
        font-size: 0.875rem;
        line-height: 1.25rem;

        &:where(.dark *) {
            color: var(--gray-300);
        }

        & > * + * {
            border-top: 1px solid var(--gray-100);
        }

        &:where(.dark *) > * + * {
            border-top-color: color-mix(in oklab, var(--gray-500) 20%, transparent);
        }

        & kbd {
            display: inline-grid;
            place-items: center;
            min-width: 1.375rem;
            height: 1.375rem;
            padding: 0 0.3125rem;
            border: 1px solid var(--gray-400);
            border-radius: 0.25rem;
            background-color: white;
            color: var(--gray-900);
            font: inherit;
            font-size: 0.75rem;
            line-height: 1;
        }

        &:where(.dark *) kbd {
            border-color: var(--gray-600);
            background-color: var(--gray-950);
            color: var(--gray-200);
        }
    }

    .fi-record-palette-search {
        display: flex;
        align-items: center;
        gap: 1rem;
        height: 3rem;
        padding: 0 1rem;

        /* A finger, not a pointer: a taller field on a phone, with room around the 16px text that keeps iOS from zooming. */
        @media (max-width: 40rem) {
            height: 3.5rem;
        }
    }

    .fi-record-palette-search-icon {
        flex: none;
        width: 1.25rem;
        height: 1.25rem;
        color: var(--gray-400);

        &:where(.dark *) {
            color: var(--gray-500);
        }
    }

    .fi-record-palette-input {
        flex: 1;
        min-width: 0;
        height: 100%;
        padding: 0;
        border: 0;
        outline: 0;
        background: transparent;
        box-shadow: none;
        color: inherit;
        font: inherit;

        &::placeholder {
            color: var(--gray-400);
        }

        &:where(.dark *)::placeholder {
            color: var(--gray-500);
        }

        &::-webkit-search-cancel-button {
            appearance: none;
        }

        @media (max-width: 40rem) {
            font-size: 1rem;
        }
    }

    .fi-record-palette-search-tail {
        display: flex;
        flex: none;
        align-items: center;
        gap: 0.5rem;
        color: var(--gray-400);

        &:where(.dark *) {
            color: var(--gray-500);
        }

        & .fi-loading-indicator {
            width: 1.125rem;
            height: 1.125rem;
        }
    }

    .fi-record-palette-clear {
        display: grid;
        place-items: center;
    }

    .fi-record-palette-chips {
        display: flex;
        gap: 0.5rem;
        padding: 0.25rem 1rem 0.75rem;
        overflow-x: auto;
        scrollbar-width: none;

        &::-webkit-scrollbar {
            display: none;
        }
    }

    .fi-record-palette-chip {
        display: inline-flex;
        flex: none;
        align-items: center;
        gap: 0.375rem;
        height: 1.75rem;
        padding-inline: 0.625rem;
        border-radius: 9999px;
        background-color: var(--gray-100);
        color: var(--gray-700);
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1rem;
        white-space: nowrap;
        cursor: pointer;

        &:hover {
            background-color: var(--gray-200);
        }

        &:focus-visible {
            outline: 2px solid var(--primary-600);
            outline-offset: 1px;
        }

        &.fi-active {
            background-color: color-mix(in oklab, var(--primary-500) 12%, transparent);
            color: var(--primary-700);
        }

        &:where(.dark *) {
            background-color: color-mix(in oklab, var(--gray-500) 15%, transparent);
            color: var(--gray-300);

            &:hover {
                background-color: color-mix(in oklab, var(--gray-500) 25%, transparent);
            }

            &.fi-active {
                background-color: color-mix(in oklab, var(--primary-400) 18%, transparent);
                color: var(--primary-300);
            }
        }
    }

    .fi-record-palette-chip-icon {
        width: 1rem;
        height: 1rem;
    }

    .fi-record-palette-row-action {
        color: var(--primary-600);

        & .fi-record-palette-row-title,
        & .fi-record-palette-row-icon {
            color: var(--primary-600);
        }

        &:where(.dark *) .fi-record-palette-row-title,
        &:where(.dark *) .fi-record-palette-row-icon {
            color: var(--primary-400);
        }
    }

    .fi-record-palette-row-quiet {
        margin-top: 0.25rem;

        & .fi-record-palette-row-title,
        & .fi-record-palette-row-icon {
            color: var(--gray-500);
            font-size: 0.8125rem;
        }

        &:where(.dark *) .fi-record-palette-row-title,
        &:where(.dark *) .fi-record-palette-row-icon {
            color: var(--gray-400);
        }
    }

    .fi-record-palette-icon-button {
        display: grid;
        flex: none;
        place-items: center;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 0.375rem;
        color: var(--gray-400);
        cursor: pointer;

        & svg {
            width: 1rem;
            height: 1rem;
        }

        &:hover {
            background-color: color-mix(in oklab, var(--gray-950) 5%, transparent);
            color: var(--gray-700);
        }

        &:where(.dark *):hover {
            background-color: color-mix(in oklab, white 8%, transparent);
            color: var(--gray-200);
        }
    }

    .fi-record-palette-notice {
        padding: 0.5rem 1rem;
        background-color: var(--gray-50);
        color: var(--gray-500);
        font-size: 0.75rem;
        line-height: 1rem;

        &:where(.dark *) {
            background-color: color-mix(in oklab, white 3%, transparent);
            color: var(--gray-400);
        }
    }

    .fi-record-palette-results {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1rem;
        flex: 0 1 auto;
        min-height: 0;
        max-height: min(55vh, 27.5rem);
        max-height: min(55dvh, 27.5rem);
        padding: 1rem 1rem 0.5rem;

        /* On a phone the window's own height bounds the list, which then scrolls inside it. */
        @media (max-width: 40rem) {
            max-height: none;
        }
        overflow-y: auto;
        overscroll-behavior: contain;

        &[hidden] {
            display: none;
        }
    }

    .fi-record-palette-group-heading {
        margin-bottom: 0.5rem;
        color: var(--gray-900);
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1rem;

        &:where(.dark *) {
            color: var(--gray-100);
        }
    }

    .fi-record-palette-rows {
        margin: 0 -0.5rem;
    }

    .fi-record-palette-more {
        margin: 0 -0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--gray-100);

        &:where(.dark *) {
            border-top-color: color-mix(in oklab, var(--gray-500) 20%, transparent);
        }
    }

    /* The options that widen the search: one short line on a wide screen, rows on a narrow one. */
    @media (min-width: 40rem) {
        .fi-record-palette-widen {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.25rem 0.5rem;

            & .fi-record-palette-row {
                width: auto;
                min-height: 0;
                gap: 0.375rem;
                padding: 0.25rem 0.5rem;
                font-size: 0.8125rem;
            }

            & .fi-record-palette-row-icon {
                width: 1rem;
                height: 1rem;
                margin: 0;
            }

            & .fi-record-palette-row-main {
                display: flex;
                align-items: baseline;
                gap: 0.5rem;
            }

            & .fi-record-palette-row-details {
                display: inline !important;
            }
        }
    }

    .fi-record-palette-row-ctn {
        position: relative;
    }

    .fi-record-palette-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
        min-height: 2.5rem;
        padding: 0.5rem;
        border-radius: 0.375rem;
        text-align: start;
        cursor: pointer;

        &.fi-selected {
            background-color: color-mix(in oklab, var(--gray-950) 5%, transparent);
        }

        &.fi-selected:where(.dark *) {
            background-color: color-mix(in oklab, white 6%, transparent);
        }

        .fi-record-palette-row-ctn:has(.fi-record-palette-related) & {
            padding-inline-end: 2.5rem;
        }
    }

    .fi-record-palette-row-icon {
        flex: none;
        width: 1.5rem;
        height: 1.5rem;
        opacity: 0.8;
    }

    .fi-record-palette-row-image {
        flex: none;
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        object-fit: cover;
        box-shadow: 0 0 0 1px color-mix(in oklab, var(--gray-950) 5%, transparent);

        &:where(.dark *) {
            box-shadow: 0 0 0 1px color-mix(in oklab, white 10%, transparent);
        }
    }

    .fi-record-palette-row-main {
        flex: 1;
        min-width: 0;
    }

    .fi-record-palette-row-title,
    .fi-record-palette-row-details,
    .fi-record-palette-row-hint {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .fi-record-palette-row-title {
        color: var(--gray-900);

        &:where(.dark *) {
            color: var(--gray-100);
        }
    }

    .fi-record-palette-row-details,
    .fi-record-palette-row-hint {
        color: var(--gray-500);
        font-size: 0.75rem;
        line-height: 1rem;

        &:where(.dark *) {
            color: var(--gray-400);
        }
    }

    .fi-record-palette-row-details {
        margin-top: 0.0625rem;

        & b {
            font-weight: 500;
        }
    }

    .fi-record-palette-row-hint {
        flex: 0 1 46%;
        min-width: 0;
        text-align: end;

        @media (max-width: 40rem) {
            display: none;
        }
    }

    /* The slots of a row a resource describes with table columns: the columns keep their own markup, the palette only sets the scale. */
    .fi-record-palette-row-leading {
        display: grid;
        flex: none;
        place-items: center;
        width: 2rem;
        min-height: 2rem;

        & .fi-ta-col {
            display: grid;
            place-items: center;
        }
    }

    .fi-record-palette-row-above,
    .fi-record-palette-row-below {
        color: var(--gray-500);
        font-size: 0.75rem;
        line-height: 1rem;

        &:where(.dark *) {
            color: var(--gray-400);
        }
    }

    .fi-record-palette-row-below {
        margin-top: 0.0625rem;
    }

    .fi-record-palette-row-heading {
        align-items: center;
        gap: 0.5rem;
        min-width: 0;

        & > .fi-record-palette-row-title {
            flex: 0 1 auto;
            min-width: 0;
        }
    }

    .fi-record-palette-row-beside {
        flex: none;
    }

    .fi-record-palette-row-trailing {
        flex: none;
        max-width: 40%;
        color: var(--gray-500);
        font-size: 0.75rem;
        line-height: 1rem;
        text-align: end;

        &:where(.dark *) {
            color: var(--gray-400);
        }

        @media (max-width: 48rem) {
            flex: 1 0 100%;
            max-width: none;
            order: 3;
            text-align: start;
        }
    }

    /*
     * On a narrow screen the end of the row moves under the title, into the title's own column: a grid of the leading
     * part (when there is one) and one column for everything else, so the moved lines start exactly where the title does.
     */
    .fi-record-palette-row-slotted {
        @media (max-width: 48rem) {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            align-items: start;
            row-gap: 0.125rem;

            &:has(> .fi-record-palette-row-leading, > .fi-record-palette-row-icon, > .fi-record-palette-row-image) {
                grid-template-columns: auto minmax(0, 1fr);
            }

            & > .fi-record-palette-row-leading,
            & > .fi-record-palette-row-icon,
            & > .fi-record-palette-row-image {
                grid-row: 1 / span 3;
            }

            & > .fi-record-palette-row-main,
            & > .fi-record-palette-row-trailing,
            & > .fi-record-palette-row-hint {
                grid-column: -2;
            }
        }
    }

    .fi-record-palette-row-above,
    .fi-record-palette-row-beside,
    .fi-record-palette-row-below,
    .fi-record-palette-row-trailing {
        & .fi-ta-col,
        & .fi-ta-text,
        & .fi-ta-split,
        & .fi-ta-stack {
            min-width: 0;
        }

        /* A column in a table cell is padded by the cell and by its own wrapper: here the text starts where the title starts. */
        & .fi-ta-col,
        & .fi-ta-text:not(.fi-inline),
        & .fi-ta-icon:not(.fi-inline) {
            padding: 0;
        }

        & .fi-ta-text-item {
            color: inherit;
            font-size: inherit;
            line-height: inherit;
        }

        & .fi-ta-split {
            align-items: center;
            gap: 0.25rem 0.5rem;
        }

        & .fi-ta-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        & .fi-badge {
            padding-block: 0;
            font-size: 0.6875rem;
            line-height: 1.25rem;
        }
    }

    .fi-record-palette-row-beside .fi-ta-text-item {
        font-size: 0.75rem;
    }

    .fi-record-palette-related {
        position: absolute;
        top: 50%;
        inset-inline-end: 0.5rem;
        transform: translateY(-50%);
        opacity: 0;

        .fi-record-palette-row-ctn:hover &,
        .fi-record-palette-row.fi-selected + &,
        &:focus-visible {
            opacity: 1;
        }

        @media (hover: none), (max-width: 40rem) {
            opacity: 1;
        }
    }

    .fi-record-palette-empty {
        padding: 1rem 0;

        @media (max-width: 40rem) {
            padding: 0;
        }
    }

    .fi-record-palette-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.625rem 1rem;
        background-color: var(--gray-50);

        &:where(.dark *) {
            background-color: var(--gray-800);
        }

        @media (hover: none), (max-width: 40rem) {
            display: none;
        }
    }

    .fi-record-palette-keys {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .fi-record-palette-key {
        display: flex;
        align-items: center;
        gap: 0.5rem;

        & kbd + kbd {
            margin-inline-start: -0.25rem;
        }

        & svg {
            width: 0.75rem;
            height: 0.75rem;
        }
    }

    .fi-record-palette-footer-hint {
        color: var(--gray-500);

        &:where(.dark *) {
            color: var(--gray-400);
        }
    }
</style>
