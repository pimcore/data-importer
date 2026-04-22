/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.flattenArray');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.flattenArray = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'flattenArray',

    getMenuGroup: function() {
        return this.menuGroups.dataManipulation;
    },
});