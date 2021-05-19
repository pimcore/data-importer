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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.importPreview');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.importPreview = Class.create({

    configName: '',
    configItemInstance: null,
    previewRecordIndex: 0,
    transformationResultHandler: null,

    initialize: function(configName, configItemInstance, transformationResultHandler) {
        this.configName = configName;
        this.configItemInstance = configItemInstance;
        this.transformationResultHandler = transformationResultHandler;
    },

    buildImportPreviewPanel: function() {

        if(!this.panel) {
            this.panel = Ext.create('Ext.Panel', {
                title: t('plugin_pimcore_datahub_data_importer_configpanel_import_preview'),
                region: 'west',
                autoScroll: true,
                animate: false,
                containerScroll: true,
                width: 400,
                split: true,
                items: [
                    this.buildUploadPanel(),
                    this.buildPreviewGrid()
                ]
            });
        }
        return this.panel;

    },

    buildUploadPanel: function() {
        this.errorField = Ext.create('Ext.form.field.Display', {});
        this.uploadPanel = Ext.create('Ext.Panel', {
            bodyStyle: 'padding: 0 8px',
            tbar: {
                items: [
                    {
                        xtype: 'button',
                        iconCls: 'pimcore_icon_upload',
                        tooltip: t('plugin_pimcore_datahub_data_importer_configpanel_preview_data_upload'),
                        handler: this.uploadDialog.bind(this)
                    },{
                        xtype: 'button',
                        iconCls: 'pimcore_icon_clone',
                        tooltip: t('plugin_pimcore_datahub_data_importer_configpanel_preview_data_clone'),
                        handler: this.copyPreviewFromDataSource.bind(this)
                    },
                    '->',
                    {
                        xtype: 'button',
                        iconCls: 'pimcore_icon_refresh',
                        handler: this.updatePreview.bind(this, null, false)
                    }
                ]
            },
            items: [
                this.errorField
            ]
        });

        return this.uploadPanel;
    },

    buildPreviewGrid: function() {

        var columns = [
            {
                text: t('plugin_pimcore_datahub_data_importer_configpanel_preview_dataindex'),
                flex: 200,
                sortable: false,
                hidden: true,
                dataIndex: 'dataindex'
            },
            {
                text: t('plugin_pimcore_datahub_data_importer_configpanel_preview_label'),
                flex: 130,
                sortable: false,
                dataIndex: 'label'
            },
            {
                text: t('plugin_pimcore_datahub_data_importer_configpanel_preview_data'),
                flex: 150,
                sortable: false,
                dataIndex: 'data',
                tdCls: 'whitespace-pre'
            }
        ];
        this.previewStore = Ext.create('Ext.data.JsonStore', {
            data: [],
            fields: ['dataIndex', 'label', 'data', 'mapped']
        });

        Ext.util.CSS.createStyleSheet(
            '.whitespace-pre .x-grid-cell-inner { white-space:pre }'
        );

        this.gridPanel = Ext.create('Ext.grid.Panel', {
            hidden: true,
            autoScroll: true,
            store: this.previewStore,
            columns: {
                items: columns,
                defaults: {
                    renderer: Ext.util.Format.htmlEncode
                },
            },
            emptyText: t('plugin_pimcore_datahub_data_importer_configpanel_preview_empty'),
            tbar: {
                items: [
                    {
                        xtype: 'button',
                        iconCls: 'pimcore_icon_upload',
                        tooltip: t('plugin_pimcore_datahub_data_importer_configpanel_preview_data_upload'),
                        handler: this.uploadDialog.bind(this)
                    },{
                        xtype: 'button',
                        iconCls: 'pimcore_icon_clone',
                        tooltip: t('plugin_pimcore_datahub_data_importer_configpanel_preview_data_clone'),
                        handler: this.copyPreviewFromDataSource.bind(this)
                    },
                    '->',
                    {
                        xtype: 'button',
                        iconCls: 'pimcore_icon_refresh',
                        handler: this.updatePreview.bind(this, null, false)
                    },{
                        xtype: 'button',
                        iconCls: 'plugin_pimcore_datahub_icon_previous',
                        handler: this.updatePreview.bind(this, 'previous', false)
                    },
                    {
                        xtype: 'button',
                        iconCls: 'plugin_pimcore_datahub_icon_next',
                        handler: this.updatePreview.bind(this, 'next', false)
                    }
                ]
            },
            trackMouseOver: true,
            columnLines: true,
            stripeRows: true,
            viewConfig: {
                forceFit: true,
                markDirty: false,
                getRowClass: function(record) {
                    if(record.get('mapped')) {
                        return 'data_hub_preview_panel_column_mapped';
                    } else {
                        return '';
                    }
                }
            },
            listeners: {
                afterrender: this.updatePreview.bind(this, null, true)
            }
        });

        return this.gridPanel;
    },

    checkValidConfiguration: function(suppressInvalidError) {
        let isValid = this.configItemInstance.loaderForm.isValid();
        isValid = this.configItemInstance.interpreterForm.isValid() && isValid;
        // isValid = this.configItemInstance.importSettings.resolverForm.isValid() && isValid;

        if(!isValid) {
            if(!suppressInvalidError) {
                pimcore.helpers.showNotification(t('error'), t('plugin_pimcore_datahub_data_importer_configpanel_invalid_config_for_preview'), 'error');
            }
            return false;
        }
        return true;

    },

    uploadDialog: function() {

        if(!this.checkValidConfiguration(false)) {
            return;
        }

        const url = Routing.generate('pimcore_dataimporter_configdataobject_uploadpreviewdata', {'config_name': this.configName});

        pimcore.helpers.uploadDialog(url, 'Filedata', function() {
            this.updatePreview();
        }.bind(this), function(res) {
            console.log('failure');
            console.log(res);

            const response = res.response;

            let jsonData = null;
            try {
                jsonData = Ext.decode(response.responseText);
            } catch (e) {

            }

            const errorMessage = this.buildErrorMessage(response, jsonData);

            let message = t("error_general");
            if(jsonData && jsonData['message']) {
                message = jsonData['message'] + "<br><br>" + t("error_general");
            }
            pimcore.helpers.showNotification(t("error"), message, "error", errorMessage);
        });
    },

    buildErrorMessage: function(response, jsonData) {
        const date = new Date();
        let errorMessage = "Timestamp: " + date.toString() + "\n";
        let errorDetailMessage = "\n" + response.responseText;

        try {
            errorMessage += "Status: " + response.status + " | " + response.statusText + "\n";
            errorMessage += "URL: " + options.url + "\n";

            if(jsonData) {
                if (jsonData['message']) {
                    errorDetailMessage = jsonData['message'];
                }

                if(jsonData['traceString']) {
                    errorDetailMessage += "\nTrace: \n" + jsonData['traceString'];
                }
            }

            errorMessage += "Message: " + errorDetailMessage;
        } catch (e) {
            errorMessage += "\n\n";
            errorMessage += response.responseText;
        }

        return errorMessage;
    },

    updatePreview: function(direction, suppressInvalidError) {

        if(!this.checkValidConfiguration(suppressInvalidError)) {
            return;
        }

        if(direction === 'next') {
            this.previewRecordIndex = this.previewRecordIndex + 1;
        } else if(direction === 'previous') {
            this.previewRecordIndex = (this.previewRecordIndex - 1 > 0) ? this.previewRecordIndex - 1 : 0;
        }

        const currentConfig = this.configItemInstance.getSaveData();

        Ext.Ajax.request({
            url: Routing.generate('pimcore_dataimporter_configdataobject_loaddatapreview'),
            method: 'POST',
            params: {
                config_name: this.configName,
                record_number: this.previewRecordIndex,
                current_config: currentConfig
            },
            success: function (response) {
                let data = Ext.decode(response.responseText);

                if(data.hasData) {
                    this.gridPanel.show();
                    this.uploadPanel.hide();
                    this.previewRecordIndex = data.previewRecordIndex;
                    this.previewStore.loadData(data.dataPreview);
                    this.transformationResultHandler.setCurrentPreviewRecord(data.previewRecordIndex);
                    this.transformationResultHandler.updateData(true);
                } else {
                    this.gridPanel.hide();
                    this.uploadPanel.show();
                    if(data.errorMessage) {
                        this.errorField.setValue(data.errorMessage);
                    } else {
                        this.errorField.setValue('');
                    }
                }


            }.bind(this)
        });


        this.configItemInstance.updateColumnHeaders();

    },

    copyPreviewFromDataSource: function() {

        if(!this.checkValidConfiguration(false)) {
            return;
        }

        const currentConfig = this.configItemInstance.getSaveData();

        Ext.Ajax.request({
            url: Routing.generate('pimcore_dataimporter_configdataobject_copypreviewdata'),
            method: 'POST',
            params: {
                config_name: this.configName,
                current_config: currentConfig
            },
            success: function (response) {
                let data = Ext.decode(response.responseText);

                if(data.success) {
                    this.updatePreview()
                } else {
                    let message = t("error_general");
                    if(data && data['message']) {
                        message = data['message'] + "<br><br>" + t("error_general");
                    }

                    const errorMessage = this.buildErrorMessage(response, data);
                    pimcore.helpers.showNotification(t("error"), message, "error", errorMessage);
                }

            }.bind(this)
        });
    }

});
