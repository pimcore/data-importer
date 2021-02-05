pimcore.registerNS('pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.interpreter.csv');
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.interpreter.csv = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'csv',

    buildSettingsForm: function() {

        if(!this.form) {
            this.form = Ext.create('DataHub.BatchImport.StructuredValueForm', {
                defaults: {
                    labelWidth: 200,
                    width: 600,
                    allowBlank: false,
                    msgTarget: 'under'
                },
                border: false,
                items: [
                    {
                        xtype: 'checkbox',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_csv_skip_first_row'),
                        name: this.dataNamePrefix + 'skipFirstRow',
                        value: this.data.hasOwnProperty('skipFirstRow') ? this.data.skipFirstRow : false,
                        inputValue: true
                    },{
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_csv_delimiter'),
                        name: this.dataNamePrefix + 'delimiter',
                        value: this.data.delimiter || ',',
                        width: 250
                    },{
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_csv_enclosure'),
                        name: this.dataNamePrefix + 'enclosure',
                        value: this.data.enclosure || '"',
                        width: 250
                    },{
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_csv_escape'),
                        name: this.dataNamePrefix + 'escape',
                        value: this.data.escape || '\\',
                        width: 250
                    },
                ]
            });
        }

        return this.form;
    }

});