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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget.classificationstore');

pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget.classificationstore = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'classificationstore',
    dataApplied: false,
    keyNameLoaded: false,
    dataObjectClassId: null,
    transformationResultType: null,

    buildSettingsForm: function() {

        if(!this.form) {
            this.dataObjectClassId = this.configItemRootContainer.currentDataValues.dataObjectClassId;
            this.transformationResultType = this.initContext.mappingConfigItemContainer.currentDataValues.transformationResultType;

            let languages = [''];
            languages = languages.concat(pimcore.settings.websiteLanguages);

            const languageSelection = Ext.create('Ext.form.ComboBox', {
                store: languages,
                forceSelection: true,
                fieldLabel: t('language'),
                name: this.dataNamePrefix + 'language',
                value: this.data.language,
                allowBlank: true,
                hidden: true
            });

            const clsKeySelectionValue = Ext.create('Ext.form.TextField', {
                name: this.dataNamePrefix + 'keyId',
                value: this.data.keyId,
                hidden: true
            });
            this.clsKeySelectionLabel = Ext.create('Ext.form.TextField', {
                name: '__ignore.' + this.dataNamePrefix + 'keyLabel',
                value: this.data.keyId,
                editable: false,
                width: 340,
                allowBlank: false,
                msgTarget: 'under'
            });

            const clsKeySelection = Ext.create('Ext.form.FieldContainer', {
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_classification_store_key'),
                layout: 'hbox',
                items: [
                    this.clsKeySelectionLabel,
                    {
                        xtype: "button",
                        iconCls: "pimcore_icon_search",
                        style: "margin-left: 5px",
                        handler: function() {

                            let searchWindow = new pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.tools.classificationStoreKeySearchWindow(
                                this.dataObjectClassId,
                                attributeSelection.getValue(),
                                this.transformationResultType,
                                function(id, groupName, keyName) {
                                    clsKeySelectionValue.setValue(id);
                                    this.updateDataKeyLabel(groupName, keyName);
                                }.bind(this)
                            );
                            searchWindow.show();
                        }.bind(this)
                    }
                ],
                width: 600,
                border: false,
                hidden: true,
                style: {
                    padding: 0
                },
                listeners: {
                    afterlayout: function() {
                        if(!this.keyNameLoaded) {

                            if(this.data.keyId) {
                                Ext.Ajax.request({
                                    url: Routing.generate('pimcore_dataimporter_configdataobject_loaddataobjectclassificationstorekeyname'),
                                    method: 'GET',
                                    params: {
                                        'key_id': this.data.keyId
                                    },
                                    success: function (response) {
                                        const data = Ext.decode(response.responseText);

                                        if(data.groupName && data.keyName) {
                                            this.updateDataKeyLabel(data.groupName, data.keyName);
                                        }

                                    }.bind(this)
                                });

                            }

                            this.keyNameLoaded = true;
                        }
                    }.bind(this)
                }
            });

            const attributeSelection = Ext.create('Ext.form.ComboBox', {
                displayField: 'title',
                valueField: 'key',
                queryMode: 'local',
                forceSelection: true,
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_fieldName'),
                name: this.dataNamePrefix + 'fieldName',
                value: this.data.fieldName,
                allowBlank: false,
                msgTarget: 'under'
            });

            const attributeStore = Ext.create('Ext.data.JsonStore', {
                fields: ['key', 'name', 'localized'],
                listeners: {
                    dataChanged: function(store) {
                        if(!this.dataApplied) {
                            attributeSelection.setValue(this.data.fieldName);
                            if(this.form) {
                                this.form.isValid();
                            }
                            this.dataApplied = true;
                            this.setLanguageVisibility(attributeStore, attributeSelection, languageSelection, clsKeySelection);
                        }

                        if(!store.findRecord('key', attributeSelection.getValue())) {
                            attributeSelection.setValue(null);
                            this.form.isValid();
                        }
                    }.bind(this)
                }
            });

            attributeSelection.setStore(attributeStore);
            attributeSelection.on('change', this.setLanguageVisibility.bind(this, attributeStore, attributeSelection, languageSelection, clsKeySelection));

            //register listeners for class and type changes
            this.initContext.mappingConfigItemContainer.on(pimcore.plugin.pimcoreDataImporterBundle.configuration.events.transformationResultTypeChanged, function(newType) {
                this.transformationResultType = newType;
                this.clsKeySelectionLabel.setValue('');
                clsKeySelectionValue.setValue('');
            }.bind(this));
            this.configItemRootContainer.on(pimcore.plugin.pimcoreDataImporterBundle.configuration.events.classChanged,
               function(combo, newValue, oldValue) {
                    this.dataObjectClassId = newValue;
                    this.initAttributeStore(attributeStore);
                }.bind(this)
            );

            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
                defaults: {
                    labelWidth: 120,
                    width: 500,
                    listeners: {
                        errorchange: this.initContext.updateValidationStateCallback
                    }
                },
                border: false,
                items: [
                    attributeSelection,
                    clsKeySelection,
                    clsKeySelectionValue,
                    languageSelection
                ]
            });

            //special loading strategy to prevent hundreds of requests when loading configurations
            this.initAttributeStore(attributeStore);
        }

        return this.form;
    },

    initAttributeStore: function(attributeStore) {

        const classId = this.dataObjectClassId;
        // const transformationResultType = this.transformationResultType;

        let classificationStoreFieldCache = this.configItemRootContainer.classificationStoreFieldCache || {};

        if(classificationStoreFieldCache[classId]) {

            if(classificationStoreFieldCache[classId].loading) {
                setTimeout(this.initAttributeStore.bind(this, attributeStore), 400);
            } else {
                attributeStore.loadData(classificationStoreFieldCache[classId].data);
            }


        } else {
            classificationStoreFieldCache = classificationStoreFieldCache || {};
            classificationStoreFieldCache[classId] = {
                loading: true,
                data: null
            };
            this.configItemRootContainer.classificationStoreFieldCache = classificationStoreFieldCache;

            Ext.Ajax.request({
                url: Routing.generate('pimcore_dataimporter_configdataobject_loaddataobjectclassificationstoreattributes'),
                method: 'GET',
                params: {
                    'class_id': classId
                },
                success: function (response) {
                    let data = Ext.decode(response.responseText);

                    classificationStoreFieldCache[classId].loading = false;
                    classificationStoreFieldCache[classId].data = data.attributes;

                    attributeStore.loadData(classificationStoreFieldCache[classId].data);

                }.bind(this)
            });
        }
    },

    setLanguageVisibility: function(attributeStore, attributeSelection, languageSelection, clsKeySelection) {
        const record = attributeStore.findRecord('key', attributeSelection.getValue());
        if(record) {
            languageSelection.setHidden(!record.data.localized);

            if(clsKeySelection) {
                clsKeySelection.show();
            }

        } else if(clsKeySelection) {
            clsKeySelection.hide();
        }
    },

    updateDataKeyLabel: function(groupName, keyName) {
        this.clsKeySelectionLabel.setValue(keyName + ' ' + t('plugin_pimcore_datahub_data_importer_configpanel_classification_key_in_group') + ' ' + groupName);
    }

});