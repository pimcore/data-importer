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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.conditionalConversion");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.conditionalConversion = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'conditionalConversion',

    getFormItems: function() {
        return [
            {
                xtype: 'textfield',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_original'),
                value: this.data.settings ? this.data.settings.original : '',
                name: 'settings.original',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                }
            },

            {
                xtype: 'textfield',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_converted'),
                value: this.data.settings ? this.data.settings.converted : '',
                name: 'settings.converted',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                }
            }
        ];
    }

});
