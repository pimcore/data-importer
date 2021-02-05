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