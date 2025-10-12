<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use function sprintf;

/**
 * Template CSS di base per il widget di prenotazione.
 * Estratto da Style.php per migliorare la manutenibilità.
 */
final class StyleCss
{
    /**
     * Genera il CSS base per il widget.
     *
     * @param string $scope Selettore CSS scope (es. '#fp-resv-123')
     * @return string CSS completo
     */
    public static function buildBaseCss(string $scope): string
    {
        $layout = <<<'CSS'
%s.fp-resv-widget {
    background: var(--fp-resv-surface);
    color: var(--fp-resv-text);
    border-radius: var(--fp-resv-radius);
    box-shadow: var(--fp-resv-shadow);
    border: 1px solid rgba(17, 25, 40, 0.04);
    display: flex;
    flex-direction: column;
    gap: clamp(1.25rem, 3vw, 2rem);
    padding: var(--fp-resv-spacing-lg, clamp(1.5rem, 1.35rem + 1vw, 2.5rem));
    width: 100%;
    max-width: min(100%, var(--fp-resv-max-width, 100%));
    margin-inline: auto;
    margin-block: var(--fp-resv-margin-block, 0);
    box-sizing: border-box;
}
%s .fp-resv-widget__topbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--fp-resv-divider);
}
%s .fp-resv-progress {
    box-sizing: border-box;
    width: 100%;
}
%s .fp-progress {
    --fp-progress-height: 6px;
    --fp-progress-fill: 0%;
    --fp-progress-gap: clamp(0.55rem, 1.4vw, 0.95rem);
    list-style: none;
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: var(--fp-progress-gap);
    padding: clamp(0.1rem, 0.45vw, 0.25rem) clamp(0.35rem, 1.6vw, 0.65rem);
    margin: 0;
    position: relative;
    isolation: isolate;
    counter-reset: fp-progress;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.35) transparent;
}
%s .fp-progress::-webkit-scrollbar { height: 5px; }
%s .fp-progress::-webkit-scrollbar-track { background: transparent; }
%s .fp-progress::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.35);
    border-radius: 3px;
}
%s .fp-progress::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    height: var(--fp-progress-height);
    background-color: var(--fp-resv-divider);
    border-radius: calc(var(--fp-progress-height) / 2);
    z-index: 0;
}
%s .fp-progress::after {
    content: '';
    position: absolute;
    left: 0;
    width: var(--fp-progress-fill);
    top: 50%;
    transform: translateY(-50%);
    height: var(--fp-progress-height);
    background-color: var(--fp-resv-primary);
    border-radius: calc(var(--fp-progress-height) / 2);
    z-index: 1;
    transition: width 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
%s .fp-progress__step {
    counter-increment: fp-progress;
    flex: 0 0 auto;
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    scroll-snap-align: center;
}
%s .fp-progress__step__icon {
    flex-shrink: 0;
    width: clamp(1.1rem, 2.6vw, 1.45rem);
    height: clamp(1.1rem, 2.6vw, 1.45rem);
    display: grid;
    place-items: center;
    background: var(--fp-resv-surface);
    border: 2px solid var(--fp-resv-divider);
    border-radius: 50%;
    color: var(--fp-resv-muted);
    font-size: clamp(0.6rem, 1.25vw, 0.75rem);
    font-weight: 700;
    line-height: 1;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
%s .fp-progress__step__icon::before {
    content: counter(fp-progress);
}
%s .fp-progress__step__label {
    flex-shrink: 0;
    font-size: clamp(0.725rem, 1.5vw, 0.875rem);
    font-weight: 500;
    color: var(--fp-resv-muted);
    transition: color 0.2s;
    white-space: nowrap;
}
%s .fp-progress__step.is-active .fp-progress__step__icon,
%s .fp-progress__step.is-completed .fp-progress__step__icon {
    border-color: var(--fp-resv-primary);
    background: var(--fp-resv-primary);
    color: white;
}
%s .fp-progress__step.is-active .fp-progress__step__label {
    color: var(--fp-resv-text);
    font-weight: 600;
}
%s .fp-progress__step.is-completed .fp-progress__step__icon::before {
    content: '✓';
}
%s .fp-resv-widget__content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: clamp(1rem, 2.2vw, 1.75rem);
}
%s .fp-resv-widget__footer {
    display: flex;
    flex-wrap: wrap;
    gap: var(--fp-resv-spacing-sm, 0.625rem);
    justify-content: flex-end;
    align-items: center;
    padding-top: var(--fp-resv-spacing-md, 1rem);
    border-top: 1px solid var(--fp-resv-divider);
}
%s button,
%s select {
    font-family: inherit;
    font-size: 100%;
    line-height: 1.15;
    margin: 0;
    box-sizing: border-box;
}
%s button,
%s [type="button"],
%s [type="reset"],
%s [type="submit"] {
    -webkit-appearance: button;
}
%s button::-moz-focus-inner,
%s [type="button"]::-moz-focus-inner,
%s [type="reset"]::-moz-focus-inner,
%s [type="submit"]::-moz-focus-inner {
    border-style: none;
    padding: 0;
}
%s button:-moz-focusring,
%s [type="button"]:-moz-focusring,
%s [type="reset"]:-moz-focusring,
%s [type="submit"]:-moz-focusring {
    outline: 1px dotted ButtonText;
}
%s .fp-btn {
    --btn-gap: clamp(0.45rem, 0.7vw, 0.625rem);
    --btn-radius: var(--fp-resv-radius);
    --btn-focus-ring: 0 0 0 var(--fp-resv-focus-ring) var(--fp-resv-primary-alpha, rgba(187, 38, 73, 0.22));
    --btn-transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--btn-gap);
    padding: var(--fp-resv-spacing-sm, 0.625rem) var(--fp-resv-spacing-md, 1rem);
    border-radius: var(--btn-radius);
    font-weight: 600;
    font-size: clamp(0.875rem, 1.7vw, 1rem);
    line-height: 1.3;
    text-align: center;
    text-decoration: none;
    white-space: nowrap;
    border: none;
    cursor: pointer;
    user-select: none;
    transition: var(--btn-transition);
    position: relative;
    overflow: hidden;
}
%s .fp-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: currentColor;
    opacity: 0;
    transition: opacity 0.2s;
    border-radius: inherit;
}
%s .fp-btn:hover::before {
    opacity: 0.08;
}
%s .fp-btn:active::before {
    opacity: 0.15;
}
%s .fp-btn:focus-visible {
    outline: none;
    box-shadow: var(--btn-focus-ring);
}
%s .fp-btn:disabled,
%s .fp-btn.is-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
%s .fp-btn-primary {
    background: var(--fp-resv-primary);
    color: white;
}
%s .fp-btn-secondary {
    background: var(--fp-resv-surface-secondary, var(--fp-resv-surface));
    color: var(--fp-resv-text);
    border: 1px solid var(--fp-resv-divider);
}
%s .fp-btn-ghost {
    background: transparent;
    color: var(--fp-resv-text);
}
%s .fp-btn-ghost:hover {
    background: rgba(17, 25, 40, 0.04);
}
%s .fp-btn-danger {
    background: var(--fp-resv-danger);
    color: white;
}
%s .fp-btn-lg {
    padding: var(--fp-resv-spacing-md, 1rem) var(--fp-resv-spacing-lg, 1.25rem);
    font-size: clamp(1rem, 1.9vw, 1.125rem);
}
%s .fp-btn-sm {
    padding: var(--fp-resv-spacing-xs, 0.5rem) var(--fp-resv-spacing-sm, 0.625rem);
    font-size: clamp(0.8125rem, 1.5vw, 0.875rem);
}
%s .fp-btn-icon {
    padding: var(--fp-resv-spacing-sm, 0.625rem);
    aspect-ratio: 1;
}
%s .fp-btn__icon {
    flex-shrink: 0;
    width: 1em;
    height: 1em;
}
%s .fp-btn__icon svg {
    display: block;
    width: 100%;
    height: 100%;
}
%s .fp-field {
    --field-radius: var(--fp-resv-radius);
    --field-focus-ring: 0 0 0 var(--fp-resv-focus-ring) var(--fp-resv-primary-alpha, rgba(187, 38, 73, 0.22));
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}
%s .fp-field__label {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
    font-weight: 600;
    font-size: clamp(0.8125rem, 1.55vw, 0.9375rem);
    color: var(--fp-resv-text);
}
%s .fp-field__label-required {
    color: var(--fp-resv-danger);
}
%s .fp-field__description {
    font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
    color: var(--fp-resv-muted);
    line-height: 1.4;
}
%s .fp-field__input,
%s .fp-field__textarea,
%s .fp-field__select {
    width: 100%;
    padding: var(--fp-resv-spacing-sm, 0.625rem) var(--fp-resv-spacing-md, 1rem);
    border: 1px solid var(--fp-resv-divider);
    border-radius: var(--field-radius);
    background: var(--fp-resv-surface);
    color: var(--fp-resv-text);
    font-family: inherit;
    font-size: clamp(0.875rem, 1.7vw, 1rem);
    line-height: 1.5;
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    box-sizing: border-box;
}
%s .fp-field__input::placeholder,
%s .fp-field__textarea::placeholder {
    color: var(--fp-resv-muted);
    opacity: 0.6;
}
%s .fp-field__input:focus,
%s .fp-field__textarea:focus,
%s .fp-field__select:focus {
    outline: none;
    border-color: var(--fp-resv-primary);
    box-shadow: var(--field-focus-ring);
}
%s .fp-field__input:disabled,
%s .fp-field__textarea:disabled,
%s .fp-field__select:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
%s .fp-field__error {
    font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
    color: var(--fp-resv-danger);
    display: none;
}
%s .fp-field.has-error .fp-field__error {
    display: block;
}
%s .fp-field.has-error .fp-field__input,
%s .fp-field.has-error .fp-field__textarea,
%s .fp-field.has-error .fp-field__select {
    border-color: var(--fp-resv-danger);
}
%s .fp-field__textarea {
    min-height: 5rem;
    resize: vertical;
}
%s .fp-field-group {
    display: grid;
    gap: var(--fp-resv-spacing-md, 1rem);
}
%s .fp-field-group--2col {
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr));
}
%s .fp-field-radio,
%s .fp-field-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}
%s .fp-field-radio__input,
%s .fp-field-checkbox__input {
    flex-shrink: 0;
    width: 1.125rem;
    height: 1.125rem;
    margin: 0;
    accent-color: var(--fp-resv-primary);
    cursor: pointer;
}
%s .fp-field-radio__label,
%s .fp-field-checkbox__label {
    font-size: clamp(0.875rem, 1.7vw, 1rem);
    color: var(--fp-resv-text);
    cursor: pointer;
}
%s .fp-card {
    background: var(--fp-resv-surface);
    border: 1px solid var(--fp-resv-divider);
    border-radius: var(--fp-resv-radius);
    padding: var(--fp-resv-spacing-lg, clamp(1rem, 2vw, 1.5rem));
    transition: box-shadow 0.2s;
}
%s .fp-card--interactive {
    cursor: pointer;
}
%s .fp-card--interactive:hover {
    box-shadow: var(--fp-resv-shadow);
}
%s .fp-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: var(--fp-resv-spacing-md, 1rem);
}
%s .fp-card__title {
    font-size: clamp(1rem, 1.9vw, 1.25rem);
    font-weight: 600;
    color: var(--fp-resv-text);
    margin: 0;
}
%s .fp-card__body {
    display: flex;
    flex-direction: column;
    gap: var(--fp-resv-spacing-sm, 0.625rem);
}
%s .fp-meal-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 10rem), 1fr));
    gap: var(--fp-resv-spacing-md, 1rem);
}
%s .fp-meal-option {
    --meal-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: var(--fp-resv-spacing-md, 1rem);
    border: 2px solid var(--fp-resv-divider);
    border-radius: var(--fp-resv-radius);
    background: var(--fp-resv-surface);
    cursor: pointer;
    transition: var(--meal-transition);
    user-select: none;
}
%s .fp-meal-option:hover {
    border-color: var(--fp-resv-primary);
    box-shadow: var(--fp-resv-shadow);
}
%s .fp-meal-option.is-active {
    border-color: var(--fp-resv-primary);
    background: var(--fp-resv-primary-bg, rgba(187, 38, 73, 0.04));
}
%s .fp-meal-option.is-disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
%s .fp-meal-option.is-disabled:hover {
    border-color: var(--fp-resv-divider);
    box-shadow: none;
}
%s .fp-meal-option__badge {
    position: absolute;
    top: -0.5rem;
    right: -0.5rem;
    padding: 0.25rem 0.5rem;
    background: var(--fp-resv-primary);
    color: white;
    font-size: 0.6875rem;
    font-weight: 700;
    border-radius: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}
%s .fp-meal-option__label {
    font-weight: 600;
    font-size: clamp(0.9375rem, 1.8vw, 1.0625rem);
    color: var(--fp-resv-text);
}
%s .fp-meal-option__hint {
    font-size: clamp(0.8125rem, 1.55vw, 0.875rem);
    color: var(--fp-resv-muted);
    line-height: 1.4;
}
%s .fp-meal-option__price {
    font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
    color: var(--fp-resv-primary);
    font-weight: 600;
}
%s .fp-date-picker {
    --datepicker-cell-size: clamp(2.25rem, 6vw, 2.75rem);
    display: flex;
    flex-direction: column;
    gap: var(--fp-resv-spacing-md, 1rem);
}
%s .fp-date-picker__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
%s .fp-date-picker__title {
    font-size: clamp(1rem, 1.9vw, 1.125rem);
    font-weight: 600;
    color: var(--fp-resv-text);
}
%s .fp-date-picker__nav {
    display: flex;
    gap: 0.5rem;
}
%s .fp-date-picker__calendar {
    width: 100%;
}
%s .fp-date-picker__weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}
%s .fp-date-picker__weekday {
    text-align: center;
    font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
    font-weight: 600;
    color: var(--fp-resv-muted);
    padding: 0.25rem;
}
%s .fp-date-picker__days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.25rem;
}
%s .fp-date-picker__day {
    aspect-ratio: 1;
    display: grid;
    place-items: center;
    font-size: clamp(0.875rem, 1.7vw, 0.9375rem);
    font-weight: 500;
    color: var(--fp-resv-text);
    border-radius: calc(var(--fp-resv-radius) * 0.75);
    cursor: pointer;
    transition: all 0.15s;
    position: relative;
}
%s .fp-date-picker__day:hover {
    background: rgba(17, 25, 40, 0.04);
}
%s .fp-date-picker__day.is-today {
    background: var(--fp-resv-accent-bg, rgba(240, 180, 41, 0.12));
    color: var(--fp-resv-accent);
    font-weight: 700;
}
%s .fp-date-picker__day.is-selected {
    background: var(--fp-resv-primary);
    color: white;
    font-weight: 700;
}
%s .fp-date-picker__day.is-disabled {
    opacity: 0.3;
    cursor: not-allowed;
}
%s .fp-date-picker__day.is-disabled:hover {
    background: transparent;
}
%s .fp-date-picker__day.is-outside-month {
    color: var(--fp-resv-muted);
    opacity: 0.4;
}
%s .fp-time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 7.5rem), 1fr));
    gap: var(--fp-resv-spacing-sm, 0.625rem);
}
%s .fp-time-slot {
    padding: var(--fp-resv-spacing-sm, 0.625rem) var(--fp-resv-spacing-md, 1rem);
    border: 2px solid var(--fp-resv-divider);
    border-radius: var(--fp-resv-radius);
    background: var(--fp-resv-surface);
    font-weight: 600;
    font-size: clamp(0.875rem, 1.7vw, 1rem);
    text-align: center;
    cursor: pointer;
    transition: all 0.15s;
}
%s .fp-time-slot:hover {
    border-color: var(--fp-resv-primary);
    box-shadow: var(--fp-resv-shadow);
}
%s .fp-time-slot.is-active {
    border-color: var(--fp-resv-primary);
    background: var(--fp-resv-primary);
    color: white;
}
%s .fp-time-slot.is-disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
%s .fp-time-slot.is-disabled:hover {
    border-color: var(--fp-resv-divider);
    box-shadow: none;
}
%s .fp-party-selector {
    display: flex;
    align-items: center;
    gap: var(--fp-resv-spacing-md, 1rem);
    justify-content: center;
}
%s .fp-party-selector__value {
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 700;
    color: var(--fp-resv-text);
    min-width: 3rem;
    text-align: center;
}
%s .fp-alert {
    padding: var(--fp-resv-spacing-md, 1rem);
    border-radius: var(--fp-resv-radius);
    display: flex;
    gap: var(--fp-resv-spacing-sm, 0.625rem);
    align-items: flex-start;
}
%s .fp-alert__icon {
    flex-shrink: 0;
    width: 1.25rem;
    height: 1.25rem;
}
%s .fp-alert__content {
    flex: 1;
    font-size: clamp(0.8125rem, 1.55vw, 0.9375rem);
    line-height: 1.5;
}
%s .fp-alert--info {
    background: rgba(37, 99, 235, 0.08);
    color: #1e40af;
}
%s .fp-alert--success {
    background: rgba(29, 154, 108, 0.08);
    color: #166534;
}
%s .fp-alert--warning {
    background: rgba(245, 158, 11, 0.08);
    color: #92400e;
}
%s .fp-alert--danger {
    background: rgba(209, 69, 69, 0.08);
    color: #991b1b;
}
%s .fp-skeleton {
    background: linear-gradient(
        90deg,
        var(--fp-resv-divider) 25%,
        rgba(148, 163, 184, 0.15) 50%,
        var(--fp-resv-divider) 75%
    );
    background-size: 200% 100%;
    animation: fp-skeleton-loading 1.5s ease-in-out infinite;
    border-radius: var(--fp-resv-radius);
}
@keyframes fp-skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
%s .fp-skeleton--text {
    height: 1em;
    width: 100%;
}
%s .fp-skeleton--heading {
    height: 1.5em;
    width: 60%;
}
%s .fp-skeleton--circle {
    border-radius: 50%;
    width: 2.5rem;
    height: 2.5rem;
}
%s .fp-skeleton--button {
    height: 2.5rem;
    width: 8rem;
}
%s .fp-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.625rem;
    font-size: clamp(0.6875rem, 1.3vw, 0.75rem);
    font-weight: 600;
    border-radius: 0.375rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}
%s .fp-badge--primary {
    background: var(--fp-resv-primary-bg, rgba(187, 38, 73, 0.12));
    color: var(--fp-resv-primary);
}
%s .fp-badge--success {
    background: rgba(29, 154, 108, 0.12);
    color: #166534;
}
%s .fp-badge--warning {
    background: rgba(245, 158, 11, 0.12);
    color: #92400e;
}
%s .fp-badge--danger {
    background: rgba(209, 69, 69, 0.12);
    color: #991b1b;
}
%s .fp-divider {
    height: 1px;
    background: var(--fp-resv-divider);
    border: none;
    margin: var(--fp-resv-spacing-md, 1rem) 0;
}
%s .fp-spinner {
    --spinner-size: 2rem;
    --spinner-border: 0.25rem;
    width: var(--spinner-size);
    height: var(--spinner-size);
    border: var(--spinner-border) solid var(--fp-resv-divider);
    border-top-color: var(--fp-resv-primary);
    border-radius: 50%;
    animation: fp-spinner-rotation 0.8s linear infinite;
}
@keyframes fp-spinner-rotation {
    to { transform: rotate(360deg); }
}
%s .fp-spinner--sm {
    --spinner-size: 1.25rem;
    --spinner-border: 0.1875rem;
}
%s .fp-spinner--lg {
    --spinner-size: 3rem;
    --spinner-border: 0.375rem;
}
%s .fp-summary {
    display: flex;
    flex-direction: column;
    gap: var(--fp-resv-spacing-md, 1rem);
}
%s .fp-summary__item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
%s .fp-summary__label {
    font-size: clamp(0.875rem, 1.7vw, 1rem);
    color: var(--fp-resv-muted);
}
%s .fp-summary__value {
    font-size: clamp(0.9375rem, 1.8vw, 1.0625rem);
    font-weight: 600;
    color: var(--fp-resv-text);
}
%s .fp-summary__total {
    padding-top: var(--fp-resv-spacing-md, 1rem);
    border-top: 1px solid var(--fp-resv-divider);
}
%s .fp-summary__total .fp-summary__label {
    font-weight: 600;
    color: var(--fp-resv-text);
}
%s .fp-summary__total .fp-summary__value {
    font-size: clamp(1.125rem, 2.2vw, 1.5rem);
    color: var(--fp-resv-primary);
}
%s .fp-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    display: grid;
    place-items: center;
    z-index: 9999;
}
%s .fp-loading-overlay__content {
    background: var(--fp-resv-surface);
    padding: var(--fp-resv-spacing-lg, 1.5rem);
    border-radius: var(--fp-resv-radius);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--fp-resv-spacing-md, 1rem);
}
%s .fp-loading-overlay__text {
    font-size: clamp(0.875rem, 1.7vw, 1rem);
    color: var(--fp-resv-text);
}
%s .fp-phone-field {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: var(--fp-resv-spacing-sm, 0.625rem);
}
%s .fp-phone-field__prefix {
    min-width: 5rem;
}
%s .fp-manage-actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--fp-resv-spacing-md, 1rem);
}
%s .fp-manage-action {
    flex: 1 1 auto;
    min-width: min(100%, 12rem);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: var(--fp-resv-spacing-md, 1rem);
    border: 2px solid var(--fp-resv-divider);
    border-radius: var(--fp-resv-radius);
    background: var(--fp-resv-surface);
    cursor: pointer;
    transition: all 0.15s;
}
%s .fp-manage-action:hover {
    border-color: var(--fp-resv-primary);
    box-shadow: var(--fp-resv-shadow);
}
%s .fp-manage-action__title {
    font-weight: 600;
    font-size: clamp(0.9375rem, 1.8vw, 1.0625rem);
    color: var(--fp-resv-text);
}
%s .fp-manage-action__description {
    font-size: clamp(0.8125rem, 1.55vw, 0.875rem);
    color: var(--fp-resv-muted);
    line-height: 1.4;
}
CSS;

        // Use str_replace instead of sprintf to avoid issues with CSS values like "0.8s" being interpreted as format specifiers
        return str_replace('%s', $scope, $layout);
    }
}
