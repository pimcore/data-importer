pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.interpreter.xlsx");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.interpreter.xlsx = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'xlsx',

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
                        xtype: 'checkbox',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_csv_skip_first_row'),
                        name: this.dataNamePrefix + 'skipFirstRow',
                        value: this.data.hasOwnProperty('skipFirstRow') ? this.data.skipFirstRow : false,
                        inputValue: true
                    },{
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_xlsx_sheet'),
                        name: this.dataNamePrefix + 'sheetName',
                        value: this.data.sheetName || 'Sheet1'
                    }
                ]
            });
        }

        return this.form;
    }

});