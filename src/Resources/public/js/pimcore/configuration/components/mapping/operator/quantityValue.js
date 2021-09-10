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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.quantityValue");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.quantityValue = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'quantityValue',
    getFormItems: function () {
        this.data.settings = this.data.settings || {};

        const unitStore = Ext.create('Ext.data.JsonStore', {
            fields: ['unitId', 'abbreviation'],
            autoLoad: true,
            proxy: {
                type: 'ajax',
                url: Routing.generate('pimcore_dataimporter_configdataobject_loadunitdata'),
                reader: {
                    type: 'json',
                    rootProperty: 'UnitList'
                }
            },

            listeners: {
                dataChanged: function(store) {
                    //todo
                }.bind(this)
            }
        });

        const staticUnitSelect = Ext.create('Ext.form.ComboBox', {
            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_quantityValue_unit_select_label'),
            name: 'settings.staticUnitSelect',
            value: this.data.settings ? this.data.settings.staticUnitSelect : null,
            displayField: 'abbreviation',
            valueField: 'unitId',
            hidden: this.data.settings.unitSource !== 'static',
            listeners: {
                change: this.inputChangePreviewUpdate.bind(this)
            },
        });

        staticUnitSelect.setStore(unitStore);

        const unitSourceSelect = Ext.create('Ext.form.ComboBox', {
            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_quantityValue_unit_source'),
            name: 'settings.unitSourceSelect',
            value: this.data.settings ? this.data.settings.unitSource : 'id',
            forceSelection: true,
            store: [
                ['id', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_quantityValue_unit_source_id')],
                ['abbr', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_quantityValue_unit_source_abbreviation')],
                ['static', t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_quantityValue_unit_source_static')]
            ],
            listeners: {
                change: function(combo, unitSource) {
                    if(unitSource === 'static') {
                        staticUnitSelect.setHidden(false);
                    } else {
                        staticUnitSelect.setHidden(true);
                    }
                    this.inputChangePreviewUpdate();
                }.bind(this)
            }
        });
        return [
            unitSourceSelect,
            staticUnitSelect
        ];
    }
});