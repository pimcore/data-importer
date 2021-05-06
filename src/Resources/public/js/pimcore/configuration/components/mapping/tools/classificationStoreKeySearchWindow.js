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

pimcore.registerNS('pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.tools.classificationStoreKeySearchWindow');

pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.tools.classificationStoreKeySearchWindow = Class.create({

    initialize: function (classId, fieldname, transformationResultType, callback) {

        this.callback = callback;

        this.searchWindow = new Ext.Window({
            title: t('search_for_key'),
            width: 850,
            height: 550,
            modal: true,
            layout: 'fit',
            items: [this.buildPanel(classId, fieldname, transformationResultType)],
            bbar: [
                '->',{
                    xtype: 'button',
                    text: t('cancel'),
                    iconCls: 'pimcore_icon_cancel',
                    handler: function () {
                        this.searchWindow.close();
                    }.bind(this)
                },{
                    xtype: 'button',
                    text: t('apply'),
                    iconCls: 'pimcore_icon_apply',
                    handler: function () {
                        var selectionModel = this.gridPanel.getSelectionModel();
                        var selectedKeys = selectionModel.getSelection();

                        if(selectedKeys.length > 0) {
                            this.callback(selectedKeys[0].data.id, selectedKeys[0].data.groupName, selectedKeys[0].data.keyName);
                        }

                        this.searchWindow.close();
                    }.bind(this)
                }]
        });
    },


    show: function() {
        this.searchWindow.show();
    },

    buildPanel: function (classId, fieldname, transformationResultType) {

        const groupFields = ['id', 'groupName', 'keyName', 'keyDescription', 'keyId', 'groupId'];

        const readerFields = [];
        for (let i = 0; i < groupFields.length; i++) {
            readerFields.push({name: groupFields[i]});
        }

        const gridColumns = [];
        gridColumns.push({
            text: 'ID',
            width: 60,
            sortable: true,
            dataIndex: 'id'
        });

        gridColumns.push({
            text: t('group'),
            flex: 1,
            sortable: true,
            dataIndex: 'groupName',
            filter: 'string',
            renderer: pimcore.helpers.grid.getTranslationColumnRenderer.bind(this)
        });

        gridColumns.push({
            text: t('name'),
            flex: 1,
            sortable: true,
            dataIndex: 'keyName',
            filter: 'string',
            renderer: pimcore.helpers.grid.getTranslationColumnRenderer.bind(this)
        });

        gridColumns.push({
            text: t('description'),
            flex: 1,
            sortable: true,
            dataIndex: 'keyDescription',
            filter: 'string',
            renderer: pimcore.helpers.grid.getTranslationColumnRenderer.bind(this)
        });

        const proxy = {
            type: 'ajax',
            url: Routing.generate('pimcore_dataimporter_configdataobject_loaddataobjectclassificationstorekeys'),
            reader: {
                type: 'json',
                rootProperty: 'data',
            },
            extraParams: {
                class_id: classId,
                field_name: fieldname,
                transformation_result_type: transformationResultType
            }
        };

        this.store = Ext.create('Ext.data.Store', {
            remoteSort: true,
            remoteFilter: true,
            autoLoad: true,
            proxy: proxy,
            fields: readerFields
        });

        const pageSize = pimcore.helpers.grid.getDefaultPageSize(-1);
        const pagingtoolbar = pimcore.helpers.grid.buildDefaultPagingToolbar(this.store, {pageSize: pageSize});

        this.gridPanel = new Ext.grid.GridPanel({
            store: this.store,
            border: false,
            columns: gridColumns,
            loadMask: true,
            columnLines: true,
            bodyCls: 'pimcore_editable_grid',
            stripeRows: true,
            selModel: Ext.create('Ext.selection.RowModel', {
                // mode: 'MULTI'
            }),
            bbar: pagingtoolbar,
            listeners: {
                rowdblclick: function (grid, record, tr, rowIndex, e, eOpts ) {
                    this.callback(record.data.id, record.data.groupName, record.data.keyName);
                    this.searchWindow.close();
                }.bind(this)
            },
            plugins: [
                'gridfilters'
            ],
            viewConfig: {
                forcefit: true
            }
        });

        return Ext.create('Ext.Panel', {
            tbar: this.getToolbar(),
            layout: 'fit',
            items: [
                this.gridPanel
            ]
        });
    },

    getToolbar: function () {

        const searchfield = Ext.create('Ext.form.TextField', {
            width: 300,
            style: 'float: left;',
            fieldLabel: t('search'),
            enableKeyEvents: true,
            listeners: {
                keypress: function(searchField, e, eOpts) {
                    if (e.getKey() === 13) {
                        this.applySearchFilter(searchField);
                    }
                }.bind(this)
            }
        });

        return {
            items: [
                '->',
                searchfield,
                {
                    xtype: 'button',
                    text: t('search'),
                    iconCls: 'pimcore_icon_search',
                    handler: this.applySearchFilter.bind(this, searchfield)
                }
            ]
        };
    },

    applySearchFilter: function (searchfield) {
        this.store.getProxy().setExtraParam('searchfilter', searchfield.getValue());
        this.store.reload();
    }


});
