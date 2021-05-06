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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.logTab');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.logTab = Class.create(pimcore.log.admin, {

    componentPrefix: 'DATA-IMPORTER ',

    initialize: function($super, configName) {
        $super({
            localMode: true,
            searchParams: {
                component: this.componentPrefix + configName
            }
        });
    },

    getTabPanel: function($super) {
        $super();

        this.panel.setTitle(t('plugin_pimcore_datahub_data_importer_configpanel_logs'));
        this.panel.setIconCls('');

        const fieldset = this.searchpanel.items.items[0];
        const componentCombo = fieldset.child('field[name=component]');
        fieldset.remove(componentCombo, false);
        fieldset.add({
            xtype: 'textfield',
            hidden: true,
            name: 'component',
            value: this.searchParams.component
        });

        const relatedObjectField = fieldset.child('field[name=relatedobject]');
        relatedObjectField.setDisabled(false);

        this.store.getProxy().setExtraParam('component', this.searchParams.component);

        return this.panel;
    },

    clearValues: function(){
        this.searchpanel.getForm().reset();

        this.searchParams.fromDate = null;
        this.searchParams.fromTime = null;
        this.searchParams.toDate = null;
        this.searchParams.toTime = null;
        this.searchParams.priority = null;
        this.searchParams.message = null;
        this.searchParams.relatedobject = null;
        this.searchParams.pid = null;
        this.store.baseParams = this.searchParams;
        this.store.reload({
            params: this.searchParams
        });
    },

    find: function() {
        var formValues = this.searchpanel.getForm().getFieldValues();

        this.searchParams.fromDate = this.fromDate.getValue();
        this.searchParams.fromTime = this.fromTime.getValue();
        this.searchParams.toDate = this.toDate.getValue();
        this.searchParams.toTime = this.toTime.getValue();
        this.searchParams.priority = formValues.priority;
        this.searchParams.component = formValues.component;
        this.searchParams.relatedobject = formValues.relatedobject;
        this.searchParams.message = formValues.message;
        this.searchParams.pid = formValues.pid;

        var proxy = this.store.getProxy();
        proxy.extraParams = this.searchParams;
        this.pagingToolbar.moveFirst();
    }

});
