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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.loader.upload");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.loader.upload = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'upload',

    buildSettingsForm: function() {
        if(!this.form) {

            let items = [];
            const url = Routing.generate('pimcore_dataimporter_configdataobject_uploadimportfile', {'config_name': this.initContext.configName});

            items.push({
                xtype: 'fileuploadfield',
                emptyText: t('select_a_file'),
                fieldLabel: t('file'),
                readOnly: false,
                width: 470,
                name: 'Filedata',
                buttonText: "",
                buttonConfig: {
                    iconCls: 'pimcore_icon_upload'
                },
                listeners: {
                    change: function () {
                        this.form.getForm().submit({
                            url: url,
                            params: {
                                csrfToken: pimcore.settings['csrfToken']
                            },
                            waitMsg: t("please_wait"),
                            success: function (el, res) {
                                this.updateUploadStatusInformation();
                            }.bind(this),

                            failure: function (el, res) {
                                const response = res.response;

                                let jsonData = null;
                                try {
                                    jsonData = Ext.decode(response.responseText);
                                } catch (e) {

                                }

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

                                var message = t("error_general");
                                if(jsonData && jsonData['message']) {
                                    message = jsonData['message'] + "<br><br>" + t("error_general");
                                }
                                pimcore.helpers.showNotification(t("error"), message, "error", errorMessage);
                            }
                        });
                    }.bind(this)
                }
            });

            this.uploadStatus = Ext.create('Ext.form.field.Display', {
                hideLabel: false,
                disabled: false,
                style: 'margin-left:205px;margin-top:-10px'
            });
            items.push(this.uploadStatus);

            this.uploadFilePath = Ext.create('Ext.form.TextField', {
                name: this.dataNamePrefix + 'uploadFilePath',
                value: this.data.uploadFilePath,
                hidden: true
            });
            items.push(this.uploadFilePath);

            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
                fileUpload: true,
                defaults: {
                    labelWidth: 200,
                    width: 600
                },
                border: false,
                width: 900,
                items: items
            });
        }

        this.updateUploadStatusInformation();

        return this.form;
    },

    updateUploadStatusInformation: function() {

        Ext.Ajax.request({
            url: Routing.generate('pimcore_dataimporter_configdataobject_hasimportfileuploaded'),
            method: 'GET',
            params: {
                config_name: this.initContext.configName,
            },
            success: function (response) {
                let data = Ext.decode(response.responseText);

                if(data.message) {
                    this.uploadStatus.setValue(data.message);
                } else {
                    this.uploadStatus.setValue('');
                }

                if(data.success) {
                    this.uploadStatus.setFieldStyle('color: green');
                } else {
                    this.uploadStatus.setFieldStyle('color: red');
                }

                this.uploadFilePath.setValue(data.filePath);

            }.bind(this)
        });
    }

});