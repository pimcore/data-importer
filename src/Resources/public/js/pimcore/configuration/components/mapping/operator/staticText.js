/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.staticText");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.staticText = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'staticText',

    getMenuGroup: function() {
        return this.menuGroups.dataManipulation;
    },

    getFormItems: function() {
        return [
            {
                xtype: 'combo',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_static_text_mode'),
                value: this.data.settings ? this.data.settings.mode : 'append',
                name: 'settings.mode',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                store: [
                    ['append', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_append')],
                    ['prepend', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_prepend')]
                ]

            },

            {
                xtype: 'textfield',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_text'),
                value: this.data.settings ? this.data.settings.text : '',
                name: 'settings.text',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                }
            },

            {
                xtype: 'checkbox',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_always_add'),
                value: this.data.settings ? this.data.settings.alwaysAdd : false,
                name: 'settings.alwaysAdd'
            }
        ];
    }

});