/**
* This source file is available under the terms of the
* Pimcore Open Core License (POCL)
* Full copyright and license information is available in
* LICENSE.md which is distributed with this source code.
*
*  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
*  @license    Pimcore Open Core License (POCL)
*/
pimcore.registerNS("pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.symfonyExpression");
pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.operator.symfonyExpression = Class.create(pimcore.plugin.pimcoreDataImporterBundle.configuration.components.mapping.abstractOperator, {
        type: "symfonyExpression",

        getMenuGroup: function () {
            return this.menuGroups.dataManipulation;
        },

        buildTransformationPipelineItem: function () {
            const id = Ext.id();
            if (!this.form) {
                this.form = Ext.create("DataHub.DataImporter.StructuredValueForm", {
                    operatorImplementation: this,
                    id: id,
                    style: "margin-top: 10px",
                    border: true,
                    bodyStyle: "padding: 10px;",
                    tbar: this.getTopBar(t("plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_symfonyExpression"), id, this.container),
                    items: this.getFormItems(),
                });
            }
            return this.form;
        },

        getFormItems: function () {
            const savedExpression =
                this.data.settings && this.data.settings.expression
                    ? this.data.settings.expression
                    : "";

            const infoHtml =
                '<div style="font-size:11px; padding:6px 8px; background:#f0f4f8; border:1px solid #c8d6e5; border-radius:3px; margin-bottom:6px;">' +
                '<strong style="display:block; margin-bottom:4px;">Available variables:</strong>' +
                '<div style="font-family:monospace; margin-bottom:4px;">attributes[0], attributes[1], &hellip;</div>' +
                '<div style="color:#888; font-style:italic;">Values come from the source columns selected above in the pipeline.</div>' +
                "</div>";

            return [
                {
                    xtype: "box",
                    html: infoHtml,
                },
                {
                    xtype: "textarea",
                    fieldLabel: t("plugin_pimcore_datahub_data_importer_configpanel_transformation_pipeline_symfonyExpression"),
                    labelAlign: "top",
                    name: "settings.expression",
                    value: savedExpression,
                    height: 100,
                    anchor: "100%",
                    emptyText: "e.g. attributes[0] == 'Demo Value' ? attributes[1] : null",
                    listeners: {
                        change: this.inputChangePreviewUpdate.bind(this),
                    },
                },
            ];
        },
    }
);
