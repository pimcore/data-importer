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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.loader.sftp");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.loader.sftp = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'sftp',

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
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_sftp_host'),
                        name: this.dataNamePrefix + 'host',
                        value: this.data.host,
                        allowBlank: false,
                        msgTarget: 'under'
                    },
                    {
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_sftp_port'),
                        name: this.dataNamePrefix + 'port',
                        value: this.data.port || 22,
                        allowBlank: false,
                        msgTarget: 'under',
                        width: 350
                    },
                    {
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_sftp_username'),
                        name: this.dataNamePrefix + 'username',
                        value: this.data.username,
                        allowBlank: false,
                        msgTarget: 'under'
                    },
                    {
                        xtype: 'textfield',
                        inputType: 'password',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_sftp_password'),
                        name: this.dataNamePrefix + 'password',
                        value: this.data.password,
                        allowBlank: false,
                        msgTarget: 'under'
                    },
                    {
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_sftp_remotePath'),
                        name: this.dataNamePrefix + 'remotePath',
                        value: this.data.remotePath || '/path/to/file/import.json',
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