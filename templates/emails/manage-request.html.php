<?php
/**
 * @var array<string,mixed> $context
 * @var array<string,mixed> $strings
 */

$labels = is_array($strings['staff']['labels'] ?? null) ? $strings['staff']['labels'] : [];

$action = (string) ($context['request']['action_label'] ?? '');
$desired = (string) ($context['request']['desired_time'] ?? '');
$userNote = (string) ($context['request']['user_note'] ?? '');
?>

<h1 style="margin:0 0 16px;font-family:Arial,sans-serif;font-size:20px;color:#111;">
    <?php echo esc_html($action); ?>
    <?php if (!empty($context['id'])) : ?>
        <small style="font-weight:normal;color:#6b7280;">#<?php echo (int) $context['id']; ?></small>
    <?php endif; ?>
</h1>

<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:14px;color:#222;">
    <tbody>
        <tr>
            <th align="left" style="padding:4px 8px;font-weight:bold;"><?php echo esc_html($labels['date_time'] ?? 'Data e ora'); ?></th>
            <td style="padding:4px 8px;"><?php echo esc_html(($context['date_formatted'] ?? $context['date'] ?? '') . ' • ' . ($context['time_formatted'] ?? $context['time'] ?? '')); ?></td>
        </tr>
        <tr>
            <th align="left" style="padding:4px 8px;font-weight:bold;"><?php echo esc_html($labels['party'] ?? 'Coperti'); ?></th>
            <td style="padding:4px 8px;"><?php echo esc_html((string) ($context['party'] ?? '')); ?></td>
        </tr>
        <tr>
            <th align="left" style="padding:4px 8px;font-weight:bold;">Azione</th>
            <td style="padding:4px 8px;"><?php echo esc_html($action); ?></td>
        </tr>
        <?php if ($desired !== '') : ?>
            <tr>
                <th align="left" style="padding:4px 8px;font-weight:bold;">Nuovo orario desiderato</th>
                <td style="padding:4px 8px;"><?php echo esc_html($desired); ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($userNote !== '') : ?>
            <tr>
                <th align="left" style="padding:4px 8px;font-weight:bold;">Nota cliente</th>
                <td style="padding:4px 8px;"><?php echo nl2br(esc_html($userNote)); ?></td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>


