/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS('pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.resolver.load.attribute');
pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.resolver.load.attribute = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.abstractOptionType, {

    type: 'attribute',
    dataApplied: false,

    buildSettingsForm: function() {

        if(!this.form) {
            const languageSelection = Ext.create('Ext.form.ComboBox', {
                store: pimcore.settings.websiteLanguages,
                forceSelection: true,
                fieldLabel: t('language'),
                name: this.dataNamePrefix + 'language',
                value: this.data.language,
                allowBlank: true,
                hidden: true
            });

            const attributeSelection = Ext.create('Ext.form.ComboBox', {
                fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_attribute_name'),
                name: this.dataNamePrefix + 'attributeName',
                value: this.data.attributeName,
                displayField: 'title',
                valueField: 'key',
                forceSelection: true,
                queryMode: 'local'
            });

            const attributeStore = Ext.create('Ext.data.JsonStore', {
                fields: ['key', 'name', 'localized'],
                autoLoad: true,
                proxy: {
                    type: 'ajax',
                    extraParams: {
                        class_id: this.configItemRootContainer.currentDataValues.dataObjectClassId
                    },
                    url: Routing.generate('pimcore_datahubbatchimport_configdataobject_loaddataobjectattributes'),
                    reader: {
                        type: 'json',
                        rootProperty: 'attributes'
                    }
                },

                listeners: {
                    dataChanged: function(store) {
                        if(!this.dataApplied) {
                            attributeSelection.setValue(this.data.attributeName);
                            this.form.isValid();
                            this.dataApplied = true;
                            this.setLanguageVisibility(attributeStore, attributeSelection, languageSelection);
                        }
                    }.bind(this)
                }
            });


            attributeSelection.setStore(attributeStore);
            attributeSelection.on('change', this.setLanguageVisibility.bind(this, attributeStore, attributeSelection, languageSelection));

            this.configItemRootContainer.on(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.events.classChanged,
                function(combo, newValue, oldValue) {
                    attributeStore.proxy.setExtraParam('class_id', newValue);
                    attributeStore.load();
                }
            );

            this.form = Ext.create('DataHub.BatchImport.StructuredValueForm', {
                defaults: {
                    labelWidth: 200,
                    width: 600,
                    allowBlank: false,
                    msgTarget: 'under'
                },
                border: false,
                items: [
                    {
                        xtype: 'combo',
                        fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_data_source_index'),
                        name: this.dataNamePrefix + 'dataSourceIndex',
                        value: this.data.dataSourceIndex,
                        store: this.configItemRootContainer.columnHeaderStore,
                        displayField: 'label',
                        valueField: 'dataIndex',
                        forceSelection: false,
                        queryMode: 'local',
                        triggerOnClick: false
                    },
                    attributeSelection,
                    languageSelection
                ]
            });
        }

        return this.form;
    },

    setLanguageVisibility: function(attributeStore, attributeSelection, languageSelection) {
        const record = attributeStore.findRecord('key', attributeSelection.getValue());
        if(record) {
            languageSelection.setHidden(!record.data.localized);
            if(!record.data.localized) {
                languageSelection.setValue(null);
            }
        }
    }


});