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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.importAssetComposedPath");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.importAssetComposedPath = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.importAsset, {

    //TODO
    type: 'importAssetComposedPath',

    getMenuGroup: function() {
        return this.menuGroups.loadImport;
    },

    getIconClass: function() {
        return "pimcore_icon_asset pimcore_icon_overlay_upload";
    },

    getFormItems: function() {

        const parentForm = Object.getPrototypeOf(Object.getPrototypeOf(this)).getFormItems.call(this);

        const withoutDropbox = parentForm.splice(1);

        //recreate parentFolder as simple field
        this.parentFolder = Ext.create('Ext.form.TextField', {
            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_target_folder'),
            name: 'settings.parentFolder',
            value: this.data.settings ? this.data.settings.parentFolder : '/',
            width: 500,
            allowBlank: false,
            msgTarget: 'under'
        });

        var urlProperty = Ext.create('Ext.form.TextField', {
            name: 'settings.urlPropertyName',
            value: this.data.settings ? this.data.settings.urlPropertyName : '',
            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_url_property'),
        });

        return [this.parentFolder, ...withoutDropbox, urlProperty];
    },
 
});
