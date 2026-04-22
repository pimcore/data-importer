/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.asArray');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.asArray = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'asArray',

    getMenuGroup: function() {
        return this.menuGroups.dataTypes;
    }
});