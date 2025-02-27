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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.interpreter.json");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.interpreter.json = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'json',

    buildSettingsForm: function() {

        if(!this.form) {
            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
                defaults: {
                    labelWidth: 200,
                    width: 600
                },
                border: false,
                items: [
                    {
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_json_path'),
                        name: this.dataNamePrefix + 'path',
                        value: this.data.path || '',
                    }
                ]
            });
        }

        return this.form;
    }

});