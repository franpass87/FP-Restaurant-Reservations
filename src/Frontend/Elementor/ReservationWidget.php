<?php

declare(strict_types=1);

namespace FP\Resv\Frontend\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use FP\Resv\Frontend\Shortcodes;
use function __;
use function is_string;

final class ReservationWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'fp_reservations_form';
    }

    public function get_title(): string
    {
        return __('Form Prenotazioni FP', 'fp-restaurant-reservations');
    }

    public function get_icon(): string
    {
        return 'eicon-calendar';
    }

    /**
     * @return array<int, string>
     */
    public function get_categories(): array
    {
        return ['general'];
    }

    /**
     * @return array<int, string>
     */
    public function get_keywords(): array
    {
        return ['reservation', 'booking', 'restaurant'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content_section', [
            'label' => __('Configurazione', 'fp-restaurant-reservations'),
        ]);

        $this->add_control('location', [
            'label'   => __('Sede/Location', 'fp-restaurant-reservations'),
            'type'    => Controls_Manager::TEXT,
            'default' => 'default',
        ]);

        $this->add_control('language', [
            'label'       => __('Lingua forzata', 'fp-restaurant-reservations'),
            'type'        => Controls_Manager::TEXT,
            'description' => __('Lascia vuoto per usare il rilevamento automatico (WPML/Polylang/get_locale).', 'fp-restaurant-reservations'),
            'default'     => '',
        ]);

        $this->add_control('form_id', [
            'label'       => __('ID form personalizzato', 'fp-restaurant-reservations'),
            'type'        => Controls_Manager::TEXT,
            'description' => __('Utile quando servono più form nella stessa pagina.', 'fp-restaurant-reservations'),
            'default'     => '',
        ]);

        $this->end_controls_section();
    }

    public function render(): void
    {
        $settings = $this->get_settings_for_display();

        $atts = [
            'location' => isset($settings['location']) && is_string($settings['location']) ? $settings['location'] : 'default',
            'lang'     => isset($settings['language']) && is_string($settings['language']) ? $settings['language'] : '',
            'form_id'  => isset($settings['form_id']) && is_string($settings['form_id']) ? $settings['form_id'] : '',
        ];

        echo Shortcodes::render($atts);
    }
}
