pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.loader.http");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.loader.http = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'http',

    buildSettingsForm: function() {

        if(!this.form) {
            this.form = Ext.create('DataHub.BatchImport.StructuredValueForm', {
                defaults: {
                    labelWidth: 200,
                    width: 600
                },
                border: false,
                items: [
                    {
                        xtype: 'combo',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_http_schema'),
                        name: this.dataNamePrefix + 'schema',
                        store: ['https://', 'http://'],
                        forceSelection: true,
                        value: this.data.schema,
                        allowBlank: false,
                        msgTarget: 'under',
                        width: 300
                    },{
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_http_url'),
                        name: this.dataNamePrefix + 'url',
                        value: this.data.url,
                        allowBlank: false,
                        msgTarget: 'under',
                        width: 900

                    },
                ]
            });
        }

        return this.form;
    }

});