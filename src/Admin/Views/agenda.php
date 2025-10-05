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
    'arrivi-oggi'  => [
        'label' => __('In arrivo oggi', 'fp-restaurant-reservations'),
        'url'   => add_query_arg('tab', 'arrivi-oggi', $baseUrl),
    ],
    'settimana'    => [
        'label' => __('Arrivi settimana', 'fp-restaurant-reservations'),
        'url'   => add_query_arg('tab', 'settimana', $baseUrl),
    ],
    'agenda'       => [
        'label' => __('Calendario', 'fp-restaurant-reservations'),
        'url'   => $baseUrl,
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
                <section class="fp-resv-calendar" data-role="calendar" hidden>
                    <header class="fp-resv-calendar__toolbar">
                        <div class="fp-resv-calendar__nav">
                            <button type="button" class="button" data-action="agenda-prev">
                                <?php esc_html_e('Giorno precedente', 'fp-restaurant-reservations'); ?>
                            </button>
                            <button type="button" class="button" data-action="agenda-today">
                                <?php esc_html_e('Oggi', 'fp-restaurant-reservations'); ?>
                            </button>
                            <button type="button" class="button" data-action="agenda-next">
                                <?php esc_html_e('Giorno successivo', 'fp-restaurant-reservations'); ?>
                            </button>
                        </div>
                        <div class="fp-resv-calendar__filters">
                            <label>
                                <span class="screen-reader-text"><?php esc_html_e('Seleziona giorno', 'fp-restaurant-reservations'); ?></span>
                                <input type="date" data-role="agenda-date" />
                            </label>
                            <label>
                                <span class="screen-reader-text"><?php esc_html_e('Filtra sala', 'fp-restaurant-reservations'); ?></span>
                                <select data-role="agenda-room"></select>
                            </label>
                            <label>
                                <span class="screen-reader-text"><?php esc_html_e('Seleziona vista', 'fp-restaurant-reservations'); ?></span>
                                <select data-role="agenda-view">
                                    <option value="day"><?php esc_html_e('Giorno', 'fp-restaurant-reservations'); ?></option>
                                    <option value="week"><?php esc_html_e('Settimana', 'fp-restaurant-reservations'); ?></option>
                                </select>
                            </label>
                            <button type="button" class="button button-primary" data-action="agenda-create">
                                <?php esc_html_e('Nuova prenotazione', 'fp-restaurant-reservations'); ?>
                            </button>
                        </div>
                    </header>
                    <div class="fp-resv-calendar__body" data-role="agenda-body">
                        <div class="fp-resv-calendar__summary">
                            <h2 class="fp-resv-admin__title" data-role="agenda-title"></h2>
                            <p class="fp-resv-calendar__hint" data-role="agenda-hint"></p>
                        </div>
                        <p class="fp-resv-calendar__empty" data-role="agenda-empty" hidden></p>
                        <div class="fp-resv-calendar__grid" data-role="agenda-grid"></div>
                    </div>
                </section>
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
				<!-- Modal: Nuova prenotazione -->
				<div class="fp-resv-modal" data-modal="create" hidden aria-hidden="true" role="dialog" aria-labelledby="fp-resv-create-title">
					<div class="fp-resv-modal__backdrop" data-action="modal-close" aria-hidden="true"></div>
					<div class="fp-resv-modal__dialog">
						<header class="fp-resv-modal__header">
							<h2 id="fp-resv-create-title" class="fp-resv-admin__title"><?php esc_html_e('Nuova prenotazione', 'fp-restaurant-reservations'); ?></h2>
							<button type="button" class="button-link" data-action="modal-close" aria-label="<?php esc_attr_e('Chiudi', 'fp-restaurant-reservations'); ?>">×</button>
						</header>
						<div class="fp-resv-modal__body">
							<div class="fp-resv-form__row">
								<label>
									<span><?php esc_html_e('Data', 'fp-restaurant-reservations'); ?></span>
									<input type="date" data-field="date" required>
								</label>
								<label>
									<span><?php esc_html_e('Ora', 'fp-restaurant-reservations'); ?></span>
									<input type="time" data-field="time" step="900" required>
								</label>
							</div>
							<div class="fp-resv-form__row">
								<label>
									<span><?php esc_html_e('Persone', 'fp-restaurant-reservations'); ?></span>
									<input type="number" min="1" step="1" data-field="party" required>
								</label>
								<div class="fp-resv-quickparty">
									<button type="button" class="button" data-quickparty="2">2</button>
									<button type="button" class="button" data-quickparty="4">4</button>
									<button type="button" class="button" data-quickparty="6">6</button>
									<button type="button" class="button" data-quickparty="8">8</button>
								</div>
							</div>
							<div class="fp-resv-form__row">
								<label>
									<span><?php esc_html_e('Nome', 'fp-restaurant-reservations'); ?></span>
									<input type="text" data-field="first_name" required>
								</label>
								<label>
									<span><?php esc_html_e('Cognome', 'fp-restaurant-reservations'); ?></span>
									<input type="text" data-field="last_name">
								</label>
							</div>
							<div class="fp-resv-form__row">
								<label>
									<span><?php esc_html_e('Sala (opzionale)', 'fp-restaurant-reservations'); ?></span>
									<input type="number" min="1" step="1" data-field="room_id">
								</label>
								<label>
									<span><?php esc_html_e('Tavolo (opzionale)', 'fp-restaurant-reservations'); ?></span>
									<input type="number" min="1" step="1" data-field="table_id">
								</label>
							</div>
							<div class="fp-resv-form__row">
								<label class="fp-resv-form__full">
									<span><?php esc_html_e('Note', 'fp-restaurant-reservations'); ?></span>
									<textarea rows="2" data-field="notes"></textarea>
								</label>
							</div>
							<p class="fp-resv-form__error" data-role="create-error" hidden></p>
						</div>
						<footer class="fp-resv-modal__footer">
							<button type="button" class="button" data-action="modal-close"><?php esc_html_e('Annulla', 'fp-restaurant-reservations'); ?></button>
							<button type="button" class="button button-primary" data-action="create-submit"><?php esc_html_e('Crea prenotazione', 'fp-restaurant-reservations'); ?></button>
						</footer>
					</div>
				</div>
            </div>
        </section>
    </main>
</div>
