pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.datatarget.objectBrick");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.datatarget.objectBrick = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'objectBrick',

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
                        xtype: "label",
                        fieldLabel: t("URL"),
                        html: 'TODO',
                        // name: this.dataNamePrefix + 'url',
                        // value: this.data.url
                    },
                ]
            });
        }

        return this.form;
    }

});