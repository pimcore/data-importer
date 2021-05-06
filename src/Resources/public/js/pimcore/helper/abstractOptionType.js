/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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