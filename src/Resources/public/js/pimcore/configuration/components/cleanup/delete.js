pimcore.registerNS('pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.cleanup.delete');
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.cleanup.delete = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'delete',

    buildSettingsForm: function() {
        return null;
    }

});