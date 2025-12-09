(() => {
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { PanelBody, TextControl, ToggleControl, SelectControl } = wp.components;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const ServerSideRender = wp.serverSideRender;

    registerBlockType('event-hub/session-detail', {
        title: __('Event Hub - Detail', 'event-hub'),
        icon: 'calendar',
        category: 'widgets',
        attributes: {
            sessionId: { type: 'number', default: 0 },
            template: { type: 'string', default: '' },
        },
        edit: ({ attributes, setAttributes }) => {
            const { sessionId, template } = attributes;
            return (
                <>
                    <InspectorControls>
                        <PanelBody title={__('Instellingen', 'event-hub')}>
                            <TextControl
                                label={__('Event ID', 'event-hub')}
                                type="number"
                                value={sessionId || ''}
                                onChange={(val) => setAttributes({ sessionId: parseInt(val || '0', 10) || 0 })}
                                help={__('Laat leeg om automatisch het huidige bericht te gebruiken.', 'event-hub')}
                            />
                            <TextControl
                                label={__('Template-bestand', 'event-hub')}
                                value={template}
                                onChange={(val) => setAttributes({ template: val })}
                                help={__('Optioneel: naam van een thema-template (bijv. single-event.php).', 'event-hub')}
                            />
                        </PanelBody>
                    </InspectorControls>
                    <ServerSideRender block="event-hub/session-detail" attributes={attributes} />
                </>
            );
        },
        save: () => null,
    });

    registerBlockType('event-hub/session-list', {
        title: __('Event Hub - Lijst', 'event-hub'),
        icon: 'list-view',
        category: 'widgets',
        attributes: {
            count: { type: 'number', default: 6 },
            status: { type: 'string', default: '' },
            order: { type: 'string', default: 'ASC' },
            showExcerpt: { type: 'boolean', default: true },
            showDate: { type: 'boolean', default: true },
        },
        edit: ({ attributes, setAttributes }) => {
            const { count, order, showExcerpt, showDate } = attributes;
            return (
                <>
                    <InspectorControls>
                        <PanelBody title={__('Lijstopties', 'event-hub')}>
                            <TextControl
                                label={__('Aantal events', 'event-hub')}
                                type="number"
                                value={count}
                                min={1}
                                onChange={(val) => setAttributes({ count: parseInt(val || '1', 10) || 1 })}
                            />
                            <SelectControl
                                label={__('Volgorde', 'event-hub')}
                                value={order}
                                options={[
                                    { label: __('Oplopend (datum)', 'event-hub'), value: 'ASC' },
                                    { label: __('Aflopend (datum)', 'event-hub'), value: 'DESC' },
                                ]}
                                onChange={(val) => setAttributes({ order: val })}
                            />
                            <ToggleControl
                                label={__('Toon datum/tijd', 'event-hub')}
                                checked={!!showDate}
                                onChange={(val) => setAttributes({ showDate: !!val })}
                            />
                            <ToggleControl
                                label={__('Toon excerpt', 'event-hub')}
                                checked={!!showExcerpt}
                                onChange={(val) => setAttributes({ showExcerpt: !!val })}
                            />
                        </PanelBody>
                    </InspectorControls>
                    <ServerSideRender block="event-hub/session-list" attributes={attributes} />
                </>
            );
        },
        save: () => null,
    });

    registerBlockType('event-hub/calendar', {
        title: __('Event Hub - Kalender', 'event-hub'),
        icon: 'schedule',
        category: 'widgets',
        attributes: {
            initialView: { type: 'string', default: 'dayGridMonth' },
        },
        edit: ({ attributes, setAttributes }) => {
            const { initialView } = attributes;
            return (
                <>
                    <InspectorControls>
                        <PanelBody title={__('Kalenderopties', 'event-hub')}>
                            <SelectControl
                                label={__('Startweergave', 'event-hub')}
                                value={initialView}
                                options={[
                                    { label: __('Maand', 'event-hub'), value: 'dayGridMonth' },
                                    { label: __('Week (tijd)', 'event-hub'), value: 'timeGridWeek' },
                                    { label: __('Weeklijst', 'event-hub'), value: 'listWeek' },
                                ]}
                                onChange={(val) => setAttributes({ initialView: val })}
                            />
                        </PanelBody>
                    </InspectorControls>
                    <div className="eh-calendar-placeholder">
                        {__('Volledige Octopus kalender wordt hier getoond op de site.', 'event-hub')}
                    </div>
                </>
            );
        },
        save: () => null,
    });
})();
