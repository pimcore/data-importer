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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.configItemDataObject');
pimcore.plugin.pimcoreDataImporterBundle.configuration.configItemDataObject = Class.create(pimcore.plugin.datahub.configuration.graphql.configItem, {

    urlSave: Routing.generate('pimcore_dataimporter_configdataobject_save'),

    getPanels: function () {
        return [
            this.buildGeneralTab(),
            this.buildDataSourceTab(),
            this.buildImportSettingsTab(),
            this.buildExecutionTab(),
            this.buildLoggerTab()
        ];
    },

    initialize: function (data, parent) {
        //TODO make that more generic in datahub
        this.parent = parent;
        this.configName = data.name;
        this.data = data.configuration;
        // console.log(this.data);
        this.disableForm = false;
        if(data['configuration']['general']['writeable'] != null &&
            data['configuration']['general']['writeable'] != undefined) {
            this.disableForm = !data['configuration']['general']['writeable'];
        }
        this.modificationDate = data.modificationDate;

        this.tab = Ext.create('Ext.TabPanel', {
            title: this.data.general.name,
            closable: true,
            deferredRender: true,
            forceLayout: true,
            iconCls: "plugin_pimcore_datahub_icon_" + this.data.general.type,
            id: "plugin_pimcore_datahub_configpanel_panel_" + data.name,
            buttons: {
                componentCls: 'plugin_pimcore_datahub_statusbar',
                itemId: 'footer'
            },
            // items: this.getPanels()
        });

        this.tab.columnHeaderStore = Ext.create('Ext.data.Store', {
            fields: ['id', 'dataIndex', 'label'],
            data: data.columnHeaders,
            autoDestroy: false
        });

        this.tab.add(this.getPanels());
        this.tab.setActiveTab(0);

        this.tab.on("activate", this.tabactivated.bind(this));
        this.tab.on("destroy", this.tabdestroy.bind(this));
        this.tab.on('render', this.isValid.bind(this, false));
        this.setupChangeDetector();

        this.parent.configPanel.editPanel.add(this.tab);
        this.parent.configPanel.editPanel.setActiveTab(this.tab);
        this.parent.configPanel.editPanel.updateLayout();

        this.showInfo();
    },

    showInfo: function () {

        var footer = this.tab.getDockedComponent('footer');

        footer.removeAll();

        // this.queueCountInfo = Ext.create('Ext.form.field.Display', {
        //     labelWidth: 300,
        //     readOnly: false,
        //     disabled: false
        // });
        //
        // footer.add(this.queueCountInfo);
        footer.add('->');

        footer.add({
            text: t("save"),
            iconCls: "pimcore_icon_apply",
            handler: this.save.bind(this)
        });

    },

    save: function () {
        //TODO make that more generic in datahub
        if(this.disableForm === false) {
            if (!this.isValid(true)) {
                pimcore.helpers.showNotification(t('error'), t('plugin_pimcore_datahub_data_importer_configpanel_invalid_config'), 'error');
                return;
            }

            var saveData = this.getSaveData();

            Ext.Ajax.request({
                url: this.urlSave,
                params: {
                    data: saveData,
                    modificationDate: this.modificationDate
                },
                method: "post",
                success: function (response) {
                    var rdata = Ext.decode(response.responseText);
                    if (rdata && rdata.success) {
                        pimcore.helpers.showNotification(t("success"), t("plugin_pimcore_datahub_configpanel_item_save_success"), "success");
                        this.modificationDate = rdata.modificationDate;
                        this.resetChanges();
                    } else {
                        pimcore.helpers.showNotification(t("error"), t("plugin_pimcore_datahub_configpanel_item_saveerror"), "error", t(rdata.message));
                    }
                }.bind(this)
            });
        }
        else {
            pimcore.helpers.showNotification(t("info"), t("config_not_writeable"), "info");
        }
    },


    buildGeneralTab: function () {
        this.generalForm = Ext.create('Ext.form.FormPanel', {
            bodyStyle: "padding:10px;",
            autoScroll: true,
            defaults: {
                labelWidth: 200,
                width: 600
            },
            border: false,
            title: t('plugin_pimcore_datahub_configpanel_item_general'),
            items: [
                {
                    xtype: "checkbox",
                    fieldLabel: t("active"),
                    name: "active",
                    inputValue: true,
                    disabled: this.disableForm,
                    value: this.data.general && this.data.general.hasOwnProperty("active") ? this.data.general.active : false
                },
                {
                    xtype: "textfield",
                    fieldLabel: t("type"),
                    name: "type",
                    value: t("plugin_pimcore_datahub_type_" + this.data.general.type),
                    readOnly: true
                },
                {
                    xtype: "textfield",
                    fieldLabel: t("name"),
                    name: "name",
                    value: this.data.general.name,
                    readOnly: true
                },
                {
                    name: "description",
                    fieldLabel: t("description"),
                    xtype: "textarea",
                    height: 100,
                    disabled: this.disableForm,
                    value: this.data.general.description
                }
            ]
        });

        return this.generalForm;
    },

    buildDataSourceTab: function () {

        let loaderSettingsPanel = Ext.create('Ext.Panel', {
            width: 900
        });

        const defaults = {
            labelWidth: 200,
            width: 600,
            allowBlank: false,
            msgTarget: 'under'
        };

        this.loaderForm = Ext.create('DataHub.DataImporter.StructuredValueForm', {
            items: [
                {
                    xtype: "fieldset",
                    title: t('plugin_pimcore_datahub_data_importer_configpanel_datasource'),
                    defaults: defaults,
                    items: [
                        {
                            fieldLabel: t("plugin_pimcore_datahub_data_importer_configpanel_datasource_type"),
                            xtype: "subsettingscombo",
                            name: "type",
                            optionsNamespace: pimcore.plugin.pimcoreDataImporterBundle.configuration.components.loader,
                            settingsPanel: loaderSettingsPanel,
                            value: this.data.loaderConfig.type,
                            settingsValues: this.data.loaderConfig.settings,
                            disabled: this.disableForm,
                            initContext: {
                                configName: this.configName,
                                disableForm: this.disableForm,
                            },
                            listeners: {
                                change: function(combo, newValue, oldValue) {
                                    this.tab.fireEvent(
                                        pimcore.plugin.pimcoreDataImporterBundle.configuration.events.loaderTypeChanged,
                                        newValue
                                    );
                                }.bind(this)
                            }
                        },
                        loaderSettingsPanel
                    ]
                }
            ]
        });

        const interpreterSettingsPanel = Ext.create('Ext.Panel', {width: 900});


        this.interpreterForm = Ext.create('DataHub.DataImporter.StructuredValueForm', {
            items: [
                {
                    xtype: 'fieldset',
                    title: t('plugin_pimcore_datahub_data_importer_configpanel_file_format'),
                    defaults: defaults,
                    items: [
                        {
                            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_file_formats_type'),
                            xtype: 'subsettingscombo',
                            disabled: this.disableForm,
                            name: 'type',
                            optionsNamespace: pimcore.plugin.pimcoreDataImporterBundle.configuration.components.interpreter,
                            initContext: {
                                disableForm: this.disableForm,
                            },
                            settingsPanel: interpreterSettingsPanel,
                            value: this.data.interpreterConfig.type,
                            settingsValues: this.data.interpreterConfig.settings
                        },
                        interpreterSettingsPanel,
                    ]
                }
            ]
        });


        return Ext.create('Ext.Panel', {
            bodyStyle: 'padding:10px;',
            autoScroll: true,
            border: false,
            title: t('plugin_pimcore_datahub_data_importer_configpanel_datasource'),
            items: [
                this.loaderForm,
                this.interpreterForm
            ]
        });

    },

    buildImportSettingsTab: function() {

        const transformationResultHandler = new pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.transformationResultHandler(this.configName, this, null);
        console.log(this.disableForm);
        const importPreview = new pimcore.plugin.pimcoreDataImporterBundle.configuration.components.importPreview(this.configName, this, transformationResultHandler,  this.disableForm);
        this.importSettings = new pimcore.plugin.pimcoreDataImporterBundle.configuration.components.importSettings(this.data, this.tab, transformationResultHandler,  this.disableForm);


        return Ext.create('Ext.Panel', {
            title: t('plugin_pimcore_datahub_data_importer_configpanel_import_settings'),
            bodyStyle: 'padding:10px;',
            layout: 'border',
            items: [
                importPreview.buildImportPreviewPanel(),
                this.importSettings.buildImportSettingsPanel()
            ]
        });

    },

    buildExecutionTab: function() {
        const execution = new pimcore.plugin.pimcoreDataImporterBundle.configuration.components.execution(this.configName, this.data.executionConfig, this.tab, this.data.loaderConfig.type, this.disableForm);
        this.executionForm = execution.buildPanel();
        return this.executionForm;
    },

    buildLoggerTab: function() {
        const loggerTab = new pimcore.plugin.pimcoreDataImporterBundle.configuration.components.logTab(this.configName);
        return loggerTab.getTabPanel();
    },

    updateColumnHeaders: function() {
        Ext.Ajax.request({
            url: Routing.generate('pimcore_dataimporter_configdataobject_loadavailablecolumnheaders'),
            method: 'POST',
            params: {
                config_name: this.configName,
                current_config: this.getSaveData()
            },
            success: function (response) {
                let data = Ext.decode(response.responseText);
                this.tab.columnHeaderStore.loadData(data.columnHeaders);
            }.bind(this)
        });
    },

    getSaveData: function () {
        let saveData = {};

        saveData['general'] = this.generalForm.getValues();
        saveData['loaderConfig'] = this.loaderForm.getValues();
        saveData['interpreterConfig'] = this.interpreterForm.getValues();
        saveData['resolverConfig'] = this.importSettings.getResolverConfig();
        saveData['processingConfig'] = this.importSettings.getProcessingConfig();
        saveData['mappingConfig'] = this.importSettings.getMappingConfig();
        saveData['executionConfig'] = this.executionForm.getValues();

        return Ext.encode(saveData);
    },

    detectedChange: function ($super) {
        $super();
        if(this.tab) {
            this.tab.fireEvent(
                pimcore.plugin.pimcoreDataImporterBundle.configuration.events.configDirtyChanged,
                this.dirty
            );
        }
    },

    resetChanges: function ($super) {
        $super();

        if(this.tab && !this.dirty) {
            this.tab.fireEvent(
                pimcore.plugin.pimcoreDataImporterBundle.configuration.events.configDirtyChanged,
                this.dirty
            );
        }
    },

    isValid: function(expandPanels) {
        let isValid = this.generalForm.isValid();
        isValid = this.loaderForm.isValid() && isValid;
        if(!isValid) {
            console.log('Loader Form not valid.');
        }
        isValid = this.interpreterForm.isValid() && isValid;
        if(!isValid) {
            console.log('Interpreter Form not valid.');
        }
        isValid = this.executionForm.isValid() && isValid;
        if(!isValid) {
            console.log('Execution Form not valid.');
        }

        isValid = this.importSettings.isValid(expandPanels) && isValid;



        return isValid;
    }

});
