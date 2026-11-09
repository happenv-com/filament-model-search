<style>
    [data-fi-modal-id='{{ \Happenv\FilamentModelSearch\Livewire\RecordPalette::MODAL_ID }}'] {
        & > .fi-modal-close-overlay {
            background-color: color-mix(in oklab, var(--gray-500) 25%, transparent);
            backdrop-filter: blur(4px);
        }

        &:where(.dark *) > .fi-modal-close-overlay {
            background-color: color-mix(in oklab, var(--gray-950) 45%, transparent);
        }

        & > .fi-modal-window-ctn {
            grid-template-rows: clamp(0.75rem, 8vh, 5rem) auto 1fr;
            align-content: start;
        }

        & > .fi-modal-window-ctn > .fi-modal-window {
            overflow: hidden;
            border-radius: 0.75rem;
        }

        & .fi-modal-content {
            padding: 0;
            gap: 0;
        }
    }

    .fi-record-palette {
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
        max-height: min(55vh, 27.5rem);
        max-height: min(55dvh, 27.5rem);
        padding: 1rem 1rem 0.5rem;
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
