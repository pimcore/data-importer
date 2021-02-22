/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

pimcore.registerNS('pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.datatarget.classificationstoreBatch');

pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.datatarget.classificationstoreBatch = Class.create(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.components.mapping.datatarget.classificationstore, {

    type: 'classificationstoreBatch',
    dataApplied: false,
    dataObjectClassId: null,
    transformationResultType: null,

    buildSettingsForm: function() {

        if(!this.form) {
            this.dataObjectClassId = this.configItemRootContainer.currentDataValues.dataObjectClassId;
            this.transformationResultType = this.initContext.mappingConfigItemContainer.currentDataValues.transformationResultType;

            const errorField = Ext.create('Ext.form.Label', {
                html: t('plugin_pimcore_datahub_batch_import_configpanel_classification_store_batch_type_error'),
                hidden: this.transformationResultType === 'array',
                style: 'color: #cf4c35'
            });

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

            const attributeSelection = Ext.create('Ext.form.ComboBox', {
                displayField: 'title',
                valueField: 'key',
                queryMode: 'local',
                forceSelection: true,
                fieldLabel: t('plugin_pimcore_datahub_batch_import_configpanel_fieldName'),
                name: this.dataNamePrefix + 'fieldName',
                value: this.data.fieldName,
                allowBlank: false,
                msgTarget: 'under',
                hidden: this.transformationResultType !== 'array'
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
                            this.setLanguageVisibility(attributeStore, attributeSelection, languageSelection);
                        }

                        if(!store.findRecord('key', attributeSelection.getValue())) {
                            attributeSelection.setValue(null);
                            this.form.isValid();
                        }
                    }.bind(this)
                }
            });

            attributeSelection.setStore(attributeStore);
            attributeSelection.on('change', this.setLanguageVisibility.bind(this, attributeStore, attributeSelection, languageSelection));

            //register listeners for class and type changes
            this.initContext.mappingConfigItemContainer.on(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.events.transformationResultTypeChanged, function(newType) {
                this.transformationResultType = newType;

                if(this.transformationResultType !== 'array') {
                    attributeSelection.setValue('');
                    attributeSelection.hide();
                    languageSelection.hide();
                    errorField.show();
                } else {
                    errorField.hide();
                    attributeSelection.show();
                    this.setLanguageVisibility.bind(this, attributeStore, attributeSelection, languageSelection);
                }

            }.bind(this));
            this.configItemRootContainer.on(pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.events.classChanged,
               function(combo, newValue, oldValue) {
                    this.dataObjectClassId = newValue;
                    this.initAttributeStore(attributeStore);
                }.bind(this)
            );

            this.form = Ext.create('DataHub.BatchImport.StructuredValueForm', {
                defaults: {
                    labelWidth: 120,
                    width: 500,
                    listeners: {
                        errorchange: this.initContext.updateValidationStateCallback
                    }
                },
                border: false,
                items: [
                    errorField,
                    {
                        html: t('plugin_pimcore_datahub_batch_import_configpanel_classification_store_batch_type'),
                        style: 'padding-bottom: 5px'
                    },
                    attributeSelection,
                    languageSelection
                ]
            });

            //special loading strategy to prevent hundreds of requests when loading configurations
            this.initAttributeStore(attributeStore);
        }

        return this.form;

    }
});