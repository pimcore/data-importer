/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS("pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.operator.combine");
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.operator.combine = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.abstractOperator, {

    type: 'combine',

    getFormItems: function() {
        return [
            {
                xtype: 'textfield',
                fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_transformation_pipeline_glue'),
                value: this.data.settings ? this.data.settings.glue : ' ',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                name: 'settings.glue'
            }
        ];
    }

});