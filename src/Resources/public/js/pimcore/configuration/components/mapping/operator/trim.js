/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.trim");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.trim = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'trim',

    getFormItems: function() {
        return [
            {
                xtype: 'combo',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_mode'),
                value: this.data.settings ? this.data.settings.mode : 'both',
                name: 'settings.mode',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                store: [
                    ['left', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_left')],
                    ['right', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_right')],
                    ['both', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_both')]
                ]

            }
        ];
    }

});