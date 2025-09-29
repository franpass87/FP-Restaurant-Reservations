(function (wp) {
    if (!wp || !wp.blocks || !wp.element || !wp.i18n) {
        return;
    }

    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { __ } = wp.i18n;
    const components = wp.components || {};
    const blockEditor = wp.blockEditor || wp.editor || {};
    const TextControl = components.TextControl || function () { return null; };
    const PanelBody = components.PanelBody || function (props) { return el('div', props, props.children); };
    const InspectorControls = blockEditor.InspectorControls || function (props) { return el(Fragment, {}, props.children); };
    const ServerSideRender = wp.serverSideRender || function () { return el('div', {}, __('Anteprima non disponibile', 'fp-restaurant-reservations')); };

    registerBlockType('fp-restaurant-reservations/form', {
        apiVersion: 2,
        title: __('Form Prenotazioni FP', 'fp-restaurant-reservations'),
        description: __('Modulo multi-step con bottone PDF e data layer pronto per il tracking.', 'fp-restaurant-reservations'),
        category: 'widgets',
        icon: 'calendar-alt',
        attributes: {
            location: {
                type: 'string',
                default: 'default',
            },
            language: {
                type: 'string',
                default: '',
            },
            formId: {
                type: 'string',
                default: '',
            },
        },
        supports: {
            align: ['wide', 'full'],
            anchor: true,
        },
        edit: function (props) {
            const { attributes, setAttributes } = props;

            const inspector = el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('Impostazioni modulo', 'fp-restaurant-reservations'), initialOpen: true },
                    el(TextControl, {
                        label: __('Sede/Location', 'fp-restaurant-reservations'),
                        help: __('Slug della sede per distinguere i PDF e le configurazioni lingua.', 'fp-restaurant-reservations'),
                        value: attributes.location || 'default',
                        onChange: function (value) {
                            setAttributes({ location: value || 'default' });
                        },
                    }),
                    el(TextControl, {
                        label: __('Lingua forzata', 'fp-restaurant-reservations'),
                        help: __('Opzionale. Sovrascrive il rilevamento automatico con uno slug lingua (es. it, en).', 'fp-restaurant-reservations'),
                        value: attributes.language || '',
                        onChange: function (value) {
                            setAttributes({ language: value });
                        },
                    }),
                    el(TextControl, {
                        label: __('ID Form personalizzato', 'fp-restaurant-reservations'),
                        help: __('Lascia vuoto per generarlo automaticamente dalla sede.', 'fp-restaurant-reservations'),
                        value: attributes.formId || '',
                        onChange: function (value) {
                            setAttributes({ formId: value });
                        },
                    })
                )
            );

            const preview = el(ServerSideRender, {
                block: 'fp-restaurant-reservations/form',
                attributes,
            });

            return el(Fragment, {}, inspector, preview);
        },
        save: function () {
            return null;
        },
    });
})(window.wp);
