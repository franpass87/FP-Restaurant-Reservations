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

// Token colore / tipografia da Impostazioni → Style::buildFrontend (stesso meccanismo anteprima admin).
$style = $context['style'] ?? null;
if (is_array($style) && !empty($style['css']) && is_string($style['css'])) {
    echo "\n" . '<style id="fp-resv-form-tokens" type="text/css">' . "\n";
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS generato lato server da StyleCssGenerator
    echo $style['css'];
    echo "\n" . '</style>' . "\n";
}

include __DIR__ . '/form-simple.php';
