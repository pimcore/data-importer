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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.location.findParent');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.location.findParent = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'findParent',
    dataApplied: false,

    buildSettingsForm: function() {

        if(!this.form) {

            const languageSelection = Ext.create('Ext.form.ComboBox', {
                store: pimcore.settings.websiteLanguages,
                forceSelection: true,
                fieldLabel: t('language'),
                name: this.dataNamePrefix + 'attributeLanguage',
                value: this.data.attributeLanguage,
                allowBlank: true,
                hidden: true
            });

            const attributeName = Ext.create('Ext.form.ComboBox', {
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_attribute_name'),
                name: this.dataNamePrefix + 'attributeName',
                hidden: this.data.findStrategy !== 'attribute',
                allowBlank: true, // this.data.findStrategy !== 'attribute',
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
                        class_id: this.data.attributeDataObjectClassId,
                        system_read: 1
                    },
                    url: Routing.generate('pimcore_dataimporter_configdataobject_loaddataobjectattributes'),
                    reader: {
                        type: 'json',
                        rootProperty: 'attributes'
                    }
                },

                listeners: {
                    dataChanged: function(store) {
                        if(!this.dataApplied) {
                            attributeName.setValue(this.data.attributeName);
                            this.form.isValid();
                            this.dataApplied = true;
                            this.setLanguageVisibility(attributeStore, attributeName, languageSelection);
                        }
                    }.bind(this)
                }
            });

            attributeName.setStore(attributeStore);
            attributeName.on('change', this.setLanguageVisibility.bind(this, attributeStore, attributeName, languageSelection));


            const attributeDataObjectClassId = Ext.create('Ext.form.field.ComboBox', {
                typeAhead: true,
                triggerAction: 'all',
                store: pimcore.globalmanager.get('object_types_store'),
                valueField: 'id',
                displayField: 'text',
                listWidth: 'auto',
                fieldLabel: t('class'),
                name: this.dataNamePrefix + 'attributeDataObjectClassId',
                value:  this.data.attributeDataObjectClassId,
                hidden: this.data.findStrategy !== 'attribute',
                allowBlank: true, // this.data.findStrategy !== 'attribute',
                forceSelection: true,
                listeners: {
                    change: function(combo, newValue, oldValue) {
                        attributeStore.proxy.setExtraParam('class_id', newValue);
                        attributeStore.load();
                    }
                }
            });




            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
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
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_find_strategy'),
                        name: this.dataNamePrefix + 'findStrategy',
                        value: this.data.findStrategy,
                        store: [
                            ['id', t('plugin_pimcore_datahub_data_importer_configpanel_find_strategy_id')],
                            ['path', t('plugin_pimcore_datahub_data_importer_configpanel_find_strategy_path')],
                            ['attribute', t('plugin_pimcore_datahub_data_importer_configpanel_find_strategy_attribute')]
                        ],
                        listeners: {
                            change: function(combo, strategy) {
                                const attributeFields = [attributeDataObjectClassId, attributeName];
                                if(strategy === 'attribute') {
                                    attributeFields.forEach(function(item) {
                                        item.setHidden(false);
                                    });
                                } else {
                                    attributeFields.forEach(function(item) {
                                        item.setValue('');
                                        item.setHidden(true);
                                    });
                                }
                            }
                        }
                    },
                    attributeDataObjectClassId,
                    attributeName,
                    languageSelection,
                    {
                        xtype: 'combo',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_data_source_index'),
                        name: this.dataNamePrefix + 'dataSourceIndex',
                        value: this.data.dataSourceIndex,
                        store: this.configItemRootContainer.columnHeaderStore,
                        displayField: 'label',
                        valueField: 'dataIndex',
                        forceSelection: false,
                        queryMode: 'local',
                        triggerOnClick: false
                    },{
                        xtype: 'textfield',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_fallback_path'),
                        name: this.dataNamePrefix + 'fallbackPath',
                        value: this.data.fallbackPath
                    }
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