/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.trim");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.trim = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'trim',

    getFormItems: function() {
        return [
            {
                xtype: 'combo',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_mode'),
                value: this.data.settings ? this.data.settings.mode : 'both',
                name: 'settings.mode',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                },
                store: [
                    ['left', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_left')],
                    ['right', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_right')],
                    ['both', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_both')]
                ]

            }
        ];
    }

});