pimcore.registerNS("pimcore.plugin.PimcoreDataHubBatchImportBundle");

pimcore.plugin.PimcoreDataHubBatchImportBundle = Class.create(pimcore.plugin.admin, {
    getClassName: function () {
        return "pimcore.plugin.PimcoreDataHubBatchImportBundle";
    },

    initialize: function () {
        pimcore.plugin.broker.registerPlugin(this);
    },

    pimcoreReady: function (params, broker) {
        // alert("PimcoreDataHubBatchImportBundle ready!");
    }
});

var PimcoreDataHubBatchImportBundlePlugin = new pimcore.plugin.PimcoreDataHubBatchImportBundle();
