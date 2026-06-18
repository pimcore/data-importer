/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType = Class.create({

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