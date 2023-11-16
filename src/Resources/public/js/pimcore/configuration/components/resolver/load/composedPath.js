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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.load.composedPath');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.load.composedPath = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'composedPath',

    buildSettingsForm: function() {

        if(!this.form) {


            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
                defaults: {
                    labelWidth: 200,
                    width: 600,
                    allowBlank: false,
                    msgTarget: 'under'
                },
                border: false,
                items: [
                    {
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_find_strategy_composedPath'),
                        name: this.dataNamePrefix + 'composedPath',
                        value: this.data.composedPath
                    }
                ]
            });
        }

        return this.form;
    }

});