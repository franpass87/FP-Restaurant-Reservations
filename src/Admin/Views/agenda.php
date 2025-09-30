<?php
/**
 * Admin agenda container.
 */

$activeTab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : '';
if ($activeTab === '') {
    $activeTab = 'agenda';
}

$baseUrl     = admin_url('admin.php?page=fp-resv-agenda');
$settingsUrl = admin_url('admin.php?page=fp-resv-settings');
$headingId   = 'fp-resv-agenda-title';

$tabs = [
    'agenda'       => [
        'label' => __('Calendario', 'fp-restaurant-reservations'),
        'url'   => $baseUrl,
    ],
    'arrivi-oggi'  => [
        'label' => __('In arrivo oggi', 'fp-restaurant-reservations'),
        'url'   => add_query_arg('tab', 'arrivi-oggi', $baseUrl),
    ],
    'settimana'    => [
        'label' => __('Arrivi settimana', 'fp-restaurant-reservations'),
        'url'   => add_query_arg('tab', 'settimana', $baseUrl),
    ],
];
?>
<div class="fp-resv-admin fp-resv-admin--agenda" role="region" aria-labelledby="<?php echo esc_attr($headingId); ?>">
    <header class="fp-resv-admin__topbar">
        <div class="fp-resv-admin__identity">
            <nav class="fp-resv-admin__breadcrumbs" aria-label="<?php esc_attr_e('Percorso', 'fp-restaurant-reservations'); ?>">
                <a href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('FP Reservations', 'fp-restaurant-reservations'); ?></a>
                <span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>
                <span class="fp-resv-admin__breadcrumb-current"><?php esc_html_e('Agenda', 'fp-restaurant-reservations'); ?></span>
            </nav>
            <div>
                <h1 class="fp-resv-admin__title" id="<?php echo esc_attr($headingId); ?>">
                    <?php esc_html_e('Agenda prenotazioni', 'fp-restaurant-reservations'); ?>
                </h1>
                <p class="fp-resv-admin__subtitle">
                    <?php esc_html_e('Gestisci prenotazioni con drag & drop, monitora gli arrivi imminenti e sincronizza rapidamente le modifiche.', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
        </div>
        <div class="fp-resv-admin__actions">
            <a class="button" href="<?php echo esc_url($settingsUrl); ?>">
                <?php esc_html_e('Impostazioni', 'fp-restaurant-reservations'); ?>
            </a>
            <a class="button button-primary" href="#fp-resv-agenda-app">
                <?php esc_html_e('Apri agenda interattiva', 'fp-restaurant-reservations'); ?>
            </a>
        </div>
    </header>

    <main class="fp-resv-admin__main">
        <section class="fp-resv-surface">
            <nav class="fp-resv-admin__tabs" aria-label="<?php esc_attr_e('Sezioni agenda', 'fp-restaurant-reservations'); ?>">
                <?php foreach ($tabs as $tabKey => $tabData) : ?>
                    <a
                        href="<?php echo esc_url($tabData['url']); ?>"
                        class="fp-resv-admin__tab <?php echo $activeTab === $tabKey ? 'is-active' : ''; ?>"
                    >
                        <?php echo esc_html($tabData['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div id="fp-resv-agenda-app" class="fp-resv-agenda-app" data-fp-resv-agenda>
                <section class="fp-resv-arrivals" data-role="arrivals" hidden>
                    <header class="fp-resv-arrivals__header">
                        <h2 class="fp-resv-admin__title" data-role="arrivals-title"></h2>
                        <div class="fp-resv-arrivals__filters">
                            <label>
                                <span class="screen-reader-text"><?php esc_html_e('Filtra per sala', 'fp-restaurant-reservations'); ?></span>
                                <input type="text" data-role="arrivals-room" placeholder="<?php esc_attr_e('ID sala', 'fp-restaurant-reservations'); ?>">
                            </label>
                            <label>
                                <span class="screen-reader-text"><?php esc_html_e('Filtra per stato', 'fp-restaurant-reservations'); ?></span>
                                <input type="text" data-role="arrivals-status" placeholder="<?php esc_attr_e('Stato', 'fp-restaurant-reservations'); ?>">
                            </label>
                            <button type="button" class="button" data-action="arrivals-reload">
                                <?php esc_html_e('Aggiorna', 'fp-restaurant-reservations'); ?>
                            </button>
                        </div>
                    </header>
                    <div class="fp-resv-arrivals__body" data-role="arrivals-body">
                        <p class="fp-resv-arrivals__empty" data-role="arrivals-empty" hidden></p>
                        <ul class="fp-resv-arrivals__list" data-role="arrivals-list"></ul>
                    </div>
                </section>
            </div>
        </section>
    </main>
</div>
