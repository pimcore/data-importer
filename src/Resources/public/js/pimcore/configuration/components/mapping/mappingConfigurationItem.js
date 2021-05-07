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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.mappingConfigurationItem');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.mappingConfigurationItem = Class.create({

    configItemRootContainer: null,
    transformationPipelineItems: [],

    initialize: function(data, configItemRootContainer, transformationResultHandler) {
        this.data = data || [];

        this.configItemRootContainer = configItemRootContainer;
        this.transformationResultHandler = transformationResultHandler;
    },

    buildMappingConfigurationItem: function() {

        let data = this.data;

        if(!this.form) {
            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
                bodyStyle: 'padding:10px;',
                title: data.label,
                collapsed : true,
                collapsible: true,
                titleCollapse: true,
                hideCollapseTool: true,
                headerOverCls: 'data_hub_cursor_pointer',
                cls: 'data_hub_mapping_panel',
                collapsedCls: 'data_hub_collapsed',
                defaults: {
                    labelWidth: 150
                },
                tools: [{
                    type: 'close',
                    cls: 'plugin_pimcore_datahub_icon_mapping_remove',
                    handler: function(owner, tool, event) {
                        const ownerContainer = event.container.component.ownerCt;
                        ownerContainer.remove(event.container.component, true);
                        ownerContainer.updateLayout();
                    }.bind(this)
                }]
            });
            this.form.currentDataValues = {
                transformationResultType: data.transformationResultType
            };


            const transformationResultTypeLabel = Ext.create('Ext.form.Label', {
                html: data.transformationResultType
            });
            this.transformationResultType = Ext.create('Ext.form.TextField', {
                value: data.transformationResultType,
                name: 'transformationResultType',
                hidden: true,
                listeners: {
                    change: function(field, newValue, oldValue) {
                        transformationResultTypeLabel.setHtml(newValue);
                        this.form.currentDataValues.transformationResultType = newValue;
                        this.form.fireEvent(
                            pimcore.plugin.pimcoreDataImporterBundle.configuration.events.transformationResultTypeChanged,
                            newValue
                        );
                    }.bind(this)
                }
            });

            this.transformationResultPreviewLabel = Ext.create('Ext.form.Label', {});

            this.configItemRootContainer.on(
                pimcore.plugin.pimcoreDataImporterBundle.configuration.events.transformationResultPreviewUpdated,
                this.doUpdateTransformationResultPreview.bind(this)
            );

            this.transformationPipeline = this.buildTransformationPipeline(data.transformationPipeline);

            const dataTargetSettingsPanel = Ext.create('Ext.Panel', {});
            this.form.add([
                {
                    xtype: 'textfield',
                    fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_mapping_label'),
                    name: 'label',
                    value: data.label,
                    listeners: {
                        change: function(field, newValue, oldValue) {
                            this.form.setTitle(newValue);
                        }.bind(this)
                    }
                },{
                    xtype: 'tagfield',
                    name: 'dataSourceIndex',
                    value: data.dataSourceIndex,
                    fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_mapping_source'),
                    store: this.configItemRootContainer.columnHeaderStore,
                    displayField: 'label',
                    valueField: 'dataIndex',
                    filterPickList: false,
                    queryMode: 'local',
                    forceSelection: false,
                    triggerOnClick: false,
                    createNewOnEnter: true,
                    allowBlank: false,
                    msgTarget: 'under',
                    listeners: {
                        change: function() {
                            this.recalculateTransformationResultType();
                            this.updateTransformationResultPreview();
                        }.bind(this),
                        errorchange: this.updateValidationState.bind(this)
                    }
                },
                {
                    xtype: 'fieldset',
                    title: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline'),
                    collapsible: true,
                    collapsed:  true,
                    items: [
                        this.transformationPipeline
                    ]
                },
                this.transformationResultType,
                {
                    xtype: 'fieldset',
                    title: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_result'),
                    items: [{
                        xtype: 'fieldcontainer',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_result_type'),
                        layout: 'hbox',
                        items: [
                            transformationResultTypeLabel
                        ]
                    },{
                        xtype: 'fieldcontainer',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_result_preview'),
                        layout: 'hbox',
                        items: [
                            this.transformationResultPreviewLabel
                        ]
                    }]
                },
                {
                    xtype: 'fieldset',
                    title: t('plugin_pimcore_datahub_data_importer_configpanel_data_target'),
                    defaults: {
                        labelWidth: 120,
                        width: 500
                    },
                    items: [
                        {
                            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_data_target_type'),
                            xtype: 'subsettingscombo',
                            name: 'dataTarget.type',
                            settingsNamePrefix: 'dataTarget.settings',
                            optionsNamespace: pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget,
                            settingsPanel: dataTargetSettingsPanel,
                            value: data.dataTarget ? data.dataTarget.type : '',
                            settingsValues: data.dataTarget ? data.dataTarget.settings : {},
                            configItemRootContainer: this.configItemRootContainer,
                            initContext: {
                                mappingConfigItemContainer: this.form,
                                updateValidationStateCallback: this.updateValidationState.bind(this)
                            },
                            allowBlank: false,
                            msgTarget: 'under'
                        },
                        dataTargetSettingsPanel
                    ]
                }

            ]);

            this.form.itemImplementation = this;
        }

        this.form.isValid();
        return this.form;
    },

    buildTransformationPipeline: function(data) {
        data = data || [];

        var transformationPipelineContainer = Ext.create('Ext.Panel', {});

        //TODO make that once globally, and not per mapping item?
        let addMenu = [];
        const itemTypes = Object.keys(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator);
        itemTypes.sort((item1, item2) => {
            const str1 = t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_' + item1);
            const str2 = t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_' + item2);
            return str1.localeCompare(str2);
        });

        for (let i = 0; i < itemTypes.length; i++) {
            addMenu.push({
                iconCls: 'pimcore_icon_add',
                handler: function() {
                    this.addTransformationPipelineItem(itemTypes[i], {}, transformationPipelineContainer);
                    this.recalculateTransformationResultType();
                    this.updateTransformationResultPreview();
                }.bind(this),
                text: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_' + itemTypes[i])
            });
        }

        transformationPipelineContainer.addDocked({
            xtype: 'toolbar',
            dock: 'top',
            items: [
                {
                    text: t('add'),
                    iconCls: 'pimcore_icon_add',
                    menu: addMenu
                }
            ]
        });

        data.forEach(function(item) {
            this.addTransformationPipelineItem(item.type, item, transformationPipelineContainer);
        }.bind(this));

        return transformationPipelineContainer;

    },

    addTransformationPipelineItem: function(type, data, container) {
        const item = new pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator[type](
            data, container, this.recalculateTransformationResultType.bind(this), this.updateTransformationResultPreview.bind(this)
        );
        container.add(item.buildTransformationPipelineItem());
    },

    recalculateTransformationResultType: function() {
        const currentConfig = Ext.encode(this.getValues());
        Ext.Ajax.request({
            url: Routing.generate('pimcore_dataimporter_configdataobject_calculatetransformationresulttype'),
            method: 'POST',
            params: {
                config_name: this.configItemRootContainer.configName,
                current_config: currentConfig
            },
            success: function (response) {
                let data = Ext.decode(response.responseText);
                this.transformationResultType.setValue(data);

            }.bind(this)
        });
    },

    updateTransformationResultPreview: function() {
        this.transformationResultHandler.updateData(false, this.doUpdateTransformationResultPreview.bind(this));
    },

    doUpdateTransformationResultPreview: function() {
        if(this.form.ownerCt && this.form.ownerCt.items) {
            const mappingIndex = this.form.ownerCt.items.items.indexOf(this.form);
            const transformationResultPreview = this.transformationResultHandler.getTransformationResultPreview(mappingIndex);
            this.transformationResultPreviewLabel.setHtml(transformationResultPreview);
        }
    },

    updateValidationState: function() {
        try {
            if(this.form.isValid()) {
                this.form.setIconCls('');
            } else {
                this.form.setIconCls('pimcore_icon_warning');
            }
        } catch (e) {
            console.log('Could not update validation state: ' + e);
        }
    },

    getValues: function() {
        let values = this.form.getValues();

        let transformationPipelineData = [];
        this.transformationPipeline.items.items.forEach(function(pipelineItem) {
            transformationPipelineData.push(pipelineItem.operatorImplementation.getValues());
        });
        values.transformationPipeline = transformationPipelineData;

        return values;
    }

});
