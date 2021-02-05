
pimcore.registerNS('pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.mappingConfiguration');
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.mappingConfiguration = Class.create({


    configItemRootContainer: null,

    initialize: function(data, configItemRootContainer, transformationResultHandler) {
        this.mappingConfigData = data || [];

        this.configItemRootContainer = configItemRootContainer;
        this.transformationResultHandler = transformationResultHandler;
    },

    buildMappingsPanel: function() {

        if(!this.panel) {

            this.panel = Ext.create('Ext.Panel', {
                scrollable: true,
                tbar: {
                    items: [
                        {
                            text: t('add'),
                            iconCls: 'pimcore_icon_add',
                            handler: function() {
                                this.collapseAll();
                                this.addItem({label: 'new column'}, false, true);
                            }.bind(this)
                        }, '->', {
                            text: t('plugin_pimcore_datahub_batch_import_configpanel_mapping_collapse_all'),
                            iconCls: 'plugin_pimcore_datahub_icon_collapse',
                            handler: this.collapseAll.bind(this)
                        }, {
                            text: t('plugin_pimcore_datahub_batch_import_configpanel_mapping_autofill'),
                            iconCls: 'plugin_pimcore_datahub_icon_wizard',
                            handler: function() {
                                //get all fields from preview
                                let allDataIndices = [];
                                this.configItemRootContainer.columnHeaderStore.each(item => allDataIndices.push(item.data.dataIndex));

                                //get all fields from mappings
                                let usedDataIndices = [];
                                const values = this.getValues();
                                values.forEach(item => usedDataIndices = usedDataIndices.concat(item.dataSourceIndex));

                                //calculate missing fields and add them to panel
                                let missingDataIndices = allDataIndices.filter(item => !usedDataIndices.includes(item));
                                missingDataIndices.forEach(function(item) {
                                    const storeItem = this.configItemRootContainer.columnHeaderStore.getById(item);
                                    let data = {
                                        label: storeItem ? storeItem.data.label : item,
                                        dataSourceIndex: [item]
                                    };

                                    let mappingConfigurationItem = this.addItem(data, false);
                                    mappingConfigurationItem.updateTransformationResultPreview();

                                }.bind(this));

                            }.bind(this)
                        }
                    ]
                },
            });

            this.mappingConfigData.forEach(function(mappingItemData, index) {
                this.addItem(mappingItemData, true);
            }.bind(this));

            // this.panel.updateLayout();
        }

        return this.panel;

    },

    collapseAll: function() {
        this.panel.items.items.forEach(function(item) {
            item.collapse();
        });
    },

    addItem: function(mappingItemData, collapsed, scrollToBottom) {
        const mappingConfigurationItem = new pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.mappingConfigurationItem(mappingItemData, this.configItemRootContainer, this.transformationResultHandler);
        this.panel.add(mappingConfigurationItem.buildMappingConfigurationItem(collapsed));
        mappingConfigurationItem.recalculateTransformationResultType();
        if(scrollToBottom) {
            this.panel.getScrollable().scrollTo(0, 9999, false);
        }
        return mappingConfigurationItem;
    },

    getValues: function() {

        let mappingConfigData = [];
        this.panel.items.items.forEach(function(item) {
            mappingConfigData.push(item.itemImplementation.getValues());
        });

        return mappingConfigData;
    },

    isValid: function(expandPanels) {
        let isValid = true;
        this.panel.items.items.forEach(function(item) {
            isValid = item.isValid() && isValid;

            if(!item.isValid()) {
                item.setIconCls('pimcore_icon_warning');
                if(expandPanels) {
                    item.expand();
                }
                const fields = item.form.getFields();
                fields.each(function(field) {
                    if(!field.isValid()) {
                        console.log(field.getName());
                        console.log(field.getErrors());
                    }
                });                
            } else {
                item.setIconCls('');
            }

        });

        return isValid;
    }

});
