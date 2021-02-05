pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.operator.trim");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.operator.trim = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.abstractOperator, {

    type: 'trim',

    getFormItems: function() {
        return [
            {
                xtype: 'combo',
                fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_transformation_pipeline_mode'),
                value: this.data.settings ? this.data.settings.mode : 'both',
                name: 'settings.mode',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                store: [
                    ['left', t('plugin_pimcore_datahub_batch_import_configpanel_transformation_pipeline_left')],
                    ['right', t('plugin_pimcore_datahub_batch_import_configpanel_transformation_pipeline_right')],
                    ['both', t('plugin_pimcore_datahub_batch_import_configpanel_transformation_pipeline_both')]
                ]

            }
        ];
    }

});