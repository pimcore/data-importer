/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS('pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.loader.push');
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.loader.push = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'push',

    buildSettingsForm: function() {

        if(!this.form) {
            var apikeyField = new Ext.form.field.Text({
                name: this.dataNamePrefix + 'apiKey',
                value: this.data.apiKey,
                width: 400,
                minLength: 16,
                allowBlank: false,
                msgTarget: 'under'
            });

            this.form = Ext.create('DataHub.BatchImport.StructuredValueForm', {
                defaults: {
                    labelWidth: 200,
                    width: 600
                },
                border: false,
                items: [
                    {
                        xtype: 'fieldcontainer',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_push_apikey'),
                        layout: 'hbox',
                        width: 700,
                        items: [
                            apikeyField,
                            {
                                xtype: 'button',
                                width: 32,
                                style: 'margin-left: 8px',
                                iconCls: 'pimcore_icon_clear_cache',
                                handler: function () {
                                    apikeyField.setValue(md5(uniqid()));
                                }.bind(this)
                            }
                        ]
                    },{
                        xtype: 'checkbox',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_push_ignore_not_empty_queue'),
                        name: this.dataNamePrefix + 'ignoreNotEmptyQueue',
                        value: this.data.ignoreNotEmptyQueue,
                    },{
                        xtype: 'fieldcontainer',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_push_endpoint'),
                        layout: 'hbox',
                        width: 700,
                        items: [
                            {
                                xtype: 'label',
                                text: location.protocol + '//' + location.host + '/pimcore-datahub-import/' + this.initContext.configName + '/push'
                            }
                        ]
                    }

                ]
            });
        }

        return this.form;
    }

});