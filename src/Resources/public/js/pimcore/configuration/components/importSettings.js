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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.importSettings');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.importSettings = Class.create({

    configItemRootContainer: null,
    transformationResultHandler: null,

    initialize: function(data, configItemRootContainer, transformationResultHandler) {
        this.resolverConfigData = data.resolverConfig;
        this.processingConfigData = data.processingConfig;
        this.mappingConfigData = data.mappingConfig;

        this.configItemRootContainer = configItemRootContainer;
        this.transformationResultHandler = transformationResultHandler;
    },

    buildImportSettingsPanel: function() {

        if(!this.panel) {
            this.panel = Ext.create('Ext.TabPanel', {
                region: 'center',
                title: t('plugin_pimcore_datahub_data_importer_configpanel_import_settings'),
                items: [
                    this.buildResolverTab(),
                    this.buildProcessingTab(),
                    this.buildMappingsTab()
                ]
            });
        }

        return this.panel;

    },

    buildResolverTab: function() {

        const panelDefaults = {
            labelWidth: 200,
            width: 600,
            allowBlank: false,
            msgTarget: 'under'
        };

        const loadingStrategySettingsPanel = Ext.create('Ext.Panel', {width: 800});
        const createLocationStrategySettingsPanel = Ext.create('Ext.Panel', {width: 800});
        const updateLocationStrategySettingsPanel = Ext.create('Ext.Panel', {width: 800});
        const publishingStrategySettingsPanel = Ext.create('Ext.Panel', {width: 800});

        this.configItemRootContainer.currentDataValues = this.configItemRootContainer.currentDataValues || {};
        this.configItemRootContainer.currentDataValues.dataObjectClassId = this.resolverConfigData.dataObjectClassId;

        this.resolverForm = Ext.create('DataHub.DataImporter.StructuredValueForm', {
            bodyStyle: 'padding:10px;',
            defaults: panelDefaults,
            scrollable: true,
            title: t('plugin_pimcore_datahub_data_importer_configpanel_resolver'),
            items: [
                {
                    xtype: 'textfield',
                    name: 'elementType',
                    value: 'dataObject',
                    hidden: true
                },{
                    xtype: 'combo',
                    typeAhead: true,
                    triggerAction: 'all',
                    store: pimcore.globalmanager.get('object_types_store'),
                    valueField: 'id',
                    displayField: 'text',
                    listWidth: 'auto',
                    fieldLabel: t('class'),
                    name: 'dataObjectClassId',
                    value: this.resolverConfigData.dataObjectClassId,
                    forceSelection: true,
                    listeners: {
                        change: function(combo, newValue, oldValue) {
                            this.configItemRootContainer.currentDataValues.dataObjectClassId = newValue;
                            this.configItemRootContainer.fireEvent(
                                pimcore.plugin.pimcoreDataImporterBundle.configuration.events.classChanged,
                                combo,
                                newValue,
                                oldValue
                            );
                        }.bind(this),
                        added: function(combo) {
                            this.configItemRootContainer.fireEvent(
                                pimcore.plugin.pimcoreDataImporterBundle.configuration.events.classInit,
                                combo,
                                combo.getValue(),
                            );
                        }.bind(this)
                    }
                },
                {
                    xtype: 'fieldset',
                    title: t('plugin_pimcore_datahub_data_importer_configpanel_element_loading'),
                    defaults: panelDefaults,
                    width: 850,
                    items: [
                        {
                            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_loading_strategy'),
                            xtype: 'subsettingscombo',
                            name: 'loadingStrategy.type',
                            settingsNamePrefix: 'loadingStrategy.settings',
                            optionsNamespace: pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.load,
                            settingsPanel: loadingStrategySettingsPanel,
                            value: this.resolverConfigData.loadingStrategy.type || 'notLoad',
                            settingsValues: this.resolverConfigData.loadingStrategy ? this.resolverConfigData.loadingStrategy.settings : {},
                            configItemRootContainer: this.configItemRootContainer
                        },
                        loadingStrategySettingsPanel,
                    ]
                },
                {
                    xtype: 'fieldset',
                    width: 850,
                    title: t('plugin_pimcore_datahub_data_importer_configpanel_element_creation'),
                    defaults: panelDefaults,
                    items: [
                        {
                            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_create_location_strategy'),
                            xtype: 'subsettingscombo',
                            name: 'createLocationStrategy.type',
                            settingsNamePrefix: 'createLocationStrategy.settings',
                            optionsNamespace: pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.location,
                            optionsBlackList: ['noChange'],
                            settingsPanel: createLocationStrategySettingsPanel,
                            value: this.resolverConfigData.createLocationStrategy.type || 'staticPath',
                            settingsValues: this.resolverConfigData.createLocationStrategy ? this.resolverConfigData.createLocationStrategy.settings : {},
                            configItemRootContainer: this.configItemRootContainer
                        },
                        createLocationStrategySettingsPanel
                    ]
                },
                {
                    xtype: 'fieldset',
                    width: 850,
                    title: t('plugin_pimcore_datahub_data_importer_configpanel_element_location_update'),
                    defaults: panelDefaults,
                    items: [
                        {
                            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_update_location_strategy'),
                            xtype: 'subsettingscombo',
                            name: 'locationUpdateStrategy.type',
                            settingsNamePrefix: 'locationUpdateStrategy.settings',
                            optionsNamespace: pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.location,
                            settingsPanel: updateLocationStrategySettingsPanel,
                            value: this.resolverConfigData.locationUpdateStrategy.type || 'noChange',
                            settingsValues: this.resolverConfigData.locationUpdateStrategy ? this.resolverConfigData.locationUpdateStrategy.settings : {},
                            configItemRootContainer: this.configItemRootContainer
                        },
                        updateLocationStrategySettingsPanel
                    ]
                },
                {
                    xtype: 'fieldset',
                    width: 850,
                    title: t('plugin_pimcore_datahub_data_importer_configpanel_element_publishing'),
                    defaults: panelDefaults,
                    items: [
                        {
                            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_publish_strategy'),
                            xtype: 'subsettingscombo',
                            name: 'publishingStrategy.type',
                            settingsNamePrefix: 'publishingStrategy.settings',
                            optionsNamespace: pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.publish,
                            settingsPanel: publishingStrategySettingsPanel,
                            value: this.resolverConfigData.publishingStrategy.type || 'noChangeUnpublishNew',
                            settingsValues: this.resolverConfigData.publishingStrategy ? this.resolverConfigData.publishingStrategy.settings : {},
                            configItemRootContainer: this.configItemRootContainer
                        },
                        publishingStrategySettingsPanel
                    ]
                },

            ]
        });

        return this.resolverForm;
    },

    getResolverConfig: function() {

        if(this.resolverForm) {
            return this.resolverForm.getValues();
        }
        return this.resolverConfigData;

    },

    buildProcessingTab: function() {

        const panelDefaults = {
            labelWidth: 200,
            width: 600,
            allowBlank: false,
            msgTarget: 'under'
        };

        const doDeltaCheckCheckbox = Ext.create('Ext.form.field.Checkbox', {
            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_delta_check'),
            name: 'doDeltaCheck',
            disabled: (!this.processingConfigData.idDataIndex || 0 === this.processingConfigData.idDataIndex.length),
            inputValue: true,
            value: this.processingConfigData.hasOwnProperty('doDeltaCheck') ? this.processingConfigData.doDeltaCheck : false
        });
        const doCleanup = Ext.create('Ext.form.field.Checkbox', {
            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_do_cleanup'),
            name: 'cleanup.doCleanup',
            disabled: (!this.processingConfigData.idDataIndex || 0 === this.processingConfigData.idDataIndex.length),
            inputValue: true,
            value: this.processingConfigData.cleanup && this.processingConfigData.cleanup.hasOwnProperty('doCleanup') ? this.processingConfigData.cleanup.doCleanup : false
        });
        const cleanupSettingsPanel = Ext.create('Ext.Panel', {width: 900});
        const cleanupStrategy = Ext.create('DataHub.DataImporter.SubSettingsComboBox', {
            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_cleanup_strategy'),
            name: 'cleanup.strategy',
            disabled: (!this.processingConfigData.idDataIndex || 0 === this.processingConfigData.idDataIndex.length),
            optionsNamespace: pimcore.plugin.pimcoreDataImporterBundle.configuration.components.cleanup,
            settingsPanel: cleanupSettingsPanel,
            value: this.processingConfigData.cleanup ? this.processingConfigData.cleanup.strategy : ''
        });


        this.processingForm = Ext.create('DataHub.DataImporter.StructuredValueForm', {
            bodyStyle: 'padding:10px;',
            defaults: panelDefaults,
            scrollable: true,
            title: t('plugin_pimcore_datahub_data_importer_configpanel_processing_settings'),
            items: [
                {
                    xtype: 'combo',
                    name: 'executionType',
                    fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_execution_type'),
                    store: [
                        ['sequential', t('plugin_pimcore_datahub_data_importer_configpanel_execution_type_sequential')],
                        ['parallel', t('plugin_pimcore_datahub_data_importer_configpanel_execution_type_parallel')]
                    ],
                    value: this.processingConfigData.executionType || 'parallel',
                    mode: 'local',
                    editable: false,
                    triggerAction: 'all'
                },
                {
                    xtype: 'checkbox',
                    fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_archive_import_file'),
                    name: 'doArchiveImportFile',
                    inputValue: true,
                    value: this.processingConfigData.hasOwnProperty('doArchiveImportFile') ? this.processingConfigData.doArchiveImportFile : false
                },
                {
                    xtype: 'combo',
                    fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_id_data_index'),
                    name: 'idDataIndex',
                    value: this.processingConfigData.idDataIndex,
                    store: this.configItemRootContainer.columnHeaderStore,
                    displayField: 'label',
                    valueField: 'dataIndex',
                    forceSelection: false,
                    queryMode: 'local',
                    triggerOnClick: false,
                    allowBlank: true,
                    listeners: {
                        change: function(textfield, newValue, oldValue) {
                            const hasNoIdField = (!newValue || 0 === newValue.length);
                            doDeltaCheckCheckbox.setDisabled(hasNoIdField);
                            doCleanup.setDisabled(hasNoIdField);
                            cleanupStrategy.setDisabled(hasNoIdField);
                            if(hasNoIdField) {
                                doDeltaCheckCheckbox.setValue(false);
                                doCleanup.setValue(false);
                                cleanupStrategy.setValue('');
                            }
                        }
                    }
                },
                doDeltaCheckCheckbox,
                doCleanup,
                cleanupStrategy
            ]
        });

        return this.processingForm;
    },


    getProcessingConfig: function() {

        if(this.processingForm) {
            return this.processingForm.getValues();
        }
        return this.processingConfigData;

    },

    buildMappingsTab: function() {

        this.mappingConfiguration = new pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.mappingConfiguration(
            this.mappingConfigData,
            this.configItemRootContainer,
            this.transformationResultHandler
        );

        const panel = Ext.create('Ext.Panel', {
            title: t('plugin_pimcore_datahub_data_importer_configpanel_mappings'),
            scrollable: false,
            layout: 'fit',
            items: [
                this.mappingConfiguration.buildMappingsPanel()
            ]
        });
        // panel.updateLayout();
        // pimcore.layout.refresh();
        return panel;
    },

    getMappingConfig: function() {

        if(this.mappingConfiguration) {
            return this.mappingConfiguration.getValues();
        }
        return this.mappingConfigData;

    },


    isValid: function(expandPanels) {

        let isValid = true;
        if(this.resolverForm && !this.resolverForm.isValid()) {
            isValid = false;
            const fields = this.resolverForm.form.getFields();
            fields.each(function(field) {
                if(!field.isValid()) {
                    console.log(field.getName());
                    console.log(field.getErrors());
                }
            });
        }

        if(this.processingForm && !this.processingForm.isValid()) {
            isValid = false;
            const fields = this.processingForm.form.getFields();
            fields.each(function(field) {
                if(!field.isValid()) {
                    console.log(field.getName());
                    console.log(field.getErrors());
                }
            });
        }

        if(this.mappingConfiguration) {
            isValid = this.mappingConfiguration.isValid(expandPanels) && isValid;
            if(!isValid) {
                console.log('Mapping Config not valid.');
            }
        }

        return isValid;
    }

});
