<?php
/**
 * Frontend reservation form.
 *
 * Delega interamente al template form-simple.php.
 *
 * @var array<string, mixed> $context
 */

if (!isset($context) || !is_array($context)) {
    return;
}

include __DIR__ . '/form-simple.php';
