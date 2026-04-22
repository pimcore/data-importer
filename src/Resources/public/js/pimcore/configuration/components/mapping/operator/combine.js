/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.combine");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.combine = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'combine',

    getMenuGroup: function() {
        return this.menuGroups.dataManipulation;
    },

    getFormItems: function() {
        return [
            {
                xtype: 'textfield',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_glue'),
                value: this.data.settings ? this.data.settings.glue : ' ',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                name: 'settings.glue'
            }
        ];
    }

});