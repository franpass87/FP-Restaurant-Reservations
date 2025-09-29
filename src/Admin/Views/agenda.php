<?php
/**
 * Admin agenda container.
 */
?>
<?php
$activeTab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : '';
if ($activeTab === '') {
    $activeTab = 'agenda';
}

$baseUrl = admin_url('admin.php?page=fp-resv-agenda');

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
<div class="wrap fp-resv-agenda-wrap">
    <h1><?php esc_html_e('Agenda prenotazioni', 'fp-restaurant-reservations'); ?></h1>
    <p class="description">
        <?php esc_html_e('Gestisci prenotazioni con drag & drop e quick edit direttamente da questa interfaccia.', 'fp-restaurant-reservations'); ?>
    </p>

    <h2 class="nav-tab-wrapper">
        <?php foreach ($tabs as $tabKey => $tabData) : ?>
            <a
                href="<?php echo esc_url($tabData['url']); ?>"
                class="nav-tab <?php echo $activeTab === $tabKey ? 'nav-tab-active' : ''; ?>"
            >
                <?php echo esc_html($tabData['label']); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <div id="fp-resv-agenda-app" class="fp-resv-agenda-app" data-fp-resv-agenda>
        <section class="fp-resv-arrivals" data-role="arrivals" hidden>
            <header class="fp-resv-arrivals__header">
                <h2 data-role="arrivals-title"></h2>
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
</div>
