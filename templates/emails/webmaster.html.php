<?php
/**
 * @var array<string, mixed> $reservation
 * @var array<string, mixed> $strings
 */

if (!isset($reservation) || !is_array($reservation)) {
    return;
}

$customer   = $reservation['customer'] ?? [];
$restaurant = $reservation['restaurant'] ?? [];
$utm        = $reservation['utm'] ?? [];
$createdAt  = $reservation['created_at'] ?? null;
$strings    = isset($strings) && is_array($strings) ? $strings : [];
$labels     = is_array($strings['labels'] ?? null) ? $strings['labels'] : [];

$headline = (string) ($strings['headline_webmaster'] ?? __('Copia notifica prenotazione', 'fp-restaurant-reservations'));
$lead     = (string) ($strings['lead_webmaster'] ?? __('Il sistema ha registrato una prenotazione per %s.', 'fp-restaurant-reservations'));

$label = static function (array $labels, string $key, string $fallback): string {
    return isset($labels[$key]) ? (string) $labels[$key] : $fallback;
};

$datetimeDisplay = (string) ($reservation['datetime_formatted'] ?? sprintf(
    '%s • %s',
    (string) ($reservation['date'] ?? ''),
    (string) ($reservation['time'] ?? '')
));

if ($createdAt instanceof \DateTimeImmutable) {
    $createdAtFormatted = $reservation['created_at_formatted'] ?? $createdAt->format('d/m/Y H:i');
} else {
    $createdAtFormatted = $reservation['created_at_formatted'] ?? '';
}
?>

<h1 style="margin:0 0 16px;font-family:Arial,sans-serif;font-size:20px;color:#111;">
    <?php echo esc_html($headline); ?>
</h1>

<p style="margin:0 0 12px;font-family:Arial,sans-serif;font-size:15px;color:#333;">
    <?php printf(esc_html($lead), esc_html((string) ($restaurant['name'] ?? ''))); ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:14px;color:#222;">
    <tbody>
        <tr>
            <th align="left" style="padding:4px 8px;font-weight:bold;">
                <?php echo esc_html($label($labels, 'reservation_id', __('ID prenotazione', 'fp-restaurant-reservations'))); ?>
            </th>
            <td style="padding:4px 8px;">
                <?php echo esc_html('#' . (string) ($reservation['id'] ?? '')); ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="padding:4px 8px;font-weight:bold;">
                <?php echo esc_html($label($labels, 'date_time', __('Data e ora', 'fp-restaurant-reservations'))); ?>
            </th>
            <td style="padding:4px 8px;">
                <?php echo esc_html($datetimeDisplay); ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="padding:4px 8px;font-weight:bold;">
                <?php echo esc_html($label($labels, 'party', __('Coperti', 'fp-restaurant-reservations'))); ?>
            </th>
            <td style="padding:4px 8px;">
                <?php echo esc_html((string) ($reservation['party'] ?? '')); ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="padding:4px 8px;font-weight:bold;">
                <?php echo esc_html($label($labels, 'customer', __('Cliente', 'fp-restaurant-reservations'))); ?>
            </th>
            <td style="padding:4px 8px;">
                <?php echo esc_html(trim((string) ($customer['first_name'] ?? '') . ' ' . (string) ($customer['last_name'] ?? ''))); ?>
                <br>
                <a href="mailto:<?php echo esc_attr((string) ($customer['email'] ?? '')); ?>" style="color:#005f73;">
                    <?php echo esc_html((string) ($customer['email'] ?? '')); ?>
                </a>
                <?php if (!empty($customer['phone'])) : ?>
                    <br>
                    <a href="tel:<?php echo esc_attr((string) $customer['phone']); ?>" style="color:#005f73;">
                        <?php echo esc_html((string) $customer['phone']); ?>
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php if (!empty($reservation['location'])) : ?>
            <tr>
                <th align="left" style="padding:4px 8px;font-weight:bold;">
                    <?php echo esc_html($label($labels, 'location', __('Sede / Location', 'fp-restaurant-reservations'))); ?>
                </th>
                <td style="padding:4px 8px;">
                    <?php echo esc_html((string) $reservation['location']); ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($reservation['notes'])) : ?>
            <tr>
                <th align="left" style="padding:4px 8px;font-weight:bold;vertical-align:top;">
                    <?php echo esc_html($label($labels, 'notes', __('Note', 'fp-restaurant-reservations'))); ?>
                </th>
                <td style="padding:4px 8px;">
                    <?php echo nl2br(esc_html((string) $reservation['notes'])); ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($reservation['allergies'])) : ?>
            <tr>
                <th align="left" style="padding:4px 8px;font-weight:bold;vertical-align:top;">
                    <?php echo esc_html($label($labels, 'allergies', __('Allergie', 'fp-restaurant-reservations'))); ?>
                </th>
                <td style="padding:4px 8px;">
                    <?php echo nl2br(esc_html((string) $reservation['allergies'])); ?>
                </td>
            </tr>
        <?php endif; ?>
        <tr>
            <th align="left" style="padding:4px 8px;font-weight:bold;">
                <?php echo esc_html($label($labels, 'status', __('Stato', 'fp-restaurant-reservations'))); ?>
            </th>
            <td style="padding:4px 8px;">
                <?php echo esc_html((string) ($reservation['status_label'] ?? $reservation['status'] ?? '')); ?>
            </td>
        </tr>
        <?php if ($createdAtFormatted !== '') : ?>
            <tr>
                <th align="left" style="padding:4px 8px;font-weight:bold;">
                    <?php echo esc_html($label($labels, 'recorded_at', __('Registrata il', 'fp-restaurant-reservations'))); ?>
                </th>
                <td style="padding:4px 8px;">
                    <?php echo esc_html($createdAtFormatted); ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($utm['source']) || !empty($utm['medium']) || !empty($utm['campaign'])) : ?>
            <tr>
                <th align="left" style="padding:4px 8px;font-weight:bold;vertical-align:top;">
                    <?php echo esc_html($label($labels, 'utm', __('Attribution / UTM', 'fp-restaurant-reservations'))); ?>
                </th>
                <td style="padding:4px 8px;">
                    <?php if (!empty($utm['source'])) : ?>
                        <div><?php printf(esc_html((string) ($labels['utm_source'] ?? __('Sorgente: %s', 'fp-restaurant-reservations'))), esc_html((string) $utm['source'])); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($utm['medium'])) : ?>
                        <div><?php printf(esc_html((string) ($labels['utm_medium'] ?? __('Mezzo: %s', 'fp-restaurant-reservations'))), esc_html((string) $utm['medium'])); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($utm['campaign'])) : ?>
                        <div><?php printf(esc_html((string) ($labels['utm_campaign'] ?? __('Campagna: %s', 'fp-restaurant-reservations'))), esc_html((string) $utm['campaign'])); ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<p style="margin:16px 0 0;font-family:Arial,sans-serif;font-size:14px;color:#333;">
    <a href="<?php echo esc_url((string) ($reservation['manage_url'] ?? '')); ?>" style="color:#005f73;">
        <?php echo esc_html($label($labels, 'open', __('Apri la scheda prenotazione', 'fp-restaurant-reservations'))); ?>
    </a>
</p>
