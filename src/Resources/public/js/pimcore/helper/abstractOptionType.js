pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType = Class.create({

    type: 'abstract',
    data: {},
    dataNamePrefix: '',
    configItemRootContainer: null,
    initContext: null,

    initialize: function (data, dataNamePrefix, configItemRootContainer, initContext) {

        this.data = data;
        this.dataNamePrefix = dataNamePrefix + '.';

        this.configItemRootContainer = configItemRootContainer;
        this.initContext = initContext;
    }

});