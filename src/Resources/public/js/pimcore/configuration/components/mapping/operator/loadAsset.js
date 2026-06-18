/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.loadAsset");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.loadAsset = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'loadAsset',

    getMenuGroup: function() {
        return this.menuGroups.loadImport;
    },

    getIconClass: function() {
        return "pimcore_icon_asset pimcore_icon_overlay_add";
    },

    getFormItems: function() {
        return [
            {
                xtype: 'combo',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_asset_load_strategy'),
                value: this.data.settings ? this.data.settings.loadStrategy : 'path',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                name: 'settings.loadStrategy',
                store: [
                    ['path', t('plugin_pimcore_datahub_data_importer_configpanel_find_strategy_path')],
                    ['id', t('plugin_pimcore_datahub_data_importer_configpanel_find_strategy_id')],
                ]
            }
        ];
    }

});