pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.interpreter.json");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.interpreter.json = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'json',

    buildSettingsForm: function() {

        if(!this.form) {
            this.form = Ext.create('DataHub.BatchImport.StructuredValueForm', {
                defaults: {
                    labelWidth: 200,
                    width: 600
                },
                border: false,
                items: [
                ]
            });
        }

        return this.form;
    }

});