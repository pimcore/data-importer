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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.location.staticPath');
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.resolver.location.staticPath = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.abstractOptionType, {

    type: 'staticPath',

    buildSettingsForm: function() {

        if(!this.form) {

            this.parentFolder = new Ext.form.TextField({
                name: this.dataNamePrefix + 'path',
                value: this.data.path || '/',
                fieldCls: 'pimcore_droptarget_input',
                width: 500,
                enableKeyEvents: true,
                allowBlank: false,
                msgTarget: 'under',
                listeners: {
                    render: function (el) {
                        // add drop zone
                        new Ext.dd.DropZone(el.getEl(), {
                            reference: this,
                            ddGroup: "element",
                            getTargetFromEvent: function (e) {
                                return this.reference.parentFolder.getEl();
                            },

                            onNodeOver: function (target, dd, e, data) {
                                if (data.records.length === 1 && this.dndAllowed(data.records[0].data)) {
                                    return Ext.dd.DropZone.prototype.dropAllowed;
                                }
                            }.bind(this),

                            onNodeDrop: this.onNodeDrop.bind(this)
                        });

                        el.getEl().on("contextmenu", this.onContextMenu.bind(this));

                    }.bind(this)
                }
            });

            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
                defaults: {
                    labelWidth: 200,
                    width: 600,
                    allowBlank: false,
                    msgTarget: 'under'
                },
                border: false,
                items: [
                    // {
                    //     xtype: 'textfield',
                    //     fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_path'),
                    //     name: this.dataNamePrefix + 'path',
                    //     value: this.data.path || '/'
                    // }
                    {
                        xtype: 'fieldcontainer',
                        fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_path'),
                        layout: 'hbox',
                        items: [
                            this.parentFolder,
                            {
                                xtype: "button",
                                iconCls: "pimcore_icon_delete",
                                style: "margin-left: 5px",
                                handler: this.empty.bind(this)
                            },{
                                xtype: "button",
                                iconCls: "pimcore_icon_search",
                                style: "margin-left: 5px",
                                handler: this.openSearchEditor.bind(this)
                            }
                        ],
                        width: 900,
                        componentCls: "object_field object_field_type_manyToOneRelation",
                        border: false,
                        style: {
                            padding: 0
                        }
                    }
                ]
            });
        }

        return this.form;
    },

    onNodeDrop: function (target, dd, e, data) {

        if(!pimcore.helpers.dragAndDropValidateSingleItem(data)) {
            return false;
        }

        data = data.records[0].data;

        if (this.dndAllowed(data)) {
            this.parentFolder.setValue(data.path);
            return true;
        } else {
            return false;
        }
    },

    onContextMenu: function (e) {

        var menu = new Ext.menu.Menu();
        menu.add(new Ext.menu.Item({
            text: t('empty'),
            iconCls: "pimcore_icon_delete",
            handler: function (item) {
                item.parentMenu.destroy();
                this.empty();
            }.bind(this)
        }));

        menu.add(new Ext.menu.Item({
            text: t('search'),
            iconCls: "pimcore_icon_search",
            handler: function (item) {
                item.parentMenu.destroy();
                this.openSearchEditor();
            }.bind(this)
        }));

        menu.showAt(e.getXY());

        e.stopEvent();
    },

    openSearchEditor: function () {
        pimcore.helpers.itemselector(false, this.addDataFromSelector.bind(this), {
            type: ['object'],
            subtype: {
                object: ['folder']
            },
            specific: {}
        }, {});
    },

    addDataFromSelector: function (data) {
        this.parentFolder.setValue(data.fullpath);
    },

    empty: function () {
        this.parentFolder.setValue("");
    },

    dndAllowed: function (data) {
        if (data.elementType === 'object') {
            return data.type === 'folder';
        }
        return false;
    }



});