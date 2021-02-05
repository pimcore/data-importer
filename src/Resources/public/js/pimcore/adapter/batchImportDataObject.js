/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

pimcore.registerNS("pimcore.plugin.datahub.adapter.batchImportDataObject");
pimcore.plugin.datahub.adapter.batchImportDataObject = Class.create(pimcore.plugin.datahub.adapter.graphql, {

    createConfigPanel: function(data) {
        let fieldPanel = new pimcore.plugin.pimcoreDataHubBatchImportBundle.configuration.configItemDataObject(data, this);
    },

    openConfiguration: function (id) {
        var existingPanel = Ext.getCmp("plugin_pimcore_datahub_configpanel_panel_" + id);
        if (existingPanel) {
            this.configPanel.editPanel.setActiveTab(existingPanel);
            return;
        }

        Ext.Ajax.request({
            url: Routing.generate('pimcore_datahubbatchimport_configdataobject_get'),
            params: {
                name: id
            },
            success: function (response) {
                let data = Ext.decode(response.responseText);
                this.createConfigPanel(data);
                pimcore.layout.refresh();
            }.bind(this)
        });
    }
});
