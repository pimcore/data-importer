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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.execution');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.execution = Class.create({

    configName: '',
    data: {},
    configItemRootContainer: null,
    currentLoaderType: null,
    currentDirtyState: false,
    updateHandle: null,

    initialize: function(configName, data, configItemRootContainer, loaderType) {
        this.configName = configName;
        this.data = data;
        this.configItemRootContainer = configItemRootContainer;
        this.currentLoaderType = loaderType;
    },

    buildPanel: function() {

        if(!this.form) {

            this.buttonFieldContainer = Ext.create('Ext.form.FieldContainer', {
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_execution_manual_execution'),
                items: [
                    {
                        xtype: 'button',
                        width: 165,
                        text: t('plugin_pimcore_datahub_data_importer_configpanel_execution_start'),
                        handler: this.startImport.bind(this)
                    }
                ],
            });

            this.scheduleTypes = Ext.create('Ext.form.FieldContainer', {
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_execution_schedule_type'),
                items: [{
                    xtype: 'radiogroup',
                    vertical: 'false',
                    columns: 2,
                    width: 400,
                    items: [{
                        boxLabel: t('plugin_pimcore_datahub_data_importer_configpanel_execution_schedule_type_cron_label'),
                        name: 'scheduleType',
                        checked: !this.data || this.data.scheduleType !== 'job',
                        inputValue: 'recurring',
                        listeners: {
                            change:  (obj, value) => {
                                if (value) {
                                    this.cronDefinitionContainer.down('textfield').setValue(this.data.cronDefinition);
                                    this.cronDefinitionContainer.setVisible(true);
                                    this.scheduledAtContainer.setVisible(false);
                                    this.scheduledAtContainer.down('datefield').reset();
                                }
                            },
                            scope: this
                        }

                    }, {
                        boxLabel: t('plugin_pimcore_datahub_data_importer_configpanel_execution_schedule_type_job_label'),
                        name: 'scheduleType',
                        checked: this.data?.scheduleType === 'job',
                        inputValue: 'job',
                        listeners: {
                            change: (obj, value) => {
                                if (value) {
                                    this.scheduledAtContainer.down('datefield').setValue(this.data.scheduledAt);
                                    this.scheduledAtContainer.setVisible(true);
                                    this.cronDefinitionContainer.setVisible(false);
                                    this.cronDefinitionContainer.down('textfield').reset();
                                }
                            },
                            scope: this
                        }
                    }]
                }]
            });

            this.scheduledAtContainer = Ext.create('Ext.form.FieldContainer', {
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_execution_datetime'),
                layout: 'hbox',
                hidden: this.data?.scheduleType !== 'job',
                style: 'margin-bottom: 18px;',
                items: [
                    {
                        xtype: 'datefield',
                        name: 'scheduledAt',
                        width: 300,
                        format: 'd-m-Y H:i',
                        value: this.data ? this.data.scheduledAt : '',
                        activeErrorsTpl: t('plugin_pimcore_datahub_data_importer_configpanel_execution_status_error'),
                        formatText: t('plugin_pimcore_datahub_data_importer_configpanel_execution_date_format'),
                        msgTarget: 'under'
                    }
                ]
            });

            this.cronDefinitionContainer = Ext.create('Ext.form.FieldContainer', {
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_execution_cron'),
                layout: 'hbox',
                hidden: this.data?.scheduleType === 'job',
                items: [
                    {
                        xtype: 'textfield',
                        name: 'cronDefinition',
                        width: 300,
                        value: this.data.cronDefinition,
                        listeners: {
                            blur: function(field) {
                                if(this.cronTimeout) {
                                    clearTimeout(this.cronTimeout);
                                }
                                this.validateCron(field);
                            }.bind(this),
                            change: function(field) {
                                if(this.cronTimeout) {
                                    clearTimeout(this.cronTimeout);
                                }
                                this.cronTimeout = setTimeout(function(field) {
                                    this.validateCron(field);
                                }.bind(this, field), 500);
                            }.bind(this)
                        },
                        msgTarget: 'under',
                    },
                    {
                        xtype: 'displayfield',
                        style: 'padding-left: 10px',
                        value: '<a target="_blank" href="https://crontab.guru/">' + t('plugin_pimcore_datahub_data_importer_configpanel_execution_cron_generator') + '</a>'
                    }
                ]
            });

            this.progressLabel = Ext.create('Ext.form.Label', {
                style: 'margin-bottom: 5px; display: block'
            });
            this.progressBar = Ext.create('Ext.ProgressBar', {
                hidden: true
            });
            this.cancelButtonContainer = Ext.create('Ext.Panel', {
                layout: 'hbox',
                hidden: true,
                bodyStyle: 'padding-top: 10px',
                border: false,
                items: [
                    {
                        xtype: 'component',
                        flex: 1
                    },
                    {
                        xtype: 'button',
                        iconCls: 'pimcore_icon_cancel',
                        text: t('plugin_pimcore_datahub_data_importer_configpanel_execution_cancel'),
                        handler: function() {
                            Ext.Ajax.request({
                                url: Routing.generate('pimcore_dataimporter_configdataobject_cancelexecution'),
                                method: 'PUT',
                                params: {
                                    config_name: this.configName,
                                },
                                success: function (response) {

                                    pimcore.helpers.showNotification(t('success'), t('plugin_pimcore_datahub_data_importer_configpanel_execution_cancel_successful'), 'success');
                                    this.updateProgress();

                                }.bind(this)
                            });
                        }.bind(this)
                    }
                ]
            });

            this.updateProgress();

            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
                bodyStyle: 'padding:10px;',
                title: t('plugin_pimcore_datahub_data_importer_configpanel_execution'),
                items: [
                    {
                        xtype: 'fieldset',
                        title: t('plugin_pimcore_datahub_data_importer_configpanel_execution_settings'),
                        defaults: {
                            labelWidth: 130
                        },
                        items: [
                            this.scheduleTypes,
                            this.cronDefinitionContainer,
                            this.scheduledAtContainer,
                            this.buttonFieldContainer
                        ]
                    },{
                        xtype: 'fieldset',
                        title: t('plugin_pimcore_datahub_data_importer_configpanel_execution_status'),
                        items: [
                            this.progressLabel,
                            this.progressBar,
                            this.cancelButtonContainer

                        ]
                    }
                ]
            });

            this.updateDisabledState();

            this.configItemRootContainer.on(pimcore.plugin.pimcoreDataImporterBundle.configuration.events.loaderTypeChanged, function(newType) {
                this.currentLoaderType = newType;
                this.updateDisabledState();
            }.bind(this));

            this.configItemRootContainer.on(pimcore.plugin.pimcoreDataImporterBundle.configuration.events.configDirtyChanged, function(dirty) {
                this.currentDirtyState = dirty;
                this.updateDisabledState();
            }.bind(this));

            this.form.on('destroy', function() {
                clearTimeout(this.updateHandle);
            }.bind(this));

        }

        return this.form;
    },

    updateDisabledState: function() {
        this.cronDefinitionContainer.setDisabled(this.currentLoaderType === 'push');
        this.buttonFieldContainer.setDisabled(this.currentLoaderType === 'push' || this.currentDirtyState);
    },

    startImport: function(button) {

        button.setText(t('plugin_pimcore_datahub_data_importer_configpanel_execution_start_loading'));
        button.setDisabled(true);

        Ext.Ajax.request({
            url: Routing.generate('pimcore_dataimporter_configdataobject_startbatchimport'),
            method: 'PUT',
            params: {
                config_name: this.configName,
            },
            success: function (response) {
                let data = Ext.decode(response.responseText);

                if (data && data.success) {
                    pimcore.helpers.showNotification(t('success'), t('plugin_pimcore_datahub_data_importer_configpanel_execution_start_successful'), 'success');
                } else {
                    pimcore.helpers.showNotification(t("error"), t('plugin_pimcore_datahub_data_importer_configpanel_execution_start_error'), 'error');
                }
                button.setDisabled(false);
                button.setText(t('plugin_pimcore_datahub_data_importer_configpanel_execution_start'));
                this.updateDisabledState();
                this.updateProgress();
            }.bind(this)
        });
    },

    validateCron: function(field) {

        if(field.getValue().length === 0) {
            field.setValidation(true);
        } else {
            Ext.Ajax.request({
                url: Routing.generate('pimcore_dataimporter_configdataobject_iscronexpressionvalid'),
                method: 'GET',
                params: {
                    cron_expression: field.getValue()
                },
                success: function (response) {
                    let data = Ext.decode(response.responseText);
                    if(data.success) {
                        field.setValidation(true);
                    } else {
                        field.setValidation(data.message);
                    }
                    field.isValid();
                }.bind(this)
            });
        }

    },

    updateProgress: function() {
        clearTimeout(this.updateHandle);
        Ext.Ajax.request({
            url: Routing.generate('pimcore_dataimporter_configdataobject_checkimportprogress'),
            method: 'GET',
            params: {
                config_name: this.configName,
            },
            success: function (response) {
                let data = Ext.decode(response.responseText);

                if(data.isRunning) {
                    this.progressBar.show();
                    this.cancelButtonContainer.show();
                    this.progressBar.updateProgress(data.progress, data.processedItems + '/' + data.totalItems + ' ' + t('plugin_pimcore_datahub_data_importer_configpanel_execution_processed'));
                    this.progressLabel.setHtml(t('plugin_pimcore_datahub_data_importer_configpanel_execution_current_progress'));
                } else {
                    this.progressBar.hide();
                    this.cancelButtonContainer.hide();
                    this.progressLabel.setHtml('<b>' + t('plugin_pimcore_datahub_data_importer_configpanel_execution_not_running') + '</b>');
                }

                this.updateHandle = setTimeout(this.updateProgress.bind(this), 5000);

            }.bind(this)
        });
    }

});