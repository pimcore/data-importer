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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget.manyToManyRelation");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget.manyToManyRelation = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'manyToManyRelation',
    dataApplied: false,
    dataObjectClassId: null,
    transformationResultType: null,

    isTransformationResultTypeValid: function(transformationResultType) {
        const validTypes = ['advancedDataObject', 'dataObjectArray', 'assetArray', 'advancedAssetArray'];
        return validTypes.includes(transformationResultType);
    },

    buildSettingsForm: function () {

        if (!this.form) {
            this.dataObjectClassId = this.configItemRootContainer.currentDataValues.dataObjectClassId;
            this.transformationResultType = this.initContext.mappingConfigItemContainer.currentDataValues.transformationResultType;
            this.validTransformationResultType = this.isTransformationResultTypeValid(this.initContext.mappingConfigItemContainer.currentDataValues.transformationResultType);

            const errorField = Ext.create('Ext.form.Label', {
                html: t('plugin_pimcore_datahub_data_importer_configpanel_mtm_relation_type_error'),
                style: 'color: #cf4c35'
            });

            const errorFieldExtMessage = Ext.create('Ext.form.Label', {
                html: t('plugin_pimcore_datahub_data_importer_configpanel_mtm_relation_type'),
                style: 'padding-bottom: 5px',
            });

            const fieldContainerError = Ext.create('Ext.form.FieldContainer',{
                hidden: this.validTransformationResultType,
                items: [errorField, errorFieldExtMessage]
            });

            const languageSelection = Ext.create('Ext.form.ComboBox', {
                store: pimcore.settings.websiteLanguages,
                forceSelection: true,
                fieldLabel: t('language'),
                name: this.dataNamePrefix + 'language',
                value: this.data.language,
                allowBlank: true,
                hidden: true
            });

            const overwriteMode = Ext.create('Ext.form.ComboBox', {
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_manyToManyRelation_write_settings_overwriteMode'),
                name: this.dataNamePrefix + 'overwriteMode',
                value: this.data.overwriteMode || 'replace',
                store: [
                    ['replace', t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_manyToManyRelation_write_settings_overwriteMode_replace')],
                    ['merge', t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_manyToManyRelation_write_settings_overwriteMode_merge')],
                ],
                hidden: !this.validTransformationResultType || (this.data.hasOwnProperty('writeIfTargetIsNotEmpty') ? !this.data.writeIfTargetIsNotEmpty : false)
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
                msgTarget: 'under',
                hidden: !this.validTransformationResultType
            });

            const attributeStore = Ext.create('Ext.data.JsonStore', {
                fields: ['key', 'name', 'localized'],
                listeners: {
                    dataChanged: function (store) {
                        if (!this.dataApplied) {
                            attributeSelection.setValue(this.data.fieldName);
                            if (this.form) {
                                this.form.isValid();
                            }
                            this.dataApplied = true;
                            this.setOptionsVisibility(attributeStore, attributeSelection, languageSelection, overwriteMode);
                        }

                        if (!store || !store.findRecord('key', attributeSelection.getValue())) {
                            attributeSelection.setValue(null);
                            this.form.isValid();
                        }
                    }.bind(this)
                }
            });

            attributeSelection.setStore(attributeStore);
            attributeSelection.on('change', this.setOptionsVisibility.bind(this, attributeStore, attributeSelection, languageSelection, overwriteMode));

            //register listeners for class and type changes
            this.initContext.mappingConfigItemContainer.on(pimcore.plugin.pimcoreDataImporterBundle.configuration.events.transformationResultTypeChanged, function (newType) {

                this.validTransformationResultType = this.isTransformationResultTypeValid(newType);
                this.transformationResultType = newType;

                if(this.validTransformationResultType) {
                    attributeSelection.show();
                    languageSelection.show();
                    overwriteMode.show();
                    fieldContainerCB.show();
                    fieldContainerError.hide()
                    this.initAttributeStore(attributeStore);
                } else {
                    attributeSelection.setValue('');
                    attributeSelection.hide();
                    languageSelection.hide();
                    overwriteMode.hide();
                    fieldContainerCB.hide();
                    fieldContainerError.show();
                }
            }.bind(this));
            this.configItemRootContainer.on(pimcore.plugin.pimcoreDataImporterBundle.configuration.events.classChanged,
                function (combo, newValue, oldValue) {
                    this.dataObjectClassId = newValue;
                    this.initAttributeStore(attributeStore);
                }.bind(this)
            );

            const writeIfTargetIsNotEmpty = Ext.create('Ext.form.Checkbox', {
                boxLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_manyToManyRelation_write_settings_ifTargetIsNotEmpty'),
                name: this.dataNamePrefix + 'writeIfTargetIsNotEmpty',
                value: this.data.hasOwnProperty('writeIfTargetIsNotEmpty') ? this.data.writeIfTargetIsNotEmpty : true,
                inputValue: true,
                uncheckedValue: false,
                listeners: {
                    change: function (checkbox, value) {
                        if (value) {
                            writeIfSourceIsEmpty.setReadOnly(false);
                            writeIfSourceIsEmpty.setValue(true);
                            overwriteMode.setHidden(false);
                        } else {
                            writeIfSourceIsEmpty.setReadOnly(true);
                            writeIfSourceIsEmpty.setValue(false);
                            overwriteMode.setHidden(true);
                        }
                    }
                }
            });

            const writeIfSourceIsEmpty = Ext.create('Ext.form.Checkbox', {
                boxLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_manyToManyRelation_write_settings_ifSourceIsEmpty'),
                name: this.dataNamePrefix + 'writeIfSourceIsEmpty',
                value: this.data.hasOwnProperty('writeIfSourceIsEmpty') ? this.data.writeIfSourceIsEmpty : true,
                readOnly: this.data.hasOwnProperty('writeIfTargetIsNotEmpty') ? !this.data.writeIfTargetIsNotEmpty : false,
                inputValue: true,
                uncheckedValue: false
            });

            const fieldContainerCB = Ext.create('Ext.form.FieldContainer',{
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_manyToManyRelation_write_settings_label'),
                defaultType: 'checkboxfield',
                hidden: !this.validTransformationResultType,
                items: [writeIfTargetIsNotEmpty, writeIfSourceIsEmpty]
            });

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
                    fieldContainerError,
                    attributeSelection,
                    languageSelection,
                    fieldContainerCB,
                    overwriteMode
                ]
            });

            //special loading strategy to prevent hundreds of requests when loading configurations
            this.initAttributeStore(attributeStore);
        }

        return this.form;
    },

    initAttributeStore: function (attributeStore) {
        const classId = this.dataObjectClassId;

        const transformationResultType = this.transformationResultType;

        let targetFieldCache = this.configItemRootContainer.targetFieldCacheRelations || {};

        if (targetFieldCache[classId] && targetFieldCache[classId][transformationResultType]) {

            if (targetFieldCache[classId][transformationResultType].loading) {
                setTimeout(this.initAttributeStore.bind(this, attributeStore), 400);
            } else {
                attributeStore.loadData(targetFieldCache[classId][transformationResultType].data);
            }


        } else {
            targetFieldCache = targetFieldCache || {};
            targetFieldCache[classId] = targetFieldCache[classId] || {};
            targetFieldCache[classId][transformationResultType] = {
                loading: true,
                data: null
            };
            this.configItemRootContainer.targetFieldCacheRelations = targetFieldCache;

            Ext.Ajax.request({
                url: Routing.generate('pimcore_dataimporter_configdataobject_loaddataobjectattributes'),
                method: 'GET',
                params: {
                    'class_id': classId,
                    'transformation_result_type': transformationResultType,
                    'system_read': 0,
                    'system_write': 0,
                    'load_advanced_relations': 1
                },
                success: function (response) {
                    let data = Ext.decode(response.responseText);

                    targetFieldCache[classId][transformationResultType].loading = false;
                    targetFieldCache[classId][transformationResultType].data = data.attributes;

                    attributeStore.loadData(targetFieldCache[classId][transformationResultType].data);

                }.bind(this)
            });
        }
    },
    setOptionsVisibility: function (attributeStore, attributeSelection, languageSelection) {
        const record = attributeStore.findRecord('key', attributeSelection.getValue());
        if (record) {
            languageSelection.setHidden(!record.data.localized);
        }
    }
});
