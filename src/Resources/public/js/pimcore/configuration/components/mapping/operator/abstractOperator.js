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

pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator = Class.create({

    type: 'abstract',

    data: {},
    container: null,
    transformationResultTypeChangeCallback: null,
    transformationResultPreviewChangeCallback: null,

    initialize: function(data, container, transformationResultTypeChangeCallback, transformationResultPreviewChangeCallback) {
        this.data = data;
        this.container = container;
        this.transformationResultTypeChangeCallback = transformationResultTypeChangeCallback;
        this.transformationResultPreviewChangeCallback = transformationResultPreviewChangeCallback;
    },

    getTopBar: function (name, index, parent) {
        return [{
            xtype: "tbtext",
            text: "<b>" + name + "</b>"
        }, "-", {
            iconCls: 'pimcore_icon_up',
            handler: function (blockId, parent) {

                const container = parent;
                const blockElement = Ext.getCmp(blockId);

                container.moveBefore(blockElement, blockElement.previousSibling());

                this.executeTransformationResultCallbacks();
            }.bind(this, index, parent)
        }, {
            iconCls: 'pimcore_icon_down',
            handler: function (blockId, parent) {

                const container = parent;
                const blockElement = Ext.getCmp(blockId);

                container.moveAfter(blockElement, blockElement.nextSibling());

                this.executeTransformationResultCallbacks();
            }.bind(this, index, parent)
        }, '->', {
            iconCls: 'pimcore_icon_delete',
            handler: function (index, parent) {
                parent.remove(Ext.getCmp(index));

                this.executeTransformationResultCallbacks();
            }.bind(this, index, parent)
        }];
    },

    buildTransformationPipelineItem: function() {
        var myId = Ext.id();
        if(!this.form) {
            this.form = Ext.create('DataHub.DataImporter.StructuredValueForm', {
                operatorImplementation: this,
                id: myId,
                style: "margin-top: 10px",
                border: true,
                bodyStyle: "padding: 10px;",
                tbar: this.getTopBar(t('plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_' + this.type), myId, this.container),
                items: this.getFormItems()
            });
        }

        return this.form;
    },

    getFormItems: function() {
        return []
    },

    getValues: function() {
        let values = this.form.getValues();
        values.type = this.type;
        return values;
    },

    executeTransformationResultCallbacks: function() {
        if(this.transformationResultPreviewChangeCallback) {
            this.transformationResultPreviewChangeCallback();
        }
        if(this.transformationResultTypeChangeCallback) {
            this.transformationResultTypeChangeCallback();
        }
    },

    inputChangePreviewUpdate: function() {
        if(this.inputChangePreviewTimeout) {
            clearTimeout(this.inputChangePreviewTimeout);
        }
        this.inputChangePreviewTimeout = setTimeout(function() {
            this.transformationResultPreviewChangeCallback();
        }.bind(this), 1000);
    }
});