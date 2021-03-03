/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
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