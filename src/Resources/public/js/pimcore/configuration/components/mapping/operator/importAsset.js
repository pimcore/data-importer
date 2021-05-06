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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.importAsset");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.importAsset = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {

    //TODO
    type: 'importAsset',

    getFormItems: function() {

        this.parentFolder = Ext.create('Ext.form.TextField', {
            name: 'settings.parentFolder',
            value: this.data.settings ? this.data.settings.parentFolder : '/',
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

                }.bind(this),
                change: this.inputChangePreviewUpdate.bind(this)
            }
        });

        let composite = Ext.create('Ext.form.FieldContainer', {
            fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_target_folder'),
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
        });


        return [
            composite,
            {
                xtype: 'checkbox',
                fieldLabel: t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_use_existing'),
                value: this.data.settings ? this.data.settings.useExisting : true,
                name: 'settings.useExisting'
            }
        ];
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
            type: ['asset'],
            subtype: {
                asset: ['folder']
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
        if (data.elementType === 'asset') {
            return data.type === 'folder';
        }
        return false;
    }


});