/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

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