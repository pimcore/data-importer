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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget.imageGalleryAppender");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget.imageGalleryAppender = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.datatarget.direct, {

    type: 'imageGalleryAppender',
    buildSettingsForm: function() {

        var parentForm =  Object.getPrototypeOf(Object.getPrototypeOf(this)).buildSettingsForm.call(this);

        const includeDuplicates = Ext.create('Ext.form.Checkbox', {
            boxLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_imageGalleryAppender_ignore_duplicates'),
            name: this.dataNamePrefix + 'ignoreDuplicates',
            value: this.data.hasOwnProperty('ignoreDuplicates') ? this.data.ignoreDuplicates : false,
            uncheckedValue: false,
            inputValue: true
        });

        const dupForm = Ext.create('Ext.form.FieldContainer', {
            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_dataTarget.type_imageGalleryAppender_duplicates_label'),
            defaultType: 'checkboxfield',
            items: [includeDuplicates],
            labelWidth: 120,
            width: 500
        });

        parentForm.items.add(dupForm);

        //the overwrite checkboxes don't make sense in this context, so we can hide them
        const overwriteContainer = Ext.ComponentQuery.query("fieldcontainer[cls=dataimporter-direct-overwrite-container]", parentForm)[0];
        overwriteContainer.hide();

        return parentForm;
    }

});