/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.numeric");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.numeric = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'numeric',

    getMenuGroup: function() {
        return this.menuGroups.dataTypes;
    },

    getIconClass: function() {
        return "pimcore_icon_data_group_numeric";
    },

    getFormItems: function() {
        return [
            {
                xtype: 'checkbox',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_numeric_return_null'),
                value: this.data.settings ? this.data.settings.returnNullIfEmpty : ' ',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                name: 'settings.returnNullIfEmpty'
            }
        ];
    }

});