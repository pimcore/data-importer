/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.stringReplace");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.stringReplace = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'stringReplace',

    getMenuGroup: function() {
        return this.menuGroups.dataManipulation;
    },

    getIconClass: function() {
        return 'pimcore_icon_operator_stringreplace';
    },

    getFormItems: function() {
        return [
            {
                xtype: 'textfield',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_search'),
                value: this.data.settings ? this.data.settings.search : '',
                name: 'settings.search',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                }
            },

            {
                xtype: 'textfield',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_replace'),
                value: this.data.settings ? this.data.settings.replace : '',
                name: 'settings.replace',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                }
            },
        ];
    }

});