/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.loader.http");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.loader.http = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'http',

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
                        xtype: 'combo',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_http_schema'),
                        name: this.dataNamePrefix + 'schema',
                        store: ['https://', 'http://'],
                        forceSelection: true,
                        value: this.data.schema,
                        allowBlank: false,
                        msgTarget: 'under',
                        width: 330
                    },{
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_http_url'),
                        name: this.dataNamePrefix + 'url',
                        value: this.data.url,
                        allowBlank: false,
                        msgTarget: 'under',
                        width: 900

                    }
                ]
            });
        }

        return this.form;
    }

});