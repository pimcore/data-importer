/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.htmlDecode");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.htmlDecode = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'htmlDecode',

    getMenuGroup: function() {
        return this.menuGroups.dataManipulation;
    },
    getIconClass: function() {
        return "pimcore_icon_html";
    },
});