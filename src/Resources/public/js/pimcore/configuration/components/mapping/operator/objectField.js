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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.objectField");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.objectField = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    type: 'objectField',

    getMenuGroup: function() {
        return this.menuGroups.dataManipulation;
    },

    getIconClass: function() {
        return "pimcore_nav_icon_object pimcore_icon_overlay_add";
    },

    getFormItems: function() {
        return [
            {
                xtype: 'textfield',
                fieldLabel: t('attribute'),
                value: this.data.settings ? this.data.settings.attribute : '',
                name: 'settings.attribute',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                }
            },

            {
                xtype: 'textfield',
                fieldLabel: t('forward_parameter'),
                value: this.data.settings ? this.data.settings.forward_parameter : '',
                name: 'settings.forward_parameter',
                listeners: {
                    change: this.inputChangePreviewUpdate.bind(this)
                }
            },
        ];
    }

});
