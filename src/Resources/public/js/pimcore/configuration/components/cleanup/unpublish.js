pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.cleanup.unpublish");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.cleanup.unpublish = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'unpublish',

    buildSettingsForm: function() {
        return null;
    }

});