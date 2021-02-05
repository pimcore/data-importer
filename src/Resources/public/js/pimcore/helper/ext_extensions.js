
Ext.define('DataHub.BatchImport.SubSettingsComboBox', {
    extend: 'Ext.form.field.ComboBox',

    alias: ['widget.subsettingscombo'],

    mode: "local",
    editable: false,
    triggerAction: "all",

    // define namespace from with options should be loaded
    optionsNamespace: {},

    // define blacklist of types that should be ignored
    optionsBlackList: [],

    // panel where settings fields should be loaded into
    settingsPanel: {},

    // values for init settings fields
    settingsValues: {},

    // prefix for names of settings fields
    settingsNamePrefix: 'settings',

    // root container of config - can be used to fire and listen for events
    configItemRootContainer: null,

    // context for initializing sub settings - e.g. passing additional init values, etc.
    initContext: null,

    initComponent: function() {
        var me = this;

        var dataTypesStore = [];
        for(let optionType in me.optionsNamespace) {

            if(!this.optionsBlackList.includes(optionType)) {
                dataTypesStore.push([
                    optionType,
                    t('plugin_pimcore_datahub_batch_import_configpanel_' + this.name + '_' + optionType)
                ]);
            }

        }

        me.store = dataTypesStore;

        me.callParent();

        me.on('added', function(combo) {
            this.updateSettingsPanel(combo.getValue());
        }.bind(this));

        me.on('change', function(combo, newValue, oldValue) {
            this.updateSettingsPanel(newValue);
        }.bind(this));

    },

    updateSettingsPanel: function(optionType) {
        this.settingsPanel.removeAll();
        if(optionType) {
            const typeInstance = new this.optionsNamespace[optionType](
                this.settingsValues || {},
                this.settingsNamePrefix,
                this.configItemRootContainer,
                this.initContext
            );
            const subPanel = typeInstance.buildSettingsForm();
            if(subPanel) {
                this.settingsPanel.add(subPanel);
                subPanel.isValid();
            }
        }
    }

});


Ext.define('DataHub.BatchImport.StructuredValueForm', {
    extend: 'Ext.form.FormPanel',
    alias: ['widget.structuredvalueform'],
    getValues: function() {
        const me = this;
        const values = me.callParent();
        let nestedValues = {};

        for(let key in values) {
            let parts = key.split('.');

            let subLevel = nestedValues;
            let currentPath = '';

            parts.forEach(function(item) {

                currentPath = currentPath + item;
                if(values[currentPath] === undefined) {
                    subLevel[item] = subLevel[item] || {};
                } else {
                    subLevel[item] = values[currentPath];
                }
                subLevel = subLevel[item];
                currentPath = currentPath + '.';
            });
        }

        return nestedValues;
    }
});