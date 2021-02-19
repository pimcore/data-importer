/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.operator.loadAsset");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.operator.loadAsset = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.abstractOperator, {

    type: 'loadAsset',

    getFormItems: function() {
        return [
            {
                xtype: 'combo',
                fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_transformation_pipeline_asset_load_strategy'),
                value: this.data.settings ? this.data.settings.loadStrategy : 'path',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                name: 'settings.loadStrategy',
                store: [
                    ['path', t('plugin_pimcore_datahub_batch_import_configpanel_find_strategy_path')],
                    ['id', t('plugin_pimcore_datahub_batch_import_configpanel_find_strategy_id')],
                ]
            }
        ];
    }

});