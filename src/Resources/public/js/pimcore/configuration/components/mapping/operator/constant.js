pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.constant");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.constant = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'constant',

    getMenuGroup: function() {
        return this.menuGroups.dataManipulation;
    },

    getFormItems: function() {
        return [
            {
                xtype: 'textfield',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_constant'),
                value: this.data.settings ? this.data.settings.constant : '',
                name: 'settings.constant',
            }
        ];
    }

});