pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.operator.explode");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.operator.explode = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.abstractOperator, {

    type: 'explode',

    getFormItems: function() {
        return [
            {
                xtype: 'textfield',
                fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_transformation_pipeline_delimiter'),
                value: this.data.settings ? this.data.settings.delimiter : ' ',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                name: 'settings.delimiter'
            }
        ];
    }

});