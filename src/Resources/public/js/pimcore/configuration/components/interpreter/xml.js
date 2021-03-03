/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.interpreter.xml');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.interpreter.xml = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'xml',

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
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_xml_xpath'),
                        name: this.dataNamePrefix + 'xpath',
                        value: this.data.xpath || '/root/item',
                        allowBlank: false,
                        msgTarget: 'under'
                    },{
                        xtype: 'textarea',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_xml_schema'),
                        name: this.dataNamePrefix + 'schema',
                        value: this.data.schema || '',
                        grow: true,
                        width: 900,
                        scrollable: true
                    }

                ]
            });
        }

        return this.form;
    }

});