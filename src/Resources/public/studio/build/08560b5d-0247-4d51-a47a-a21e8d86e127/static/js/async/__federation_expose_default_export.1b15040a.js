/*! For license information please see __federation_expose_default_export.1b15040a.js.LICENSE.txt */
"use strict";(self.webpackChunkpimcore_dataimporter_bundle=self.webpackChunkpimcore_dataimporter_bundle||[]).push([["525"],{3648(e,t,a){a.r(t),a.d(t,{DataImporterPlugin:()=>aH});var r=a(2977),i=a(3729);let o="DataImporter/DynamicTypes/Adapter/DataImporterDataObject",n="DataImporter/DynamicTypes/Transformer/Registry",l="DataImporter/DynamicTypes/Transformer/Trim",s="DataImporter/DynamicTypes/Transformer/Combine",d="DataImporter/DynamicTypes/Transformer/StaticText",p="DataImporter/DynamicTypes/Transformer/StringReplace",c="DataImporter/DynamicTypes/Transformer/Date",m="DataImporter/DynamicTypes/Transformer/Numeric",u="DataImporter/DynamicTypes/Transformer/Explode",g="DataImporter/DynamicTypes/Transformer/ConditionalConversion",h="DataImporter/DynamicTypes/Transformer/ObjectField",x="DataImporter/DynamicTypes/Transformer/LoadAsset",f="DataImporter/DynamicTypes/Transformer/FlattenArray",v="DataImporter/DynamicTypes/Transformer/ReduceArrayKeyValuePairs",y="DataImporter/DynamicTypes/Transformer/HtmlDecode",b="DataImporter/DynamicTypes/Transformer/Boolean",j="DataImporter/DynamicTypes/Transformer/AsArray",S="DataImporter/DynamicTypes/Transformer/AsColor",C="DataImporter/DynamicTypes/Transformer/AsCountries",I="DataImporter/DynamicTypes/Transformer/Gallery",w="DataImporter/DynamicTypes/Transformer/ImageAdvanced",T="DataImporter/DynamicTypes/Transformer/QuantityValue",N="DataImporter/DynamicTypes/Transformer/QuantityValueArray",F="DataImporter/DynamicTypes/Transformer/InputQuantityValue",k="DataImporter/DynamicTypes/Transformer/InputQuantityValueArray",D="DataImporter/DynamicTypes/Transformer/AsGeobounds",$="DataImporter/DynamicTypes/Transformer/AsGeopoint",L="DataImporter/DynamicTypes/Transformer/AsGeopolygon",P="DataImporter/DynamicTypes/Transformer/AsGeopolyline",B="DataImporter/DynamicTypes/Transformer/LoadDataObject",M="DataImporter/DynamicTypes/Transformer/ImportAsset";var R=a(1635),A=a(4781);class E{}E=(0,R.Cg)([(0,A.injectable)()],E);class O{registerDynamicType(e){this.types.set(e.id,e)}getDynamicType(e){return this.types.get(e)}getAllTypes(){return Array.from(this.types.values())}constructor(){this.types=new Map}}O=(0,R.Cg)([(0,A.injectable)()],O);var q=a(4848),z=a(5446),H=a.n(z),W=a(2696),X=a(3842),V=a(8096);let U=(0,V.createStyles)(e=>{let{css:t}=e;return{label:t`
      font-size: 11px;
      white-space: nowrap;
    `,formItem:t`
      margin-bottom: 6px;
    `,formItemSwitch:t`
      margin-bottom: 0 !important;
      .ant-form-item-row {
        row-gap: 0;
      }
    `,formItemLast:t`
      margin-bottom: 0 !important;
      .ant-form-item-row {
        row-gap: 0;
      }
    `,noSettings:t`
      font-size: 11px;
    `}}),G=e=>{let{children:t}=e,{styles:a}=U();return(0,q.jsx)(X.FieldWidthProvider,{children:(0,q.jsx)(W.Form,{colon:!1,layout:"vertical",children:t(a)})})},_=e=>{let{settings:t,onChange:a}=e;return(0,q.jsx)(G,{children:e=>(0,q.jsx)(W.Form.Item,{className:e.formItemLast,label:(0,q.jsx)("span",{className:e.label,children:"Mode"}),children:(0,q.jsx)(W.Select,{onChange:e=>{a({...t,mode:e})},options:[{value:"both",label:"Both"},{value:"left",label:"Left"},{value:"right",label:"Right"}],value:t.mode??"both"})})})};class K extends E{renderSettings(e,t){return(0,q.jsx)(_,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="trim",this.label="Trim",this.group="dataManipulation"}}K=(0,R.Cg)([(0,A.injectable)()],K);let Q=e=>{let{settings:t,onChange:a}=e;return(0,q.jsx)(G,{children:e=>(0,q.jsx)(W.Form.Item,{className:e.formItemLast,label:(0,q.jsx)("span",{className:e.label,children:"Glue"}),children:(0,q.jsx)(W.Input,{onChange:e=>{var r;r=e.target.value,a({...t,glue:r})},value:t.glue??" "})})})};class Y extends E{renderSettings(e,t){return(0,q.jsx)(Q,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="combine",this.label="Combine",this.group="dataManipulation"}}Y=(0,R.Cg)([(0,A.injectable)()],Y);let Z=e=>{let{settings:t,onChange:a}=e,r=(e,r)=>{a({...t,[e]:r})};return(0,q.jsx)(G,{children:e=>(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Mode"}),children:(0,q.jsx)(W.Select,{onChange:e=>{r("mode",e)},options:[{value:"append",label:"Append"},{value:"prepend",label:"Prepend"}],value:t.mode??"append"})}),(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Text"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("text",e.target.value)},value:t.text??""})}),(0,q.jsx)(W.Form.Item,{className:e.formItemLast,children:(0,q.jsx)(W.Switch,{checked:t.alwaysAdd??!1,labelRight:"Always add",onChange:e=>{r("alwaysAdd",e)},size:"small"})})]})})};class J extends E{renderSettings(e,t){return(0,q.jsx)(Z,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="staticText",this.label="Static Text",this.group="dataManipulation"}}J=(0,R.Cg)([(0,A.injectable)()],J);let ee=e=>{let{settings:t,onChange:a}=e,r=(e,r)=>{a({...t,[e]:r})};return(0,q.jsx)(G,{children:e=>(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Search"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("search",e.target.value)},value:t.search??""})}),(0,q.jsx)(W.Form.Item,{className:e.formItemLast,label:(0,q.jsx)("span",{className:e.label,children:"Replace"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("replace",e.target.value)},value:t.replace??""})})]})})};class et extends E{renderSettings(e,t){return(0,q.jsx)(ee,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="stringReplace",this.label="String Replace",this.group="dataManipulation"}}et=(0,R.Cg)([(0,A.injectable)()],et);let ea=e=>{let{settings:t,onChange:a}=e;return(0,q.jsx)(G,{children:e=>(0,q.jsx)(W.Form.Item,{className:e.formItemLast,label:(0,q.jsx)("span",{className:e.label,children:"Format"}),children:(0,q.jsx)(W.Input,{onChange:e=>{var r;r=e.target.value,a({...t,format:r})},placeholder:"Y-m-d",value:t.format??"Y-m-d"})})})};class er extends E{renderSettings(e,t){return(0,q.jsx)(ea,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="date",this.label="Date",this.group="dataTypes"}}er=(0,R.Cg)([(0,A.injectable)()],er);let ei=e=>{let{settings:t,onChange:a}=e;return(0,q.jsx)(G,{children:e=>(0,q.jsx)(W.Form.Item,{className:e.formItemLast,children:(0,q.jsx)(W.Switch,{checked:t.returnNullIfEmpty??!1,labelRight:"Return null if empty",onChange:e=>{a({...t,returnNullIfEmpty:e})},size:"small"})})})};class eo extends E{renderSettings(e,t){return(0,q.jsx)(ei,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="numeric",this.label="Numeric",this.group="dataTypes"}}eo=(0,R.Cg)([(0,A.injectable)()],eo);let en=e=>{let{settings:t,onChange:a}=e,r=(e,r)=>{a({...t,[e]:r})};return(0,q.jsx)(G,{children:e=>(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Delimiter"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("delimiter",e.target.value)},value:t.delimiter??""})}),(0,q.jsx)(W.Form.Item,{className:e.formItemLast,children:(0,q.jsx)(W.Switch,{checked:t.keepSubArrays??!1,labelRight:"Keep sub-arrays",onChange:e=>{r("keepSubArrays",e)},size:"small"})})]})})};class el extends E{renderSettings(e,t){return(0,q.jsx)(en,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="explode",this.label="Explode",this.group="dataManipulation"}}el=(0,R.Cg)([(0,A.injectable)()],el);let es=e=>{let{settings:t,onChange:a}=e,r=(e,r)=>{a({...t,[e]:r})};return(0,q.jsx)(G,{children:e=>(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Original"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("original",e.target.value)},value:t.original??""})}),(0,q.jsx)(W.Form.Item,{className:e.formItemLast,label:(0,q.jsx)("span",{className:e.label,children:"Converted"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("converted",e.target.value)},value:t.converted??""})})]})})};class ed extends E{renderSettings(e,t){return(0,q.jsx)(es,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="conditionalConversion",this.label="Conditional Conversion",this.group="dataManipulation"}}ed=(0,R.Cg)([(0,A.injectable)()],ed);let ep=e=>{let{settings:t,onChange:a}=e,r=(e,r)=>{a({...t,[e]:r})};return(0,q.jsx)(G,{children:e=>(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Attribute"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("attribute",e.target.value)},value:t.attribute??""})}),(0,q.jsx)(W.Form.Item,{className:e.formItemLast,label:(0,q.jsx)("span",{className:e.label,children:"Forward parameter"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("forward_parameter",e.target.value)},value:t.forward_parameter??""})})]})})};class ec extends E{renderSettings(e,t){return(0,q.jsx)(ep,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="objectField",this.label="Object Field",this.group="dataManipulation"}}ec=(0,R.Cg)([(0,A.injectable)()],ec);let em=e=>{let{settings:t,onChange:a}=e;return(0,q.jsx)(G,{children:e=>(0,q.jsx)(W.Form.Item,{className:e.formItemLast,label:(0,q.jsx)("span",{className:e.label,children:"Load strategy"}),children:(0,q.jsx)(W.Select,{onChange:e=>{a({...t,loadStrategy:e})},options:[{value:"path",label:"By path"},{value:"id",label:"By ID"}],value:t.loadStrategy??"path"})})})};class eu extends E{renderSettings(e,t){return(0,q.jsx)(em,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="loadAsset",this.label="Load Asset",this.group="loadImport"}}eu=(0,R.Cg)([(0,A.injectable)()],eu);var eg=a(2703),eh=a(3513),ex=a(1436);let ef=ex.api.enhanceEndpoints({addTagTypes:["Bundle Data Importer"]}).injectEndpoints({endpoints:e=>({bundleDataImporterClassificationstoreLoadAttributes:e.query({query:e=>({url:"/pimcore-studio/api/bundle/data-importer/classificationstore/attributes",params:{classId:e.classId}}),providesTags:["Bundle Data Importer"]}),bundleDataImporterClassificationstoreLoadKeyName:e.query({query:e=>({url:"/pimcore-studio/api/bundle/data-importer/classificationstore/key-name",params:{keyId:e.keyId}}),providesTags:["Bundle Data Importer"]}),bundleDataImporterClassificationstoreLoadKeys:e.query({query:e=>({url:"/pimcore-studio/api/bundle/data-importer/classificationstore/keys",params:{classId:e.classId,fieldName:e.fieldName,transformationResultType:e.transformationResultType,sort:e.sort,start:e.start,limit:e.limit,searchfilter:e.searchfilter,filter:e.filter}}),providesTags:["Bundle Data Importer"]}),bundleDataImporterConfigCalculateTransformationResultType:e.query({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/transformation-result-type`,method:"POST",body:e.bundleDataImporterCalculateTransformationResultTypeParameters}),providesTags:["Bundle Data Importer"]}),bundleDataImporterConfigCancelExecution:e.mutation({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/cancel-execution`,method:"PUT"}),invalidatesTags:["Bundle Data Importer"]}),bundleDataImporterConfigCheckImportProgress:e.query({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/check-import-progress`}),providesTags:["Bundle Data Importer"]}),bundleDataImporterConfigCopyPreview:e.mutation({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/copy-preview`,method:"POST",body:e.bundleDataImporterCopyPreviewParameters}),invalidatesTags:["Bundle Data Importer"]}),bundleDataImporterConfigGet:e.query({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}`}),providesTags:["Bundle Data Importer"]}),bundleDataImporterConfigSave:e.mutation({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}`,method:"PUT",body:e.bundleDataImporterConfigurationSaveParameters}),invalidatesTags:["Bundle Data Importer"]}),bundleDataImporterConfigHasImportFileUploaded:e.query({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/has-import-file-uploaded`}),providesTags:["Bundle Data Importer"]}),bundleDataImporterConfigLoadColumnHeaders:e.query({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/column-headers`,method:"POST",body:e.bundleDataImporterCopyPreviewParameters}),providesTags:["Bundle Data Importer"]}),bundleDataImporterConfigLoadPreview:e.query({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/load-preview`,method:"POST",body:e.bundleDataImporterLoadPreviewParameters}),providesTags:["Bundle Data Importer"]}),bundleDataImporterConfigLoadTransformationResult:e.query({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/transformation-result`,method:"POST",body:e.bundleDataImporterLoadPreviewParameters}),providesTags:["Bundle Data Importer"]}),bundleDataImporterConfigStartImport:e.mutation({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/start-import`,method:"PUT"}),invalidatesTags:["Bundle Data Importer"]}),bundleDataImporterConfigUploadImportFile:e.mutation({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/upload-import-file`,method:"POST",body:e.body}),invalidatesTags:["Bundle Data Importer"]}),bundleDataImporterConfigUploadPreview:e.mutation({query:e=>({url:`/pimcore-studio/api/bundle/data-importer/config/${e.name}/upload-preview`,method:"POST",body:e.body}),invalidatesTags:["Bundle Data Importer"]}),bundleDataImporterConnectionList:e.query({query:()=>({url:"/pimcore-studio/api/bundle/data-importer/connection/list"}),providesTags:["Bundle Data Importer"]}),bundleDataImporterDataTypeLoadClassAttributes:e.query({query:e=>({url:"/pimcore-studio/api/bundle/data-importer/data-type/class-attributes",params:{classId:e.classId,loadAdvancedRelations:e.loadAdvancedRelations,systemRead:e.systemRead,systemWrite:e.systemWrite,transformationResultType:e.transformationResultType}}),providesTags:["Bundle Data Importer"]}),bundleDataImporterDataTypeLoadUnitData:e.query({query:()=>({url:"/pimcore-studio/api/bundle/data-importer/data-type/unit-data"}),providesTags:["Bundle Data Importer"]}),bundleDataImporterUtilityCheckCrontab:e.query({query:e=>({url:"/pimcore-studio/api/bundle/data-importer/utility/check-crontab",params:{cronExpression:e.cronExpression}}),providesTags:["Bundle Data Importer"]})}),overrideExisting:!1}),{useBundleDataImporterClassificationstoreLoadAttributesQuery:ev,useBundleDataImporterClassificationstoreLoadKeyNameQuery:ey,useBundleDataImporterClassificationstoreLoadKeysQuery:eb,useBundleDataImporterConfigCalculateTransformationResultTypeQuery:ej,useBundleDataImporterConfigCancelExecutionMutation:eS,useBundleDataImporterConfigCheckImportProgressQuery:eC,useBundleDataImporterConfigCopyPreviewMutation:eI,useBundleDataImporterConfigGetQuery:ew,useBundleDataImporterConfigSaveMutation:eT,useBundleDataImporterConfigHasImportFileUploadedQuery:eN,useBundleDataImporterConfigLoadColumnHeadersQuery:eF,useBundleDataImporterConfigLoadPreviewQuery:ek,useBundleDataImporterConfigLoadTransformationResultQuery:eD,useBundleDataImporterConfigStartImportMutation:e$,useBundleDataImporterConfigUploadImportFileMutation:eL,useBundleDataImporterConfigUploadPreviewMutation:eP,useBundleDataImporterConnectionListQuery:eB,useBundleDataImporterDataTypeLoadClassAttributesQuery:eM,useBundleDataImporterDataTypeLoadUnitDataQuery:eR,useBundleDataImporterUtilityCheckCrontabQuery:eA}=ef,eE=["id","path","key"],eO=e=>{let{settings:t,onChange:a}=e,r=(e,r)=>{a({...t,[e]:r})},i=(0,eg.useSettings)(),o=(0,z.useMemo)(()=>(i.validLanguages??[]).map(e=>({value:e,label:e})),[i.validLanguages]),{data:n,isLoading:l}=(0,eh.useClassDefinitionCollectionQuery)(),s=((null==n?void 0:n.items)??[]).map(e=>({value:e.id,label:e.name})),d=t.loadStrategy??"id",p="attribute"===d,c=t.attributeDataObjectClassId??"",m=t.attributeName,{data:u,isLoading:g}=eM({classId:c,systemRead:!0},{skip:""===c||!p}),h=(0,z.useMemo)(()=>((null==u?void 0:u.attributes)??[]).map(e=>({key:e.key??e.name??"",title:e.title??e.name??e.key??"",localized:!!e.localized})),[u]),x=h.map(e=>({value:e.key,label:e.title})),f=h.find(e=>e.key===m),v=(null==f?void 0:f.localized)??!1,y=p&&null!=m&&""!==m&&!eE.includes(m);return(0,q.jsx)(G,{children:e=>(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Load strategy"}),children:(0,q.jsx)(W.Select,{onChange:e=>{r("loadStrategy",e),"attribute"!==e&&a({...t,loadStrategy:e,attributeDataObjectClassId:void 0,attributeName:void 0,loadUnpublished:void 0})},options:[{value:"id",label:"By ID"},{value:"path",label:"By Path"},{value:"attribute",label:"By Attribute"}],value:d})}),p&&(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Class"}),children:(0,q.jsx)(W.Select,{loadingSkeleton:l,onChange:e=>{a({...t,attributeDataObjectClassId:e,attributeName:void 0})},options:s,value:""!==c?c:void 0})}),(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Attribute name"}),children:(0,q.jsx)(W.Select,{loadingSkeleton:g,onChange:e=>{r("attributeName",e)},options:x,value:m})}),y&&(0,q.jsx)(W.Form.Item,{className:e.formItemSwitch,children:(0,q.jsx)(W.Switch,{checked:!!t.partialMatch,labelRight:"Accept partial match",onChange:e=>{r("partialMatch",e)},size:"small"})}),v&&(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Language"}),children:(0,q.jsx)(W.Select,{onChange:e=>{r("attributeLanguage",e)},options:o,value:t.attributeLanguage})}),(0,q.jsx)(W.Form.Item,{className:e.formItemLast,children:(0,q.jsx)(W.Switch,{checked:!!t.loadUnpublished,labelRight:"Load unpublished",onChange:e=>{r("loadUnpublished",e)},size:"small"})})]}),!p&&(0,q.jsx)("div",{style:{height:0}})]})})};class eq extends E{renderSettings(e,t){return(0,q.jsx)(eO,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="loadDataObject",this.label="Load Data Object",this.group="loadImport"}}eq=(0,R.Cg)([(0,A.injectable)()],eq);let ez=e=>{let{settings:t,onChange:a}=e,r=(e,r)=>{a({...t,[e]:r})},i=t.unitSourceSelect??"id",{data:o,isLoading:n}=eR(),l=(0,z.useMemo)(()=>((null==o?void 0:o.UnitList)??[]).map(e=>({value:e.unitId??"",label:e.abbreviation??e.unitId??""})),[o]);return(0,q.jsx)(G,{children:e=>(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Unit source"}),children:(0,q.jsx)(W.Select,{onChange:e=>{a({...t,unitSourceSelect:e,staticUnitSelect:void 0})},options:[{value:"id",label:"By Unit ID"},{value:"abbr",label:"By Abbreviation"},{value:"static",label:"Static"}],value:i})}),"static"===i&&(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Unit"}),children:(0,q.jsx)(W.Select,{loadingSkeleton:n,onChange:e=>{r("staticUnitSelect",e)},options:l,showSearch:!0,value:t.staticUnitSelect})}),(0,q.jsx)(W.Form.Item,{className:e.formItemLast,children:(0,q.jsx)(W.Switch,{checked:!!t.unitNullIfNoValueCheckbox,labelRight:"Null if no value",onChange:e=>{r("unitNullIfNoValueCheckbox",e)},size:"small"})})]})})};class eH extends E{renderSettings(e,t){return(0,q.jsx)(ez,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="quantityValue",this.label="Quantity Value",this.group="dataTypes"}}eH=(0,R.Cg)([(0,A.injectable)()],eH);let eW=e=>{let{settings:t,onChange:a}=e,r=(e,r)=>{a({...t,[e]:r})};return(0,q.jsx)(G,{children:e=>(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{className:e.formItem,label:(0,q.jsx)("span",{className:e.label,children:"Parent folder"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("parentFolder",e.target.value)},placeholder:"/",value:t.parentFolder??"/"})}),(0,q.jsx)(W.Form.Item,{className:e.formItemSwitch,children:(0,q.jsx)(W.Switch,{checked:!1!==t.useExisting,labelRight:"Use existing",onChange:e=>{r("useExisting",e)},size:"small"})}),(0,q.jsx)(W.Form.Item,{className:e.formItemSwitch,children:(0,q.jsx)(W.Switch,{checked:!1!==t.overwriteExisting,labelRight:"Overwrite existing",onChange:e=>{r("overwriteExisting",e)},size:"small"})}),(0,q.jsx)(W.Form.Item,{className:e.formItemLast,label:(0,q.jsx)("span",{className:e.label,children:"Preg match"}),children:(0,q.jsx)(W.Input,{onChange:e=>{r("pregMatch",e.target.value)},value:t.pregMatch??""})})]})})};class eX extends E{renderSettings(e,t){return(0,q.jsx)(eW,{onChange:t,settings:e})}constructor(...e){super(...e),this.id="importAsset",this.label="Import Asset",this.group="loadImport"}}eX=(0,R.Cg)([(0,A.injectable)()],eX);let eV=()=>{let{styles:e}=U();return(0,q.jsx)(W.Text,{className:e.noSettings,type:"secondary",children:"No additional settings"})};function eU(e,t,a){class r extends E{renderSettings(){return(0,q.jsx)(eV,{})}constructor(...r){super(...r),this.id=e,this.label=t,this.group=a}}return(0,R.Cg)([(0,A.injectable)()],r)}let eG=eU("flattenArray","Flatten Array","dataManipulation"),e_=eU("reduceArrayKeyValuePairs","Reduce Array Key-Value Pairs","dataManipulation"),eK=eU("htmlDecode","HTML Decode","dataManipulation"),eQ=eU("boolean","Boolean","dataTypes"),eY=eU("asArray","As Array","dataTypes"),eZ=eU("asColor","As Color","dataTypes"),eJ=eU("asCountries","As Countries","dataTypes"),e0=eU("gallery","Gallery","dataTypes"),e1=eU("imageAdvanced","Image Advanced","dataTypes"),e2=eU("quantityValueArray","Quantity Value Array","dataTypes"),e3=eU("inputQuantityValue","Input Quantity Value","dataTypes"),e5=eU("inputQuantityValueArray","Input Quantity Value Array","dataTypes"),e6=eU("asGeobounds","As Geobounds","dataTypes"),e9=eU("asGeopoint","As Geopoint","dataTypes"),e4=eU("asGeopolygon","As Geopolygon","dataTypes"),e7=eU("asGeopolyline","As Geopolyline","dataTypes"),e8={onInit:()=>{r.container.get(i.bundleServiceIds["DataHub/DynamicTypes/Adapter/Registry"]).registerDynamicType(r.container.get(o)),r.container.bind(n).to(O).inSingletonScope(),r.container.bind(l).to(K).inSingletonScope(),r.container.bind(s).to(Y).inSingletonScope(),r.container.bind(d).to(J).inSingletonScope(),r.container.bind(p).to(et).inSingletonScope(),r.container.bind(c).to(er).inSingletonScope(),r.container.bind(m).to(eo).inSingletonScope(),r.container.bind(u).to(el).inSingletonScope(),r.container.bind(g).to(ed).inSingletonScope(),r.container.bind(h).to(ec).inSingletonScope(),r.container.bind(x).to(eu).inSingletonScope(),r.container.bind(B).to(eq).inSingletonScope(),r.container.bind(f).to(eG).inSingletonScope(),r.container.bind(v).to(e_).inSingletonScope(),r.container.bind(y).to(eK).inSingletonScope(),r.container.bind(b).to(eQ).inSingletonScope(),r.container.bind(j).to(eY).inSingletonScope(),r.container.bind(S).to(eZ).inSingletonScope(),r.container.bind(C).to(eJ).inSingletonScope(),r.container.bind(I).to(e0).inSingletonScope(),r.container.bind(w).to(e1).inSingletonScope(),r.container.bind(T).to(eH).inSingletonScope(),r.container.bind(N).to(e2).inSingletonScope(),r.container.bind(F).to(e3).inSingletonScope(),r.container.bind(k).to(e5).inSingletonScope(),r.container.bind(D).to(e6).inSingletonScope(),r.container.bind($).to(e9).inSingletonScope(),r.container.bind(L).to(e4).inSingletonScope(),r.container.bind(P).to(e7).inSingletonScope(),r.container.bind(M).to(eX).inSingletonScope();let e=r.container.get(n);for(let t of[l,s,d,p,c,m,u,g,h,x,f,v,y,b,j,S,C,I,w,T,N,F,k,D,$,L,P,B,M])e.registerDynamicType(r.container.get(t))}},te=ef.enhanceEndpoints({addTagTypes:["DataHubConfigs"],endpoints:{bundleDataImporterClassificationstoreLoadAttributes:{providesTags:[]},bundleDataImporterClassificationstoreLoadKeyName:{providesTags:[]},bundleDataImporterClassificationstoreLoadKeys:{providesTags:[]},bundleDataImporterConfigCancelExecution:{invalidatesTags:[]},bundleDataImporterConfigCheckImportProgress:{providesTags:[]},bundleDataImporterConfigCalculateTransformationResultType:{providesTags:[]},bundleDataImporterConfigCopyPreview:{invalidatesTags:[]},bundleDataImporterConfigLoadColumnHeaders:{providesTags:[]},bundleDataImporterConfigLoadPreview:{providesTags:[]},bundleDataImporterConfigLoadTransformationResult:{providesTags:[]},bundleDataImporterConfigGet:{providesTags:[]},bundleDataImporterConfigSave:{invalidatesTags:["DataHubConfigs"]},bundleDataImporterConfigHasImportFileUploaded:{providesTags:[]},bundleDataImporterConfigUploadImportFile:{invalidatesTags:[]},bundleDataImporterConfigUploadPreview:{invalidatesTags:[]},bundleDataImporterConfigStartImport:{invalidatesTags:[]},bundleDataImporterConnectionList:{providesTags:[]},bundleDataImporterDataTypeLoadClassAttributes:{providesTags:[]},bundleDataImporterDataTypeLoadUnitData:{providesTags:[]},bundleDataImporterUtilityCheckCrontab:{providesTags:[]}}}),{useBundleDataImporterConfigGetQuery:tt,useBundleDataImporterConfigSaveMutation:ta,useBundleDataImporterConfigHasImportFileUploadedQuery:tr,useBundleDataImporterConnectionListQuery:ti}=te;var to=a(1119);function tn(e){return void 0===e||""===e||"default"===e?"__default__":e}function tl(){var e;return"function"==typeof(null==(e=globalThis.crypto)?void 0:e.randomUUID)?globalThis.crypto.randomUUID():`mapping-${Date.now().toString(36)}-${Math.random().toString(36).slice(2,8)}`}function ts(e){return{id:e.id,name:e.name,read:e.read??!1,update:e.update??!1,delete:e.delete??!1}}function td(e){return{id:e.id,name:e.name??"",read:e.read??!1,update:e.update??!1,delete:e.delete??!1}}function tp(e,t){var a;let r=tc(e.loaderConfig,t.loaderConfig),i=tc(e.interpreterConfig,t.interpreterConfig),o=function(e){if((null==e?void 0:e.type)===void 0)return e;let t=(null==e?void 0:e.settings)??{};if("asset"===e.type)return{...e,settings:{assetPath:tm(t.assetPath)}};if("upload"===e.type)return{...e,settings:{uploadFilePath:tm(t.uploadFilePath)}};if("http"===e.type){let a=tm(t.schema),r=tm(t.url).replace(/^\s*[a-z][a-z0-9+.-]*:\/\//i,"");return{...e,settings:{schema:a,url:r}}}return"sftp"===e.type?{...e,settings:{host:tm(t.host),port:tm(t.port),username:tm(t.username),password:tm(t.password),remotePath:tm(t.remotePath)}}:"push"===e.type?{...e,settings:{apiKey:tm(t.apiKey),ignoreNotEmptyQueue:tu(t.ignoreNotEmptyQueue)}}:"sql"===e.type?{...e,settings:{connection:tm(t.connection),select:tm(t.select),from:tm(t.from),where:tm(t.where),groupBy:tm(t.groupBy)}}:e}(r),n=function(e){if((null==e?void 0:e.type)===void 0)return e;let t=e.settings??{};return"csv"===e.type?{...e,settings:{skipFirstRow:tu(t.skipFirstRow),saveHeaderName:tu(t.saveHeaderName),delimiter:tm(t.delimiter),enclosure:tm(t.enclosure),escape:tm(t.escape)}}:"json"===e.type?{...e,settings:{path:tm(t.path)}}:"xml"===e.type?{...e,settings:{xpath:tm(t.xpath),schema:tm(t.schema)}}:"xlsx"===e.type?{...e,settings:{skipFirstRow:tu(t.skipFirstRow),sheetName:tm(t.sheetName)}}:"sql"===e.type?{...e,settings:{}}:e}(i);return{...t,general:{...t.general,active:e.active,description:e.description,group:e.group},loaderConfig:o,interpreterConfig:n,resolverConfig:e.resolverConfig??t.resolverConfig,mappingConfig:e.mappingConfig??t.mappingConfig,processingConfig:e.processingConfig??t.processingConfig,executionConfig:e.executionConfig??t.executionConfig,permissions:{roles:((null==(a=e.permissions)?void 0:a.roles)??[]).map(ts),users:((null==a?void 0:a.users)??[]).map(ts)}}}function tc(e,t){return void 0===e?t:void 0===e.type||""===e.type?t??e:{...t,...e,settings:{...(null==t?void 0:t.settings)??{},...e.settings??{}}}}function tm(e){return null==e?"":String(e)}function tu(e){return"boolean"==typeof e?e:"1"===e||1===e||"true"===e}function tg(e,t){var a,r,i,o;let n=(e.mappingConfig??[]).map(e=>({...{...e,mappingId:e.mappingId??tl()}}));return{active:(null==(a=e.general)?void 0:a.active)??!1,name:t,description:(null==(r=e.general)?void 0:r.description)??"",group:(null==(i=e.general)?void 0:i.group)??"",loaderConfig:e.loaderConfig,interpreterConfig:e.interpreterConfig,resolverConfig:e.resolverConfig,mappingConfig:n,processingConfig:e.processingConfig,executionConfig:e.executionConfig,permissions:{roles:((null==(o=e.permissions)?void 0:o.roles)??[]).map(td),users:((null==o?void 0:o.users)??[]).map(td)}}}let th=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{stepHeading:t`
      color: ${a.colorPrimary};
      font-size: 14px;
      font-weight: ${a.fontWeightStrong};
      height: 32px;
      line-height: 32px;
      margin: 0;
      padding-left: ${a.paddingXXS}px;
    `}}),tx=e=>{let{children:t}=e,{styles:a}=th();return(0,q.jsx)("div",{className:a.stepHeading,children:t})},tf=(0,V.createStyles)((e,t)=>{let{css:a}=e;return{container:a`
      max-width: ${t}px;
      width: 100%;
    `}}),tv=e=>{let{children:t}=e,{medium:a}=(0,X.useFieldWidth)(),{styles:r}=tf(a);return(0,q.jsx)("div",{className:r.container,children:t})},ty=e=>{let{children:t,theme:a="card-with-highlight",contentPadding:r="extra-small",noWidthLimit:i=!1,...o}=e;if("card-with-highlight"===a){let e=(0,q.jsx)(W.Space,{className:"w-full",direction:"vertical",size:"extra-small",children:t});return(0,q.jsx)(W.FormKit.Panel,{...o,contentPadding:r,theme:a,children:i?e:(0,q.jsx)(tv,{children:e})})}let n=(0,q.jsx)(W.FormKit.Panel,{...o,contentPadding:r,theme:a,children:t});return i?n:(0,q.jsx)(tv,{children:n})},tb=(e,t)=>String((null==t?void 0:t.label)??"").toLowerCase().includes(e.toLowerCase()),tj=()=>{let{t:e}=(0,A.useTranslation)();return(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.asset.asset-path"),name:["loaderConfig","settings","assetPath"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.asset.asset-path")})}],children:(0,q.jsx)(W.ManyToOneRelationPath,{allowPathTextInput:!0,allowedAssetTypes:["text","document","unknown"],assetsAllowed:!0})})},tS=e=>{let{configName:t}=e,{t:a}=(0,A.useTranslation)(),r=W.Form.useFormInstance(),[i,o]=(0,z.useState)(!1),{data:n,isFetching:l,isLoading:s,isError:d,refetch:p}=tr({name:t}),c=`${(0,ex.getPrefix)()}/bundle/data-importer/config/${t}/upload-import-file`;(0,z.useEffect)(()=>{let e=(null==n?void 0:n.filePath)??"";(r.getFieldValue(["loaderConfig","settings","uploadFilePath"])??"")!==e&&r.setFieldValue(["loaderConfig","settings","uploadFilePath"],e,{triggerChange:!1})},[null==n?void 0:n.filePath,r]);let m=(null==n?void 0:n.exists)===!0,u=s||l&&void 0===n,g=(null==n?void 0:n.message)??a(m?"data-importer.loader.upload.file-uploaded":"data-importer.loader.upload.no-file");return(0,q.jsxs)(W.Space,{direction:"vertical",size:"small",children:[u?(0,q.jsx)(W.Spin,{type:"classic"}):(0,q.jsx)(W.Alert,{message:g,type:d?"error":m?"success":"warning"}),(0,q.jsx)(W.Form.Item,{hidden:!0,name:["loaderConfig","settings","uploadFilePath"],children:(0,q.jsx)(W.Input,{})}),(0,q.jsx)(W.Button,{onClick:()=>{o(!0)},type:"primary",children:a("data-importer.loader.upload.open-upload")}),(0,q.jsx)(W.ImportModal,{action:c,onOpenChange:o,onUploadSuccess:()=>{p()},open:i,title:a("data-importer.loader.upload.modal-title")})]})},tC=()=>{let{t:e}=(0,A.useTranslation)();return(0,q.jsxs)(W.FormKit.Panel,{children:[(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.http.schema"),name:["loaderConfig","settings","schema"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.http.schema")})}],children:(0,q.jsx)(W.Select,{filterOption:tb,options:[{value:"https://",label:"HTTPS"},{value:"http://",label:"HTTP"}],showSearch:!0})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.http.url"),name:["loaderConfig","settings","url"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.http.url")})}],children:(0,q.jsx)(W.Input,{placeholder:"example.com/data.csv"})})]})},tI=()=>{let{t:e}=(0,A.useTranslation)();return(0,q.jsxs)(W.FormKit.Panel,{children:[(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.sftp.host"),name:["loaderConfig","settings","host"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.sftp.host")})}],children:(0,q.jsx)(W.Input,{placeholder:"example.com"})}),(0,q.jsx)(W.Form.Item,{initialValue:22,label:e("data-importer.loader.sftp.port"),name:["loaderConfig","settings","port"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.sftp.port")})}],children:(0,q.jsx)(W.InputNumber,{max:65535,min:1})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.sftp.username"),name:["loaderConfig","settings","username"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.sftp.username")})}],children:(0,q.jsx)(W.Input,{})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.sftp.password"),name:["loaderConfig","settings","password"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.sftp.password")})}],children:(0,q.jsx)(W.InputPassword,{})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.sftp.remote-path"),name:["loaderConfig","settings","remotePath"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.sftp.remote-path")})}],children:(0,q.jsx)(W.Input,{placeholder:"/path/to/file.csv"})})]})};var tw=a(2692);let tT=(0,V.createStyles)(e=>{let{css:t}=e;return{fullWidth:t`
      width: 100%;
    `}}),tN=()=>{let{t:e}=(0,A.useTranslation)(),{styles:t}=tT(),a=W.Form.useFormInstance(),r=W.Form.useWatch(["name"]),i=`${window.location.protocol}//${window.location.host}/pimcore-datahub-import/${r??""}/push`;return(0,q.jsxs)(W.FormKit.Panel,{children:[(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.push.api-key"),required:!0,children:(0,q.jsxs)(W.Compact,{className:t.fullWidth,children:[(0,q.jsx)(W.Form.Item,{name:["loaderConfig","settings","apiKey"],noStyle:!0,rules:[{required:!0,message:e("data-importer.loader.push.api-key-required")},{min:16,message:e("data-importer.loader.push.api-key-min-length")}],children:(0,q.jsx)(W.Input,{})}),(0,q.jsx)(W.Button,{htmlType:"button",icon:(0,q.jsx)(W.Icon,{value:"reload"}),onClick:()=>{let e=(0,tw.v4)();a.setFieldValue(["loaderConfig","settings","apiKey"],e,{triggerChange:!0})},type:"default",children:e("data-importer.loader.push.api-key.generate")})]})}),(0,q.jsx)(W.Form.Item,{name:["loaderConfig","settings","ignoreNotEmptyQueue"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:e("data-importer.loader.push.ignore-not-empty-queue")})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.push.endpoint"),children:(0,q.jsx)(W.Alert,{message:i,type:"info"})})]})},tF=()=>{let{t:e}=(0,A.useTranslation)(),{data:t,isLoading:a}=ti(),r=(null==t?void 0:t.connections.map(e=>({value:e.value??"",label:e.name??""})))??[];return(0,q.jsxs)(W.FormKit.Panel,{children:[(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.sql.connection"),name:["loaderConfig","settings","connection"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.sql.connection")})}],children:(0,q.jsx)(W.Select,{filterOption:tb,loadingSkeleton:a,options:r,showSearch:!0})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.sql.select"),name:["loaderConfig","settings","select"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.sql.select")})}],children:(0,q.jsx)(W.TextArea,{placeholder:"a, b, c",rows:4})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.sql.from"),name:["loaderConfig","settings","from"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.loader.sql.from")})}],children:(0,q.jsx)(W.TextArea,{placeholder:"table_name INNER JOIN other_table ON condition",rows:4})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.sql.where"),name:["loaderConfig","settings","where"],children:(0,q.jsx)(W.TextArea,{placeholder:"column = 'value'",rows:4})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.loader.sql.group-by"),name:["loaderConfig","settings","groupBy"],children:(0,q.jsx)(W.TextArea,{placeholder:"column1, column2",rows:4})})]})},tk=()=>{let{t:e}=(0,A.useTranslation)(),t=t=>({async validator(a,r){(r??"").length<=1?await Promise.resolve():await Promise.reject(Error(e("data-importer.interpreter.csv.single-char-only",{field:t})))}});return(0,q.jsxs)(W.FormKit.Panel,{children:[(0,q.jsx)(W.Form.Item,{name:["interpreterConfig","settings","skipFirstRow"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:e("data-importer.interpreter.csv.skip-first-row")})}),(0,q.jsx)(W.Form.Item,{name:["interpreterConfig","settings","saveHeaderName"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:e("data-importer.interpreter.csv.save-header-name")})}),(0,q.jsx)(W.Form.Item,{initialValue:",",label:e("data-importer.interpreter.csv.delimiter"),name:["interpreterConfig","settings","delimiter"],required:!0,rules:[{required:!0,message:e("data-importer.interpreter.csv.required",{field:e("data-importer.interpreter.csv.delimiter")})},t(e("data-importer.interpreter.csv.delimiter"))],children:(0,q.jsx)(W.Input,{})}),(0,q.jsx)(W.Form.Item,{initialValue:'"',label:e("data-importer.interpreter.csv.enclosure"),name:["interpreterConfig","settings","enclosure"],required:!0,rules:[{required:!0,message:e("data-importer.interpreter.csv.required",{field:e("data-importer.interpreter.csv.enclosure")})},t(e("data-importer.interpreter.csv.enclosure"))],children:(0,q.jsx)(W.Input,{})}),(0,q.jsx)(W.Form.Item,{initialValue:"\\",label:e("data-importer.interpreter.csv.escape"),name:["interpreterConfig","settings","escape"],required:!0,rules:[t(e("data-importer.interpreter.csv.escape"))],children:(0,q.jsx)(W.Input,{})})]})},tD=()=>{let{t:e}=(0,A.useTranslation)();return(0,q.jsx)(W.Form.Item,{label:e("data-importer.interpreter.json.path"),name:["interpreterConfig","settings","path"],children:(0,q.jsx)(W.Input,{placeholder:"data[*]"})})},t$=(0,V.createStyles)(e=>{let{css:t}=e;return{monoTextArea:t`
      font-family: monospace;
    `}}),tL=()=>{let{t:e}=(0,A.useTranslation)(),{styles:t}=t$();return(0,q.jsxs)(W.FormKit.Panel,{children:[(0,q.jsx)(W.Form.Item,{initialValue:"/root/item",label:e("data-importer.interpreter.xml.xpath"),name:["interpreterConfig","settings","xpath"],required:!0,rules:[{required:!0,message:e("data-importer.validation.required",{field:e("data-importer.interpreter.xml.xpath")})}],children:(0,q.jsx)(W.Input,{})}),(0,q.jsx)(W.Form.Item,{label:e("data-importer.interpreter.xml.schema"),name:["interpreterConfig","settings","schema"],children:(0,q.jsx)(W.TextArea,{className:t.monoTextArea,rows:6})})]})},tP=()=>{let{t:e}=(0,A.useTranslation)();return(0,q.jsxs)(W.FormKit.Panel,{children:[(0,q.jsx)(W.Form.Item,{name:["interpreterConfig","settings","skipFirstRow"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:e("data-importer.interpreter.xlsx.skip-first-row")})}),(0,q.jsx)(W.Form.Item,{initialValue:"Sheet1",label:e("data-importer.interpreter.xlsx.sheet-name"),name:["interpreterConfig","settings","sheetName"],children:(0,q.jsx)(W.Input,{})})]})},tB=()=>{let{t:e}=(0,A.useTranslation)();return(0,q.jsx)(W.Alert,{message:e("data-importer.interpreter.sql.info"),type:"info"})},tM=e=>{let{configName:t}=e,{t:a}=(0,A.useTranslation)(),r=[{value:"asset",label:a("data-importer.loader.asset")},{value:"upload",label:a("data-importer.loader.upload")},{value:"http",label:a("data-importer.loader.http")},{value:"sftp",label:a("data-importer.loader.sftp")},{value:"push",label:a("data-importer.loader.push")},{value:"sql",label:a("data-importer.loader.sql")}],i=[{value:"csv",label:a("data-importer.interpreter.csv")},{value:"json",label:a("data-importer.interpreter.json")},{value:"xml",label:a("data-importer.interpreter.xml")},{value:"xlsx",label:a("data-importer.interpreter.xlsx")},{value:"sql",label:a("data-importer.interpreter.sql")}];return(0,q.jsxs)(X.FieldWidthProvider,{fieldWidthValues:{medium:900},children:[(0,q.jsx)(tx,{children:a("data-importer.data-source.title")}),(0,q.jsxs)(ty,{children:[(0,q.jsx)(W.Form.Item,{label:a("data-importer.data-source.type-label"),name:["loaderConfig","type"],required:!0,children:(0,q.jsx)(W.Select,{filterOption:tb,options:r,showSearch:!0})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.loaderConfig)?void 0:t.type)==="asset"},children:(0,q.jsx)(ty,{theme:"fieldset",title:a("data-importer.loader.asset"),children:(0,q.jsx)(tj,{})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.loaderConfig)?void 0:t.type)==="upload"},children:(0,q.jsx)(ty,{theme:"fieldset",title:a("data-importer.loader.upload"),children:(0,q.jsx)(tS,{configName:t})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.loaderConfig)?void 0:t.type)==="http"},children:(0,q.jsx)(ty,{theme:"fieldset",title:a("data-importer.loader.http"),children:(0,q.jsx)(tC,{})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.loaderConfig)?void 0:t.type)==="sftp"},children:(0,q.jsx)(ty,{theme:"fieldset",title:a("data-importer.loader.sftp"),children:(0,q.jsx)(tI,{})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.loaderConfig)?void 0:t.type)==="push"},children:(0,q.jsx)(ty,{theme:"fieldset",title:a("data-importer.loader.push"),children:(0,q.jsx)(tN,{})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.loaderConfig)?void 0:t.type)==="sql"},children:(0,q.jsx)(ty,{theme:"fieldset",title:a("data-importer.loader.sql"),children:(0,q.jsx)(tF,{})})})]}),(0,q.jsxs)(ty,{title:a("data-importer.file-format.title"),children:[(0,q.jsx)(W.Form.Item,{label:a("data-importer.file-format.title"),name:["interpreterConfig","type"],required:!0,children:(0,q.jsx)(W.Select,{filterOption:tb,options:i,showSearch:!0})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.interpreterConfig)?void 0:t.type)==="csv"},children:(0,q.jsx)(ty,{border:!0,theme:"fieldset",title:a("data-importer.interpreter.csv"),children:(0,q.jsx)(tk,{})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.interpreterConfig)?void 0:t.type)==="json"},children:(0,q.jsx)(ty,{border:!0,theme:"fieldset",title:a("data-importer.interpreter.json"),children:(0,q.jsx)(tD,{})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.interpreterConfig)?void 0:t.type)==="xml"},children:(0,q.jsx)(ty,{border:!0,theme:"fieldset",title:a("data-importer.interpreter.xml"),children:(0,q.jsx)(tL,{})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.interpreterConfig)?void 0:t.type)==="xlsx"},children:(0,q.jsx)(ty,{border:!0,theme:"fieldset",title:a("data-importer.interpreter.xlsx"),children:(0,q.jsx)(tP,{})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.interpreterConfig)?void 0:t.type)==="sql"},children:(0,q.jsx)(ty,{border:!0,theme:"fieldset",title:a("data-importer.interpreter.sql"),children:(0,q.jsx)(tB,{})})})]})]})};var tR=a(7984);function tA(e){let{configName:t,enabled:a,getCurrentConfig:r}=e,[i,o]=(0,z.useState)(void 0),[n,l]=(0,z.useState)(0),[s,d]=(0,z.useState)(!1),{data:p,isLoading:c,isFetching:m,isError:u,error:g,refetch:h}=ek(i,{skip:!a||void 0===i,refetchOnMountOrArgChange:!1}),x=(0,z.useCallback)((e,a)=>{let i=null==r?void 0:r();l(e),o({name:t,bundleDataImporterLoadPreviewParameters:{recordNumber:e,...void 0!==i&&{currentConfig:i}}}),(null==a?void 0:a.forceRefetch)===!0&&d(!0)},[t,r]);(0,z.useEffect)(()=>{a&&void 0===i&&x(0)},[a,i,x]),(0,z.useEffect)(()=>{s&&void 0!==i&&(d(!1),h())},[s,i,h]);let f=(null==p?void 0:p.previewRecordIndex)??n;return{dataPreview:(0,z.useMemo)(()=>(null==p?void 0:p.dataPreview)??[],[p]),currentRecordIndex:f,isLoading:c,isFetching:m,isError:u,error:g,load:x}}let tE=(0,tR.createColumnHelper)(),tO=e=>{let{configName:t,isActive:a}=e,{t:r}=(0,A.useTranslation)(),i=W.Form.useFormInstance(),[o,n]=(0,z.useState)(0),[l,s]=(0,z.useState)(""),[d,p]=(0,z.useState)(!1),{data:c}=tt({name:t}),m=(0,z.useCallback)(()=>tp(i.getFieldsValue(!0),(null==c?void 0:c.configuration)??{}),[i,c]),[u,{isLoading:g,error:h}]=eI(),{dataPreview:x,currentRecordIndex:f,isLoading:v,isFetching:y,isError:b,error:j,load:S}=tA({configName:t,enabled:a,getCurrentConfig:m});(0,z.useEffect)(()=>{n(f)},[f]),(0,z.useEffect)(()=>{null!=j&&("object"!=typeof j||null===j||404!==j.status)&&(0,eg.trackError)(new eg.ApiError(j))},[j]),(0,z.useEffect)(()=>{void 0!==h&&(0,eg.trackError)(new eg.ApiError(h))},[h]);let C=async()=>{"error"in await u({name:t,bundleDataImporterCopyPreviewParameters:{currentConfig:m()}})||S(0,{forceRefetch:!0})},I=[{key:"upload",label:r("data-importer.preview-import.upload-file"),onClick:()=>{p(!0)}},{key:"copy",label:r("data-importer.preview-import.copy-from-source"),onClick:()=>{C()}}],w=(0,z.useMemo)(()=>[tE.accessor("label",{header:r("data-importer.preview-import.column.label"),size:300}),tE.accessor("data",{header:r("data-importer.preview-import.column.data"),meta:{autoWidth:!0}})],[r]),T=(0,z.useMemo)(()=>b?[]:x.map(e=>({...e,data:"string"==typeof e.data?e.data:JSON.stringify(e.data)})),[x,b]),N=(0,z.useMemo)(()=>{if(""===l)return T;let e=l.toLowerCase();return T.filter(t=>(t.label??"").toLowerCase().includes(e))},[T,l]),F=v||y||g;return(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Box,{margin:{bottom:"extra-small"},children:(0,q.jsxs)(W.Flex,{align:"center",gap:"extra-small",justify:"space-between",children:[(0,q.jsxs)(W.Flex,{align:"center",gap:"extra-small",children:[(0,q.jsx)(tx,{children:r("data-importer.preview-import.title")}),(0,q.jsx)(W.Dropdown,{menu:{items:I},children:(0,q.jsx)(W.DropdownButton,{disabled:F,type:"default",children:r("data-importer.preview-import.choose-preview-data")})}),(0,q.jsx)(W.IconButton,{disabled:F||o<=0,icon:{value:"chevron-left"},onClick:()=>{S(Math.max(0,o-1))},tooltip:{title:r("data-importer.preview-import.prev")},type:"default"}),(0,q.jsx)(W.IconButton,{disabled:F,icon:{value:"chevron-right"},onClick:()=>{S(o+1)},tooltip:{title:r("data-importer.preview-import.next")},type:"default"})]}),(0,q.jsx)(W.SearchInput,{maxWidth:320,onChange:e=>{s(e.target.value)},placeholder:r("data-importer.preview-import.search-placeholder"),withClear:!0,withPrefix:!0})]})}),(0,q.jsx)(W.Grid,{autoWidth:!0,columns:w,data:N,isLoading:F}),(0,q.jsx)(W.ImportModal,{action:`${(0,ex.getPrefix)()}/bundle/data-importer/config/${t}/upload-preview`,onOpenChange:e=>{p(e)},onUploadSuccess:()=>{p(!1),S(0,{forceRefetch:!0})},open:d,title:r("data-importer.preview-import.upload-file")})]})};function tq(e){return{key:e.key??e.name??"",title:e.title??e.name??e.key??"",localized:!!e.localized}}let tz=e=>{var t,a,r;let{configName:i,columnHeaderOptions:o}=e,{t:n}=(0,A.useTranslation)(),l=(0,eg.useSettings)(),s=(0,z.useMemo)(()=>(l.validLanguages??[]).map(e=>({value:e,label:e})),[l.validLanguages]),{data:d,isLoading:p}=(0,eh.useClassDefinitionCollectionQuery)(),c=((null==d?void 0:d.items)??[]).map(e=>({value:e.id,label:e.name})),m=W.Form.useWatch(["resolverConfig","dataObjectClassId"]),u=W.Form.useWatch(["resolverConfig","loadingStrategy","type"]),g=W.Form.useWatch(["resolverConfig","loadingStrategy","settings","attributeName"]),h=W.Form.useWatch(["resolverConfig","createLocationStrategy","type"]),x=W.Form.useWatch(["resolverConfig","locationUpdateStrategy","type"]),f=W.Form.useWatch(["resolverConfig","createLocationStrategy","settings","findStrategy"]),v=W.Form.useWatch(["resolverConfig","locationUpdateStrategy","settings","findStrategy"]),y=W.Form.useWatch(["resolverConfig","createLocationStrategy","settings","attributeDataObjectClassId"]),b=W.Form.useWatch(["resolverConfig","locationUpdateStrategy","settings","attributeDataObjectClassId"]),j=W.Form.useWatch(["resolverConfig","publishingStrategy","type"]),S=W.Form.useWatch(["resolverConfig","createLocationStrategy","settings","attributeName"]),C=W.Form.useWatch(["resolverConfig","locationUpdateStrategy","settings","attributeName"]),{data:I,isLoading:w}=eM({classId:m??""},{skip:void 0===m||""===m||"attribute"!==u}),T=(0,z.useMemo)(()=>((null==I?void 0:I.attributes)??[]).map(tq),[I]),N=T.map(e=>({value:e.key,label:e.title})),F=(null==(t=T.find(e=>e.key===g))?void 0:t.localized)??!1,{data:k,isLoading:D}=eM({classId:y??"",systemRead:!0},{skip:void 0===y||""===y||"attribute"!==f}),$=(0,z.useMemo)(()=>((null==k?void 0:k.attributes)??[]).map(tq),[k]),L=$.map(e=>({value:e.key,label:e.title})),P=(null==(a=$.find(e=>e.key===S))?void 0:a.localized)??!1,{data:B}=eM({classId:b??"",systemRead:!0},{skip:void 0===b||""===b||"attribute"!==v}),M=(0,z.useMemo)(()=>((null==B?void 0:B.attributes)??[]).map(tq),[B]),R=M.map(e=>({value:e.key,label:e.title})),E=(null==(r=M.find(e=>e.key===C))?void 0:r.localized)??!1,O=[{value:"notLoad",label:n("data-importer.resolver.loading-strategy.notLoad")},{value:"id",label:n("data-importer.resolver.loading-strategy.id")},{value:"path",label:n("data-importer.resolver.loading-strategy.path")},{value:"attribute",label:n("data-importer.resolver.loading-strategy.attribute")}],H=[{value:"staticPath",label:n("data-importer.resolver.location-strategy.staticPath")},{value:"findOrCreateFolder",label:n("data-importer.resolver.location-strategy.findOrCreateFolder")},{value:"findParent",label:n("data-importer.resolver.location-strategy.findParent")},{value:"doNotCreate",label:n("data-importer.resolver.location-strategy.doNotCreate")}],V=[{value:"noChange",label:n("data-importer.resolver.location-strategy.noChange")},{value:"staticPath",label:n("data-importer.resolver.location-strategy.staticPath")},{value:"findOrCreateFolder",label:n("data-importer.resolver.location-strategy.findOrCreateFolder")},{value:"findParent",label:n("data-importer.resolver.location-strategy.findParent")}],U=[{value:"noChangeUnpublishNew",label:n("data-importer.resolver.publishing-strategy.noChangeUnpublishNew")},{value:"noChangePublishNew",label:n("data-importer.resolver.publishing-strategy.noChangePublishNew")},{value:"alwaysPublish",label:n("data-importer.resolver.publishing-strategy.alwaysPublish")},{value:"attributeBased",label:n("data-importer.resolver.publishing-strategy.attributeBased")}],G=[{value:"id",label:n("data-importer.resolver.location-strategy.find-strategy.id")},{value:"path",label:n("data-importer.resolver.location-strategy.find-strategy.path")},{value:"attribute",label:n("data-importer.resolver.location-strategy.find-strategy.attribute")}];return(0,q.jsx)(X.FieldWidthProvider,{fieldWidthValues:{medium:600},children:(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(tx,{children:n("data-importer.resolver.title")}),(0,q.jsx)(ty,{children:(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.class"),name:["resolverConfig","dataObjectClassId"],required:!0,children:(0,q.jsx)(W.Select,{filterOption:tb,loadingSkeleton:p,options:c,placeholder:n("data-importer.resolver.class-placeholder"),showSearch:!0})})}),(0,q.jsxs)(ty,{title:n("data-importer.resolver.element-loading"),children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.loading-strategy"),name:["resolverConfig","loadingStrategy","type"],tooltip:n("data-importer.resolver.loading-strategy.tooltip"),children:(0,q.jsx)(W.Select,{filterOption:tb,options:O,showSearch:!0})}),"id"===u&&(0,q.jsx)(ty,{theme:"fieldset",title:n("data-importer.resolver.loading-strategy.id"),children:(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.loading-strategy.data-source-index"),name:["resolverConfig","loadingStrategy","settings","dataSourceIndex"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:o,placeholder:n("data-importer.resolver.loading-strategy.data-source-index-placeholder"),showSearch:!0})})}),"path"===u&&(0,q.jsx)(ty,{theme:"fieldset",title:n("data-importer.resolver.loading-strategy.path"),children:(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.loading-strategy.data-source-index"),name:["resolverConfig","loadingStrategy","settings","dataSourceIndex"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:o,placeholder:n("data-importer.resolver.loading-strategy.data-source-index-placeholder"),showSearch:!0})})}),"attribute"===u&&(0,q.jsxs)(ty,{theme:"fieldset",title:n("data-importer.resolver.loading-strategy.attribute"),children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.loading-strategy.data-source-index"),name:["resolverConfig","loadingStrategy","settings","dataSourceIndex"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:o,placeholder:n("data-importer.resolver.loading-strategy.data-source-index-placeholder"),showSearch:!0})}),(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.loading-strategy.attribute-name"),name:["resolverConfig","loadingStrategy","settings","attributeName"],children:(0,q.jsx)(W.Select,{filterOption:tb,loadingSkeleton:w,options:N,placeholder:n("data-importer.resolver.loading-strategy.attribute-name-placeholder"),showSearch:!0})}),F&&(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.loading-strategy.language"),name:["resolverConfig","loadingStrategy","settings","language"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:s,placeholder:n("data-importer.resolver.loading-strategy.language-placeholder"),showSearch:!0})}),(0,q.jsx)(W.Form.Item,{name:["resolverConfig","loadingStrategy","settings","includeUnpublished"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:n("data-importer.resolver.loading-strategy.include-unpublished"),size:"small"})})]})]}),(0,q.jsxs)(ty,{title:n("data-importer.resolver.element-creation"),children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.create-location-strategy"),name:["resolverConfig","createLocationStrategy","type"],tooltip:n("data-importer.resolver.create-location-strategy.tooltip"),children:(0,q.jsx)(W.Select,{filterOption:tb,options:H,showSearch:!0})}),"staticPath"===h&&(0,q.jsx)(ty,{theme:"fieldset",title:n("data-importer.resolver.location-strategy.staticPath"),children:(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.path"),name:["resolverConfig","createLocationStrategy","settings","path"],required:!0,rules:[{required:!0,message:n("data-importer.validation.required",{field:n("data-importer.resolver.location-strategy.path")})}],children:(0,q.jsx)(W.ManyToOneRelationPath,{allowPathTextInput:!0,allowedDataObjectTypes:["folder"],dataObjectsAllowed:!0})})}),"findOrCreateFolder"===h&&(0,q.jsxs)(ty,{theme:"fieldset",title:n("data-importer.resolver.location-strategy.findOrCreateFolder"),children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.data-source-index"),name:["resolverConfig","createLocationStrategy","settings","dataSourceIndex"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:o,placeholder:n("data-importer.resolver.location-strategy.data-source-index-placeholder"),showSearch:!0})}),(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.fallback-path"),name:["resolverConfig","createLocationStrategy","settings","fallbackPath"],tooltip:n("data-importer.resolver.location-strategy.fallback-path.tooltip"),children:(0,q.jsx)(W.Input,{placeholder:n("data-importer.resolver.location-strategy.fallback-path-placeholder")})})]}),"findParent"===h&&(0,q.jsxs)(ty,{theme:"fieldset",title:n("data-importer.resolver.location-strategy.findParent"),children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.find-strategy"),name:["resolverConfig","createLocationStrategy","settings","findStrategy"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:G,showSearch:!0})}),"attribute"===f&&(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.attribute-class"),name:["resolverConfig","createLocationStrategy","settings","attributeDataObjectClassId"],children:(0,q.jsx)(W.Select,{filterOption:tb,loadingSkeleton:p,options:c,showSearch:!0})}),(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.attribute-name"),name:["resolverConfig","createLocationStrategy","settings","attributeName"],children:(0,q.jsx)(W.Select,{filterOption:tb,loadingSkeleton:D,options:L,showSearch:!0})}),P&&(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.attribute-language"),name:["resolverConfig","createLocationStrategy","settings","attributeLanguage"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:s,showSearch:!0})})]}),(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.data-source-index"),name:["resolverConfig","createLocationStrategy","settings","dataSourceIndex"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:o,placeholder:n("data-importer.resolver.location-strategy.data-source-index-placeholder"),showSearch:!0})}),(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.fallback-path"),name:["resolverConfig","createLocationStrategy","settings","fallbackPath"],tooltip:n("data-importer.resolver.location-strategy.fallback-path.tooltip"),children:(0,q.jsx)(W.Input,{placeholder:n("data-importer.resolver.location-strategy.fallback-path-placeholder")})}),(0,q.jsx)(W.Form.Item,{name:["resolverConfig","createLocationStrategy","settings","asVariant"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:n("data-importer.resolver.location-strategy.as-variant"),size:"small"})})]})]}),(0,q.jsxs)(ty,{title:n("data-importer.resolver.element-location-update"),children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-update-strategy"),name:["resolverConfig","locationUpdateStrategy","type"],tooltip:n("data-importer.resolver.location-update-strategy.tooltip"),children:(0,q.jsx)(W.Select,{filterOption:tb,options:V,showSearch:!0})}),"staticPath"===x&&(0,q.jsx)(ty,{theme:"fieldset",title:n("data-importer.resolver.location-strategy.staticPath"),children:(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.path"),name:["resolverConfig","locationUpdateStrategy","settings","path"],required:!0,rules:[{required:!0,message:n("data-importer.validation.required",{field:n("data-importer.resolver.location-strategy.path")})}],children:(0,q.jsx)(W.ManyToOneRelationPath,{allowPathTextInput:!0,allowedDataObjectTypes:["folder"],dataObjectsAllowed:!0})})}),"findOrCreateFolder"===x&&(0,q.jsxs)(ty,{theme:"fieldset",title:n("data-importer.resolver.location-strategy.findOrCreateFolder"),children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.data-source-index"),name:["resolverConfig","locationUpdateStrategy","settings","dataSourceIndex"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:o,placeholder:n("data-importer.resolver.location-strategy.data-source-index-placeholder"),showSearch:!0})}),(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.fallback-path"),name:["resolverConfig","locationUpdateStrategy","settings","fallbackPath"],tooltip:n("data-importer.resolver.location-strategy.fallback-path.tooltip"),children:(0,q.jsx)(W.Input,{placeholder:n("data-importer.resolver.location-strategy.fallback-path-placeholder")})})]}),"findParent"===x&&(0,q.jsxs)(ty,{theme:"fieldset",title:n("data-importer.resolver.location-strategy.findParent"),children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.find-strategy"),name:["resolverConfig","locationUpdateStrategy","settings","findStrategy"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:G,showSearch:!0})}),"attribute"===v&&(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.attribute-class"),name:["resolverConfig","locationUpdateStrategy","settings","attributeDataObjectClassId"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:c,showSearch:!0})}),(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.attribute-name"),name:["resolverConfig","locationUpdateStrategy","settings","attributeName"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:R,showSearch:!0})}),E&&(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.attribute-language"),name:["resolverConfig","locationUpdateStrategy","settings","attributeLanguage"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:s,showSearch:!0})})]}),(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.data-source-index"),name:["resolverConfig","locationUpdateStrategy","settings","dataSourceIndex"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:o,placeholder:n("data-importer.resolver.location-strategy.data-source-index-placeholder"),showSearch:!0})}),(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.location-strategy.fallback-path"),name:["resolverConfig","locationUpdateStrategy","settings","fallbackPath"],tooltip:n("data-importer.resolver.location-strategy.fallback-path.tooltip"),children:(0,q.jsx)(W.Input,{placeholder:n("data-importer.resolver.location-strategy.fallback-path-placeholder")})}),(0,q.jsx)(W.Form.Item,{name:["resolverConfig","locationUpdateStrategy","settings","asVariant"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:n("data-importer.resolver.location-strategy.as-variant"),size:"small"})})]})]}),(0,q.jsxs)(ty,{title:n("data-importer.resolver.element-publishing"),children:[(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.publishing-strategy"),name:["resolverConfig","publishingStrategy","type"],tooltip:n("data-importer.resolver.publishing-strategy.tooltip"),children:(0,q.jsx)(W.Select,{filterOption:tb,options:U,showSearch:!0})}),"attributeBased"===j&&(0,q.jsx)(ty,{theme:"fieldset",title:n("data-importer.resolver.publishing-strategy.attributeBased"),children:(0,q.jsx)(W.Form.Item,{label:n("data-importer.resolver.publishing-strategy.data-source-index"),name:["resolverConfig","publishingStrategy","settings","dataSourceIndex"],children:(0,q.jsx)(W.Select,{filterOption:tb,options:o,placeholder:n("data-importer.resolver.publishing-strategy.data-source-index-placeholder"),showSearch:!0})})})]})]})})},tH=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{mappingLayout:t`
      display: flex;
      width: 100%;
      height: 100%;
      min-width: 900px;
    `,mappingLayoutLeft:t`
      flex: 2;
      min-width: 0;
      height: 100%;
      overflow-y: auto;
    `,mappingLayoutCenter:t`
      width: 46px;
      flex-shrink: 0;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
    `,mappingLayoutCenterArrow:t`
      position: sticky;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      display: flex;
      align-items: center;
      justify-content: center;
      color: ${a.colorFillActive};
    `,mappingLayoutRight:t`
      flex: 3;
      min-width: 0;
      height: 100%;
      overflow-y: auto;
    `,panel:t`
      display: flex;
      flex-direction: column;
      height: 100%;
    `,panelScrollable:t`
      flex: 1;
      min-height: 0;
      display: flex;
      flex-direction: column;
      padding: 0 ${a.paddingSM}px ${a.paddingMD}px;
    `,sourcesPanel:t`
      display: flex;
      flex-direction: column;
      height: 100%;
      background: ${a.colorFillTertiary};
    `,sourcesHeader:t`
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: ${a.paddingSM}px ${a.paddingMD}px;
      padding-bottom: ${a.paddingSM}px;
      min-height: 52px;
    `,sourcesTitle:t`
      color: ${a.colorPrimary};
      font-weight: 600;
      font-size: ${a.fontSizeLG}px;
      margin: 0;
    `,resetViewLink:t`
      color: ${a.colorPrimary};
      cursor: pointer;

      &:hover {
        text-decoration: underline;
      }
    `,sourceRowOuter:t`
      border-left: 2px solid ${a.colorBorder};
      border-radius: ${a.borderRadiusLG}px;
      box-shadow: ${a.boxShadowTertiary};
      transition: opacity 0.15s;
      position: relative;

      &:hover .source-add-btn {
        opacity: 1;
        pointer-events: auto;
      }
    `,sourceRowOuterMapped:t`
      border-left-color: ${a.colorPrimaryBorderHover};
    `,sourceRowOuterFaded:t`
      opacity: 0.4;
    `,sourceRowInner:t`
      background: ${a.colorBgContainer};
      border: 1px solid ${a.colorBorderSecondary};
      border-left: none;
      border-radius: ${a.borderRadiusLG}px;
      display: flex;
      align-items: center;
      min-height: 40px;
      overflow: hidden;
    `,sourceRowInnerMapped:t`
      cursor: pointer;
    `,sourceRowInnerUnmapped:t`
      cursor: pointer;
    `,sourceRowTagArea:t`
      flex: 1;
      display: flex;
      align-items: center;
      gap: ${a.paddingXS}px;
      padding: ${a.paddingXS}px ${a.paddingXS}px;
      min-width: 0;
    `,sourceTagInner:t`
      display: inline-flex;
      align-items: center;
    `,sourceAddBtn:t`
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.15s;
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
    `,sourceValue:t`
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      color: ${a.colorTextDescription};
      font-size: ${a.fontSize}px;
      padding: 0 ${a.paddingXS}px;
      min-width: 0;
    `,noContentWrapper:t`
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: ${a.paddingXL}px ${a.paddingMD}px;
    `,sourceRowsSpace:t`
      width: 100%;
    `,badgeMappedCount:t`
      & .ant-badge-count {
        background-color: ${a.colorFillActive};
        color: ${a.colorPrimary};
        box-shadow: none;
      }
    `,droppablePanel:t`
      width: 100%;

      & .dnd--drag-active,
      & .dnd--drag-valid,
      & .dnd--drag-error {
        background: unset !important;
        border: unset !important;
        outline: none !important;
      }
    `,panelDndWrapper:t`
      & .ant-collapse {
        transition: border-color 0.15s, border-style 0.15s;
      }

      &.dnd--drag-active .ant-collapse {
        border-style: dashed !important;
        border-color: ${a.colorBorder} !important;
      }

      &.dnd--drag-valid .ant-collapse {
        border-style: dashed !important;
        border-color: ${a.colorPrimary} !important;
      }

      &.dnd--drag-error .ant-collapse {
        border-style: dashed !important;
        border-color: ${a.colorError} !important;
      }
    `,sourceDropZone:t`
      border-radius: ${a.borderRadius}px;
      padding: 0 ${a.paddingXXS}px ${a.paddingXXS}px;
    `,mappingsHeader:t`
      display: flex;
      align-items: center;
      gap: ${a.paddingSM}px;
      padding: ${a.paddingSM}px ${a.paddingMD}px ${a.paddingSM}px 0;
      min-height: 52px;
    `,mappingsTitle:t`
      color: ${a.colorPrimary};
      font-weight: 600;
      font-size: ${a.fontSizeLG}px;
      margin: 0;
      margin-right: ${a.paddingXS}px;
    `,mappingsActions:t`
      display: flex;
      align-items: center;
      gap: ${a.paddingXS}px;
      flex: 1;
    `,mappingsDivider:t`
      margin: 0;
      height: 16px;
    `,collapseAllLink:t`
      color: ${a.colorPrimary};
      cursor: pointer;
      margin-left: auto;

      &:hover {
        text-decoration: underline;
      }
    `,mappingsContent:t`
      display: flex;
      flex-direction: column;
      flex: 1;
      gap: 0;
      padding: 0 ${a.paddingMD}px ${a.paddingMD}px 0;
    `,emptyState:t`
      flex: 1;
      display: flex;
      flex-direction: column;
      height: 100%;
      min-height: 0;

      /* Droppable renders a wrapper div — make it fill too */
      & > div {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
      }
    `,emptyStateInner:t`
      flex: 1;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: ${a.colorTextSecondary};
      line-height: 1.6;
      border-radius: ${a.borderRadiusLG}px;
      transition: background 0.15s, outline-color 0.15s;

      &.dnd--drag-active {
        outline: 2px dashed ${a.colorBorderSecondary};
        outline-offset: -2px;
      }

      &.dnd--drag-valid {
        background: ${a.colorFillSecondary};
        outline: 2px dashed ${a.colorInfoBorderHover};
        outline-offset: -2px;
      }

      &.dnd--drag-error {
        outline: 2px dashed ${a.colorErrorBorder};
        outline-offset: -2px;
      }
    `,mappingItemContent:t`
      padding: ${a.paddingSM}px ${a.paddingXS}px;
    `,mappingLabelRow:t`
      display: flex;
      align-items: center;
      gap: ${a.paddingXS}px;
      padding: 0 ${a.paddingXXS}px;
    `,mappingLabelInput:t`
      flex: 1;
    `,mappingDivider:t`
      border-top: 1px solid ${a.colorBorderSecondary};
      margin-top: ${a.paddingXS}px;
    `,sourcesDestRow:t`
      display: flex;
      align-items: stretch;
      padding-top: ${a.paddingSM}px;
    `,sourcesDestCol:t`
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: ${a.paddingXXS}px;
      padding: 0 ${a.paddingXXS}px;
    `,arrowCol:t`
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 0 ${a.paddingSM}px;
    `,arrowColSimple:t`
      justify-content: flex-start;
    `,arrowLabelSpacer:t`
      height: calc(22px + ${a.paddingXXS}px);
      flex-shrink: 0;
    `,arrowSelectRow:t`
      display: flex;
      align-items: center;
      height: ${a.controlHeight}px;
    `,arrowColAdvanced:t`
      justify-content: center;
      gap: 10px;
    `,arrowWarningBadge:t`
      display: flex;
      align-items: center;
      justify-content: center;
      color: ${a.colorWarning};
      font-size: ${a.fontSizeLG}px;
      line-height: 1;
    `,arrowGearIcon:t`
      display: flex;
      align-items: center;
      justify-content: center;
      color: ${a.colorIcon};
      font-size: ${a.fontSizeLG}px;
      line-height: 1;
    `,arrowSvg:t`
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    `,destinationTextBlock:t`
      padding: 0 ${a.paddingXXS}px;
      display: flex;
      flex-direction: column;
      gap: 0;
      justify-content: center;
      font-size: ${a.fontSizeSM}px;
      line-height: 22px;
      color: ${a.colorText};
    `,requiresAdvancedHint:t`
      padding: 0 ${a.paddingXXS}px;
      font-size: ${a.fontSizeSM}px;
      line-height: 22px;
      color: ${a.colorWarningText};
    `,languageRow:t`
      display: flex;
      justify-content: flex-end;
    `,languageSelect:t`
      flex: 1;
    `,filterEmptyState:t`
      flex: 1;
      display: flex;
      flex-direction: column;
      width: 100%;
      height: 100%;
      min-height: 0;

      /* Droppable renders a wrapper div — make it fill too */
      & > div {
        flex: 1;
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
      }
    `,filterEmptyStateInner:t`
      flex: 1;
      width: 100%;
      height: 100%;
      min-height: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: ${a.paddingSM}px;
      text-align: center;
      color: ${a.colorTextSecondary};
      border-radius: ${a.borderRadiusLG}px;
      transition: background 0.15s, outline-color 0.15s;

      &.dnd--drag-active {
        outline: 2px dashed ${a.colorBorderSecondary};
        outline-offset: -2px;
      }

      &.dnd--drag-valid {
        background: ${a.colorFillSecondary};
        outline: 2px dashed ${a.colorInfoBorderHover};
        outline-offset: -2px;
      }

      &.dnd--drag-error {
        outline: 2px dashed ${a.colorErrorBorder};
        outline-offset: -2px;
      }
    `,hiddenItem:t`
      display: none;
    `,mappingDropZoneWrapper:t`
      width: 100%;
      padding: 2px 0;

      & .dnd--drag-active,
      & .dnd--drag-valid,
      & .dnd--drag-error {
        border: none !important;
        outline: none !important;
      }
    `,mappingDropZone:t`
      width: 100%;
      height: 6px;
      border-radius: ${a.borderRadiusSM}px;
      background: transparent;
      transition: background 0.15s;
      pointer-events: none;
      flex-shrink: 0;

      &.dnd--drag-active {
        pointer-events: auto;
        background: ${a.colorBorderSecondary};
      }

      &.dnd--drag-valid {
        pointer-events: auto;
        background: ${a.colorInfoBorderHover};
      }

      &.dnd--drag-error {
        pointer-events: auto;
        background: ${a.colorErrorBorder};
      }
    `,mappingItemNew:t`
      @keyframes mapping-item-slide-in {
        0% {
          opacity: 0;
          transform: translateY(-12px);
        }
        100% {
          opacity: 1;
          transform: translateY(0);
        }
      }

      animation: mapping-item-slide-in 400ms ease-in-out;
    `}}),tW="data-importer-source-column",tX=e=>{let{configName:t,sourceRows:a,hasPreviewError:r,activeFilter:i,onSetFilter:o,onAddMappingFromSource:n}=e,{t:l}=(0,A.useTranslation)(),{styles:s}=tH(),d=W.Form.useWatch(e=>(e.mappingConfig??[]).map(e=>e.dataSourceIndex??[])),p=(0,z.useMemo)(()=>{let e={};return(d??[]).forEach(t=>{t.forEach(t=>{e[t]=(e[t]??0)+1})}),e},[d]),c=null!==i,m=a.length>0;return(0,q.jsxs)("div",{className:s.sourcesPanel,children:[(0,q.jsxs)("div",{className:s.sourcesHeader,children:[(0,q.jsx)(W.Text,{className:s.sourcesTitle,children:l("data-importer.mapping.sources.title")}),c&&(0,q.jsx)("span",{className:s.resetViewLink,onClick:()=>{o(null)},children:l("data-importer.mapping.sources.reset-view")})]}),(0,q.jsxs)("div",{className:s.panelScrollable,children:[!m&&r&&(0,q.jsx)("div",{className:s.noContentWrapper,children:(0,q.jsx)(W.NoContent,{text:l("data-importer.mapping.sources.empty")})}),m&&(0,q.jsx)(W.Space,{className:s.sourceRowsSpace,direction:"vertical",size:"extra-small",children:a.map(e=>{let t=p[e.dataIndex]??0,a=t>0,r=c&&i!==e.dataIndex;return(0,q.jsx)("div",{className:`${s.sourceRowOuter}${a?` ${s.sourceRowOuterMapped}`:""}${r?` ${s.sourceRowOuterFaded}`:""}`,children:(0,q.jsx)(W.Draggable,{info:{type:tW,title:e.label,icon:{value:"workflow"},data:{dataIndex:e.dataIndex,label:e.label}},children:(0,q.jsxs)("div",{className:`${s.sourceRowInner}${a?` ${s.sourceRowInnerMapped}`:` ${s.sourceRowInnerUnmapped}`}`,onClick:()=>{o(i===e.dataIndex?null:e.dataIndex)},children:[(0,q.jsxs)("div",{className:s.sourceRowTagArea,children:[(0,q.jsx)("div",{className:s.sourceTagInner,children:(0,q.jsxs)(W.Space,{size:"extra-small",children:[(0,q.jsx)(W.Tag,{color:a?"purple":void 0,children:e.label}),a&&(0,q.jsx)(W.Badge,{className:s.badgeMappedCount,count:t,size:"large"})]})}),!a&&(0,q.jsx)("span",{className:`source-add-btn ${s.sourceAddBtn}`,children:(0,q.jsx)(W.IconButton,{icon:{value:"plus-circle"},onClick:t=>{t.stopPropagation(),n(e.dataIndex,e.label),o(e.dataIndex)},size:"small",tooltip:{title:l("data-importer.mapping.add")},type:"default"})})]}),(0,q.jsx)("span",{className:s.sourceValue,title:e.value,children:e.value})]})})},e.dataIndex)})})]})]})};var tV=a(5710);let tU=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{row:t`
      display: flex;
      align-items: center;
      gap: ${a.paddingXS}px;
      cursor: pointer;
      height: 38px;
      padding: ${a.paddingXXS}px ${a.paddingSM}px;
      user-select: none;
    `,rowWithBorder:t`
      border-bottom: 1px solid ${a.colorBorderSecondary};
    `,badge:t`
      width: 24px;
      height: 24px;
      border-radius: 32px;
      background: ${a.colorPrimary};
      color: ${a.colorTextLightSolid};
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
      flex-shrink: 0;
    `,title:t`
      font-size: 12px;
      color: ${a.colorText};
      flex: 1;
    `}}),tG=e=>{let{step:t,title:a,expanded:r,onToggle:i,hasBorderBottom:o}=e,{token:n}=tV.A.useToken(),{styles:l,cx:s}=tU();return(0,q.jsxs)("div",{className:s(l.row,o&&l.rowWithBorder),onClick:i,children:[(0,q.jsx)("div",{className:l.badge,children:t}),(0,q.jsx)("span",{className:l.title,children:a}),r?(0,q.jsx)("svg",{fill:"none",height:"16",viewBox:"0 0 16 16",width:"16",xmlns:"http://www.w3.org/2000/svg",children:(0,q.jsx)("path",{d:"M3 10L8 5L13 10",stroke:n.colorTextSecondary,strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:"1.5"})}):(0,q.jsx)("svg",{fill:"none",height:"16",viewBox:"0 0 16 16",width:"16",xmlns:"http://www.w3.org/2000/svg",children:(0,q.jsx)("path",{d:"M3 6L8 11L13 6",stroke:n.colorTextSecondary,strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:"1.5"})})]})};function t_(e){return{dataIndex:String(e.dataIndex??""),label:String(e.label??e.dataIndex??""),value:"string"==typeof e.data?e.data:void 0!==e.data?JSON.stringify(e.data):""}}let tK=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{wrapper:t`
      display: flex;
      flex-direction: column;
      gap: ${a.paddingXS}px;
      flex: 1;
    `,header:t`
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 32px;
    `,title:t`
      font-size: 12px;
      font-weight: 600;
      color: ${a.colorPrimaryText};
    `,buttonGroup:t`
      display: flex;
      gap: ${a.paddingXXS}px;
    `,tableWrapper:t`
      min-height: 180px;
      max-height: 420px;
      border: 0.5px solid ${a.colorBorderSecondary};
      border-radius: ${a.borderRadiusSM}px;
      overflow: hidden;
      overflow-y: auto;
    `,tableHeader:t`
      display: grid;
      grid-template-columns: 1fr 1fr;
      background: ${a.colorFillAlter};
      border-bottom: 0.5px solid ${a.colorBorderSecondary};
    `,tableHeaderCell:t`
      font-size: 12px;
      padding: 6px ${a.paddingXS}px;
      font-weight: 500;
    `,tableHeaderCellBorder:t`
      border-right: 0.5px solid ${a.colorBorderSecondary};
    `,tableRow:t`
      display: grid;
      grid-template-columns: 1fr 1fr;
      background: ${a.colorBgContainer};
      height: 32px;
    `,tableRowHighlighted:t`
      background: ${a.colorSuccessBg};
    `,tableRowBorder:t`
      border-bottom: 0.5px solid ${a.colorBorderSecondary};
    `,tableCell:t`
      font-size: 12px;
      padding: 0 ${a.paddingXS}px;
      display: flex;
      align-items: center;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    `,tableCellBorder:t`
      border-right: 0.5px solid ${a.colorBorderSecondary};
    `,stateMessage:t`
      padding: ${a.paddingMD}px ${a.paddingXS}px;
      font-size: 12px;
      color: ${a.colorTextSecondary};
      text-align: center;
    `,previewArea:t`
      min-height: 100px;
      overflow-y: auto;
    `,muted:t`
      font-size: 12px;
      color: ${a.colorTextQuaternary};
    `,previewLine:t`
      font-size: 12px;
      color: ${a.colorTextSecondary};
      margin-bottom: ${a.paddingXXS}px;
    `}}),tQ=e=>{let{token:t}=tV.A.useToken(),{t:a}=(0,A.useTranslation)(),{styles:r,cx:i}=tK(),[o,n]=(0,z.useState)([]),[l,s]=(0,z.useState)(0),[d,p]=(0,z.useState)(0),[c,m]=(0,z.useState)(""),{dataPreview:u,currentRecordIndex:g,isLoading:h,isFetching:x,isError:f,load:v}=tA({configName:e.configName,enabled:"import"===e.mode}),[y,b]=(0,z.useState)([]),[j,S]=(0,z.useState)(0),[C,I]=(0,z.useState)(void 0),{data:w,isLoading:T,isFetching:N,isError:F}=eD(C,{skip:"result"!==e.mode||void 0===C,refetchOnMountOrArgChange:!1});(0,z.useEffect)(()=>{n(u.map(e=>t_(e)))},[u]),(0,z.useEffect)(()=>{p(g)},[g]),(0,z.useEffect)(()=>{f&&n([])},[f]);let k=t=>{var a,r,i,o;let n=void 0!==e.currentMappingItem?{mappingConfig:[e.currentMappingItem],...(null==(a=e.baseConfig)?void 0:a.loaderConfig)!==void 0&&{loaderConfig:e.baseConfig.loaderConfig},...(null==(r=e.baseConfig)?void 0:r.interpreterConfig)!==void 0&&{interpreterConfig:e.baseConfig.interpreterConfig},...(null==(i=e.baseConfig)?void 0:i.resolverConfig)!==void 0&&{resolverConfig:e.baseConfig.resolverConfig},...(null==(o=e.baseConfig)?void 0:o.processingConfig)!==void 0&&{processingConfig:e.baseConfig.processingConfig}}:void 0;I({name:e.configName,bundleDataImporterLoadPreviewParameters:{recordNumber:t,...void 0!==n&&{currentConfig:n}}})};(0,z.useEffect)(()=>{if("result"!==e.mode||void 0===w)return;let t=w.transformationResultPreviews??[];b(void 0!==e.currentMappingItem?t.slice(0,1):t)},[e.mode,w,e.currentMappingItem]),(0,z.useEffect)(()=>{F&&b([])},[F]),(0,z.useEffect)(()=>{"result"===e.mode&&k(j)},[e.refreshToken]);let D=(e,t,a,i,o)=>(0,q.jsxs)("div",{className:r.header,children:[(0,q.jsx)("span",{className:r.title,children:e}),(0,q.jsxs)("div",{className:r.buttonGroup,children:[(0,q.jsx)(W.IconButton,{disabled:!t||a,icon:{value:"chevron-left"},onClick:i,size:"small",type:"default"}),(0,q.jsx)(W.IconButton,{disabled:a,icon:{value:"chevron-right"},onClick:o,size:"small",type:"default"})]})]});if("import"===e.mode){let n=""===c.trim()?o:o.filter(e=>e.label.toLowerCase().includes(c.toLowerCase())||e.value.toLowerCase().includes(c.toLowerCase()));return(0,q.jsxs)("div",{className:r.wrapper,children:[D(a("data-importer.mapping.advanced-modal.step-source.import-preview"),d>0,h||x,()=>{let e=Math.max(0,l-1);s(e),v(e)},()=>{let e=l+1;s(e),v(e)}),(0,q.jsx)(W.Input,{onChange:e=>{m(e.target.value)},placeholder:a("data-importer.mapping.advanced-modal.step-source.search-placeholder"),prefix:(0,q.jsxs)("svg",{fill:"none",height:"14",viewBox:"0 0 14 14",width:"14",xmlns:"http://www.w3.org/2000/svg",children:[(0,q.jsx)("path",{d:"M6.125 11.375C9.02246 11.375 11.375 9.02246 11.375 6.125C11.375 3.22754 9.02246 0.875 6.125 0.875C3.22754 0.875 0.875 3.22754 0.875 6.125C0.875 9.02246 3.22754 11.375 6.125 11.375Z",stroke:t.colorTextTertiary,strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:"1.5"}),(0,q.jsx)("path",{d:"M12.25 12.25L10.0625 10.0625",stroke:t.colorTextTertiary,strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:"1.5"})]}),value:c}),(0,q.jsxs)("div",{className:r.tableWrapper,children:[(0,q.jsxs)("div",{className:r.tableHeader,children:[(0,q.jsx)("div",{className:i(r.tableHeaderCell,r.tableHeaderCellBorder),children:a("data-importer.mapping.advanced-modal.step-source.column-name")}),(0,q.jsx)("div",{className:r.tableHeaderCell,children:a("data-importer.mapping.advanced-modal.step-source.column-data")})]}),(h||x)&&(0,q.jsx)("div",{className:r.stateMessage,children:(0,q.jsx)(W.Spin,{type:"classic"})}),!(h||x)&&n.map((t,a)=>{let o=e.selectedDataSourceIndex.includes(t.dataIndex),l=a===n.length-1;return(0,q.jsxs)("div",{className:i(r.tableRow,o&&r.tableRowHighlighted,!l&&r.tableRowBorder),children:[(0,q.jsx)("div",{className:i(r.tableCell,r.tableCellBorder),children:t.label}),(0,q.jsx)("div",{className:r.tableCell,children:t.value})]},a)}),!(h||x)&&0===n.length&&(0,q.jsx)("div",{className:r.stateMessage,children:a("data-importer.mapping.advanced-modal.no-data")})]})]})}return(0,q.jsxs)("div",{className:r.wrapper,children:[D(a("data-importer.mapping.advanced-modal.step-target.preview-result"),j>0,T||N,()=>{let e=Math.max(0,j-1);S(e),k(e)},()=>{let e=j+1;S(e),k(e)}),(0,q.jsxs)("div",{className:r.previewArea,children:[(T||N)&&(0,q.jsx)("div",{className:r.muted,children:(0,q.jsx)(W.Spin,{type:"classic"})}),!(T||N)&&0===y.length&&(0,q.jsx)("div",{className:r.muted,children:a("data-importer.mapping.advanced-modal.no-preview")}),!(T||N)&&y.map((e,t)=>(0,q.jsx)("div",{className:r.previewLine,children:e},t))]})]})},tY=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{twoColumnLayout:t`
      display: flex;
      gap: ${a.paddingXS}px;
    `,navButtons:t`
      display: flex;
      justify-content: flex-end;
      gap: ${a.paddingXS}px;
    `,outlineButton:t`
      height: 32px;
      padding: 0 15px;
      font-size: 12px;
      color: ${a.colorPrimary};
      background: ${a.colorBgContainer};
      border: 1px solid ${a.colorPrimaryBorder};
      border-radius: ${a.borderRadius}px;
      cursor: pointer;
      box-shadow: ${a.boxShadowTertiary};
    `,boxHeader:t`
      padding: ${a.paddingXXS}px ${a.paddingXS}px;
    `,boxHeaderTitle:t`
      font-size: 12px;
      font-weight: 600;
    `,selectFull:t`
      width: 100%;
      &.ant-select {
        max-width: 100% !important;
      }
    `}}),tZ=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{twoColumnLayout:t`
      display: flex;
      gap: ${a.paddingXS}px;
      min-height: 280px;
      width: 100%;
      overflow: hidden;
    `,leftColumn:t`
      flex: 1 1 0;
      min-width: 0;
      background: ${a.colorFillAdditional};
      border-radius: ${a.borderRadius}px;
      padding: 10px ${a.paddingSM}px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    `,labelSmall:t`
      font-size: 12px;
    `,selectFull:t`
      width: 100%;
      /* Ant Design sets max-width: 9999px as an inline style on .ant-select
         for multiple-mode selects — override it so the select cannot overflow
         its flex container. */
      &.ant-select {
        max-width: 100% !important;
      }
    `,rightColumn:t`
      flex: 1 1 0;
      min-width: 0;
      display: flex;
      flex-direction: column;
    `,footer:t`
      display: flex;
      justify-content: flex-end;
      padding-top: ${a.paddingXS}px;
    `}}),tJ=e=>{let{configName:t,dataSourceIndex:a,columnHeaderOptions:r,onDataSourceIndexChange:i,onNext:o}=e,{t:n}=(0,A.useTranslation)(),{styles:l}=tZ(),{styles:s}=tY();return(0,q.jsxs)(q.Fragment,{children:[(0,q.jsxs)("div",{className:l.twoColumnLayout,children:[(0,q.jsxs)("div",{className:l.leftColumn,children:[(0,q.jsx)(W.Text,{className:l.labelSmall,strong:!0,children:n("data-importer.mapping.advanced-modal.step-source.label")}),(0,q.jsx)(W.Text,{className:l.labelSmall,type:"secondary",children:n("data-importer.mapping.advanced-modal.step-source.description")}),(0,q.jsx)(W.Select,{className:l.selectFull,mode:"multiple",onChange:i,options:r,placeholder:n("data-importer.mapping.item.source-placeholder"),showSearch:!0,value:a})]}),(0,q.jsx)("div",{className:l.rightColumn,children:(0,q.jsx)(tQ,{configName:t,mode:"import",selectedDataSourceIndex:a})})]}),(0,q.jsx)("div",{className:l.footer,children:(0,q.jsx)("button",{className:s.outlineButton,onClick:o,type:"button",children:n("data-importer.mapping.advanced-modal.next-step")})})]})};var t0=a(8668),t1=a(3627),t2=a(8831);let t3=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{twoColumnLayout:t`
      display: flex;
      gap: ${a.paddingXS}px;
      width: 100%;
      overflow: hidden;
    `,leftColumn:t`
      flex: 1 0 0;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: ${a.paddingXS}px;
    `,listHeader:t`
      display: flex;
      align-items: center;
      gap: ${a.paddingXS}px;
      height: 32px;
      flex-shrink: 0;
    `,listHeaderTitle:t`
      font-size: 12px;
      font-weight: 600;
      color: ${a.colorPrimaryText};
      flex: 1;
    `,collapseAllLink:t`
      font-size: 12px;
      color: ${a.colorPrimaryText};
      cursor: pointer;
      white-space: nowrap;

      &:hover {
        text-decoration: underline;
      }
    `,itemsList:t`
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
      overflow-y: auto;
    `,emptyState:t`
      font-size: 12px;
      color: ${a.colorTextQuaternary};
    `,transformerCardOverlay:t`
      border: 1px solid ${a.colorBorderSecondary};
      border-radius: ${a.borderRadiusSM}px;
      padding: 6px ${a.paddingXS}px;
      display: flex;
      flex-direction: column;
      gap: ${a.paddingXXS}px;
      background: ${a.colorFillAdditional};
      box-shadow: ${a.boxShadowSecondary};
      cursor: grabbing;
    `,rightColumn:t`
      flex: 1 0 0;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: ${a.paddingXS}px;
      padding-left: ${a.paddingXXS}px;
    `,rightColumnTop:t`
      display: flex;
      flex-direction: column;
      gap: ${a.paddingXS}px;
    `,sourceSection:t`
      display: flex;
      flex-direction: column;
      gap: ${a.paddingXXS}px;
    `,sourceSectionHeader:t`
      display: flex;
      align-items: center;
      gap: ${a.paddingXXS}px;
      height: 32px;
    `,sourceSectionTitle:t`
      font-size: 12px;
      font-weight: 600;
      color: ${a.colorPrimaryText};
    `,sourceValues:t`
      font-size: 12px;
      color: ${a.colorText};
      line-height: 22px;
      flex-wrap: wrap;
      display: flex;
      gap: 0;
    `,sourceSeparator:t`
      color: ${a.colorTextSecondary};
      margin: 0 4px;
    `,previewWrapper:t`
      flex: 1;
      overflow: hidden;
    `}});var t5=a(4979);let t6=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{transformerCard:t`
      border: 1px solid ${a.colorBorderSecondary};
      border-radius: ${a.borderRadiusSM}px;
      padding: 6px ${a.paddingXS}px;
      display: flex;
      flex-direction: column;
      gap: ${a.paddingXXS}px;
      background: ${a.colorFillAdditional};
    `,transformerCardOverlay:t`
      border: 1px solid ${a.colorBorderSecondary};
      border-radius: ${a.borderRadiusSM}px;
      padding: 6px ${a.paddingXS}px;
      display: flex;
      flex-direction: column;
      gap: ${a.paddingXXS}px;
      background: ${a.colorFillAdditional};
      box-shadow: ${a.boxShadowSecondary};
      cursor: grabbing;
    `,transformerCardHeader:t`
      display: flex;
      align-items: center;
      gap: ${a.paddingXXS}px;
    `,dragHandle:t`
      display: flex;
      align-items: center;
      cursor: grab;
      user-select: none;
      flex-shrink: 0;
      margin-right: ${a.paddingXXS}px;

      &:active {
        cursor: grabbing;
      }
    `,dragHandleIcon:t`
      font-size: 14px;
      line-height: 1;
      color: ${a.colorTextTertiary};
    `,transformerLabel:t`
      font-weight: 400;
      font-size: 12px;
      color: ${a.colorText};
    `,transformerCollapseIcon:t`
      display: flex;
      align-items: center;
      color: ${a.colorText};
      flex-shrink: 0;
    `,transformerDeleteButton:t`
      margin-left: auto;
      color: ${a.colorPrimary} !important;

      &:hover {
        color: ${a.colorPrimaryHover} !important;
        background: transparent !important;
      }
    `}}),t9=e=>{let{label:t,isCollapsed:a,children:r,dragHandleProps:i,index:o,onToggleCollapse:n,onRemove:l,removeTooltip:s,collapseTooltip:d}=e,{styles:p}=t6();return(0,q.jsxs)(q.Fragment,{children:[(0,q.jsxs)("div",{className:p.transformerCardHeader,children:[(0,q.jsx)("div",{className:p.dragHandle,...i,children:(0,q.jsx)("span",{className:p.dragHandleIcon,children:"⠿"})}),(0,q.jsx)("span",{className:p.transformerLabel,children:t}),(0,q.jsx)(W.IconButton,{className:p.transformerCollapseIcon,icon:{value:a?"chevron-down":"chevron-up"},onClick:()=>{n(o)},size:"small",tooltip:{title:d},type:"text"}),(0,q.jsx)(W.IconButton,{className:p.transformerDeleteButton,icon:{value:"trash"},onClick:()=>{l(o)},size:"small",tooltip:{title:s},type:"text"})]}),!a&&r]})},t4=e=>{let{item:t,index:a,label:r,isCollapsed:i,children:o,onToggleCollapse:n,onRemove:l,removeTooltip:s,collapseTooltip:d}=e,{styles:p}=t6(),{attributes:c,listeners:m,setNodeRef:u,transform:g,transition:h,isDragging:x}=(0,t1.gl)({id:t._id}),f={transform:t5.Ks.Transform.toString(g),transition:h,opacity:+!x};return(0,q.jsx)("div",{className:p.transformerCard,ref:u,style:f,children:(0,q.jsx)(t9,{collapseTooltip:d,dragHandleProps:{...c,...m},index:a,isCollapsed:i,label:r,onRemove:l,onToggleCollapse:n,removeTooltip:s,children:o})})},t7=e=>({...e,_id:(0,tw.v4)()}),t8=e=>{let{_id:t,...a}=e;return a},ae=e=>{let{configName:t,pipeline:a,dataSourceIndex:i,columnHeaderOptions:o,previewRefreshToken:l,currentMappingItem:s,baseConfig:d,onPipelineChange:p,onDataSourceIndexChange:c,onPrev:m,onNext:u}=e,{t:g}=(0,A.useTranslation)(),{styles:h}=t3(),{styles:x}=tY(),[f,v]=(0,z.useState)(()=>a.map(t7)),y=(0,z.useRef)(a);(0,z.useEffect)(()=>{a!==y.current&&(y.current=a,v(e=>a.map((t,a)=>{let r=e[a];return void 0!==r&&r.type===t.type?{...t,_id:r._id}:t7(t)})))},[a]);let[b,j]=(0,z.useState)({}),[S,C]=(0,z.useState)(!1),[I,w]=(0,z.useState)(null),T=(0,t0.FR)((0,t0.MS)(t0.AN,{activationConstraint:{distance:8}}),(0,t0.MS)(t0.uN,{coordinateGetter:t1.JR})),N=(0,z.useMemo)(()=>r.container.get(n),[]),F=(0,z.useMemo)(()=>{let e=N.getAllTypes(),t=t=>e.filter(e=>e.group===t).map(e=>({key:e.id,label:e.label}));return[{key:"dataManipulation",label:g("data-importer.mapping.advanced-modal.transformer.group.data-manipulation"),children:t("dataManipulation")},{key:"dataTypes",label:g("data-importer.mapping.advanced-modal.transformer.group.data-types"),children:t("dataTypes")},{key:"loadImport",label:g("data-importer.mapping.advanced-modal.transformer.group.load-import"),children:t("loadImport")}]},[N]),k=e=>{let t=f[e],a=f.filter((t,a)=>a!==e);v(a),j(e=>{let{[t._id]:a,...r}=e;return r}),p(a.map(t8))},D=(e,t)=>{let a=f.map((a,r)=>r===e?{...a,settings:t}:a);v(a),p(a.map(t8))},$=e=>{var t;let a=null==(t=f[e])?void 0:t._id;void 0!==a&&j(e=>({...e,[a]:!e[a]}))},L=f.length>0&&f.every(e=>b[e._id]);return(0,q.jsxs)("div",{className:h.twoColumnLayout,children:[(0,q.jsxs)("div",{className:h.leftColumn,children:[(0,q.jsxs)("div",{className:h.listHeader,children:[(0,q.jsx)("span",{className:h.listHeaderTitle,children:g("data-importer.mapping.advanced-modal.step-transformations")}),(0,q.jsx)(W.Dropdown,{menu:{items:F,onClick:e=>{let t,{key:a}=e;v(t=[...f,t7({type:String(a),settings:{}})]),p(t.map(t8))}},trigger:["click"],children:(0,q.jsx)(W.IconTextButton,{icon:{value:"add"},size:"small",type:"default",children:g("data-importer.mapping.add")})}),f.length>0&&(0,q.jsx)("span",{className:h.collapseAllLink,onClick:()=>{if(L)j({});else{let e={};f.forEach(t=>{e[t._id]=!0}),j(e)}},children:L?g("data-importer.mapping.expand-all"):g("data-importer.mapping.collapse-all")})]}),(0,q.jsxs)("div",{className:h.itemsList,children:[0===f.length&&(0,q.jsx)("span",{className:h.emptyState,children:g("data-importer.mapping.advanced-modal.no-transformers")}),(0,q.jsxs)(t0.Mp,{collisionDetection:t0.fp,modifiers:[t2.FN],onDragEnd:e=>{w(null);let{active:t,over:a}=e;if(null===a||t.id===a.id)return;let r=f.findIndex(e=>e._id===t.id),i=f.findIndex(e=>e._id===a.id);if(-1===r||-1===i)return;let o=[...f],[n]=o.splice(r,1);o.splice(i,0,n),v(o),p(o.map(t8))},onDragStart:e=>{w(String(e.active.id))},sensors:T,children:[(0,q.jsx)(t1.gB,{items:f.map(e=>e._id),strategy:t1._G,children:f.map((e,t)=>{let a="string"==typeof e.type?e.type:"",r=N.getDynamicType(a),i=b[e._id],o=e.settings??{};return(0,q.jsx)(t4,{collapseTooltip:i?g("data-importer.mapping.expand-all"):g("data-importer.mapping.collapse-all"),index:t,isCollapsed:i,item:e,label:(null==r?void 0:r.label)??a,onRemove:k,onToggleCollapse:$,removeTooltip:g("data-importer.mapping.advanced-modal.transformer.remove"),children:null==r?void 0:r.renderSettings(o,e=>{D(t,e)})},e._id)})}),(0,q.jsx)(t0.Hd,{children:(()=>{if(null===I)return null;let e=f.findIndex(e=>e._id===I);if(-1===e)return null;let t=f[e],a="string"==typeof t.type?t.type:"",r=N.getDynamicType(a),i=b[t._id],o=t.settings??{};return(0,q.jsx)("div",{className:h.transformerCardOverlay,children:(0,q.jsx)(t9,{collapseTooltip:i?g("data-importer.mapping.expand-all"):g("data-importer.mapping.collapse-all"),index:e,isCollapsed:i,label:(null==r?void 0:r.label)??a,onRemove:k,onToggleCollapse:$,removeTooltip:g("data-importer.mapping.advanced-modal.transformer.remove"),children:null==r?void 0:r.renderSettings(o,t=>{D(e,t)})})})})()})]})]})]}),(0,q.jsxs)("div",{className:h.rightColumn,children:[(0,q.jsxs)("div",{className:h.rightColumnTop,children:[(0,q.jsxs)("div",{className:h.sourceSection,children:[(0,q.jsxs)("div",{className:h.sourceSectionHeader,children:[(0,q.jsx)("span",{className:h.sourceSectionTitle,children:g("data-importer.mapping.advanced-modal.step-source.label")}),(0,q.jsx)(W.IconButton,{icon:{value:"edit-pen"},onClick:()=>{C(e=>!e)},size:"small",tooltip:{title:g("data-importer.mapping.advanced-modal.transformer.edit-source")},type:"text"})]}),S?(0,q.jsx)(W.Select,{className:x.selectFull,mode:"multiple",onBlur:()=>{C(!1)},onChange:e=>{c(Array.isArray(e)?e:[]),C(!1)},options:o,showSearch:!0,value:i}):(0,q.jsx)("div",{className:h.sourceValues,children:0===i.length?(0,q.jsx)("span",{className:h.emptyState,children:"—"}):i.map((e,t)=>{let a;return(0,q.jsxs)(H().Fragment,{children:[t>0&&(0,q.jsx)("span",{className:h.sourceSeparator,children:" | "}),(0,q.jsx)("span",{children:(null==(a=o.find(t=>t.value===e))?void 0:a.label)??e})]},e)})})]}),(0,q.jsx)("div",{className:h.previewWrapper,children:(0,q.jsx)(tQ,{baseConfig:d,configName:t,currentMappingItem:s,mode:"result",refreshToken:l})})]}),(0,q.jsxs)("div",{className:x.navButtons,children:[(0,q.jsx)("button",{className:x.outlineButton,onClick:m,type:"button",children:g("data-importer.mapping.advanced-modal.previous-step")}),(0,q.jsx)("button",{className:x.outlineButton,onClick:u,type:"button",children:g("data-importer.mapping.advanced-modal.next-step")})]})]})]})},at=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{twoColumnLayout:t`
      display: flex;
      gap: ${a.paddingXS}px;
      min-height: 240px;
    `,leftColumn:t`
      flex: 0 0 calc(50% - 4px);
      min-width: 0;
      background: ${a.colorFillAdditional};
      border-radius: ${a.borderRadius}px;
      display: flex;
      flex-direction: column;
    `,leftHeader:t`
      padding: ${a.paddingXXS}px ${a.paddingXS}px;
      height: 32px;
      display: flex;
      align-items: center;
    `,leftHeaderTitle:t`
      font-size: 12px;
    `,fieldsContainer:t`
      padding: 0 ${a.paddingXS}px ${a.paddingXS}px ${a.paddingXS}px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;

      & > div {
        min-width: 0;
      }
    `,fieldLabel:t`
      font-size: 12px;
      margin-bottom: ${a.paddingXXS}px;
    `,overwriteLabel:t`
      font-size: 12px;
      margin-top: 2px;
    `,switchLabel:t`
      font-size: 12px;
    `,selectFull:t`
      width: 100%;
      height: 32px;
    `,selectSkeletonWrapper:t`
      width: 100%;
      min-width: 0;

      & > * {
        width: 100%;
        min-width: 0;
      }
    `,typeError:t`
      color: ${a.colorErrorText};
      font-size: 12px;
      line-height: 18px;
      background: ${a.colorErrorBg};
      border: 1px solid ${a.colorErrorBorder};
      border-radius: ${a.borderRadius}px;
      padding: ${a.paddingXXS}px ${a.paddingXS}px;
    `,classificationStoreKeyRow:t`
      display: flex;
      align-items: center;
      gap: ${a.paddingXS}px;
    `,classificationStoreKeyInput:t`
      flex: 1;
    `,rightColumn:t`
      flex: 0 0 calc(50% - 4px);
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    `,previewWrapper:t`
      flex: 1;
      overflow: hidden;
    `}}),aa=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{toolbar:t`
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: ${a.paddingSM}px;
      margin-bottom: ${a.paddingSM}px;
    `,search:t`
      width: 320px;
      max-width: 100%;
    `,paginationRow:t`
      display: flex;
      justify-content: flex-end;
      margin-top: ${a.paddingSM}px;
    `,footer:t`
      display: flex;
      justify-content: flex-end;
      gap: ${a.paddingXS}px;
      margin-top: ${a.paddingSM}px;
    `}}),ar=(0,tR.createColumnHelper)(),ai=e=>{let{open:t,classId:a,fieldName:r,transformationResultType:i,onClose:o,onSelect:n}=e,{t:l}=(0,A.useTranslation)(),{styles:s}=aa(),[d,p]=(0,z.useState)(""),[c,m]=(0,z.useState)(1),[u,g]=(0,z.useState)(10),[h,x]=(0,z.useState)(null),{data:f,isFetching:v}=eb({classId:a,fieldName:r,transformationResultType:i,start:(c-1)*u,limit:u,searchfilter:""!==d?d:void 0},{skip:!t}),y=(null==f?void 0:f.data)??[],b=(null==f?void 0:f.total)??0,j=(0,z.useMemo)(()=>[ar.accessor("groupName",{header:l("classification-store.column.group")}),ar.accessor("keyName",{header:l("classification-store.column.name")}),ar.accessor("keyDescription",{header:l("classification-store.column.description")})],[l]);return(0,q.jsxs)(W.Modal,{footer:null,onCancel:o,open:t,size:"XL",title:l("data-importer.mapping.advanced-modal.step-target.classification-store-key-modal.title"),children:[(0,q.jsxs)("div",{className:s.toolbar,children:[(0,q.jsx)(W.Text,{children:l("data-importer.mapping.advanced-modal.step-target.classification-store-key-modal.description")}),(0,q.jsx)(W.SearchInput,{className:s.search,onChange:e=>{p(e.target.value),m(1)},placeholder:l("data-importer.mapping.advanced-modal.step-source.search-placeholder"),value:d,withClear:!0,withPrefix:!0})]}),(0,q.jsx)(W.Grid,{columns:j,data:y,isLoading:v,onSelectedRowsChange:e=>{x(Object.keys(e)[0]??null)},selectedRows:null!==h?{[h]:!0}:void 0,setRowId:e=>e.id??""}),(0,q.jsx)("div",{className:s.paginationRow,children:(0,q.jsx)(W.Pagination,{current:c,defaultPageSize:u,onChange:(e,t)=>{m(e),g(t)},showSizeChanger:!0,showTotal:e=>l("pagination.show-total",{total:e}),total:b})}),(0,q.jsxs)("div",{className:s.footer,children:[(0,q.jsx)(W.Button,{onClick:o,type:"default",children:l("common.cancel")}),(0,q.jsx)(W.Button,{disabled:null===h,onClick:()=>{null!==h&&n(h)},type:"primary",children:l("common.apply-selection")})]})]})},ao=e=>{var t,a,r,i,o,n,l,s,d,p,c;let{attributesMap:m,transformationResultType:u,dataTarget:g,languageOptions:h,classId:x,configName:f,previewRefreshToken:v,currentMappingItem:y,baseConfig:b,onDataTargetChange:j,onPrev:S,onConfirm:C}=e,{t:I}=(0,A.useTranslation)(),{styles:w}=at(),{styles:T}=tY(),[N,F]=(0,z.useState)(!1),k=(0,z.useRef)(u),D=(null==g?void 0:g.type)==="direct",$=(null==g?void 0:g.type)==="classificationstore",L=(null==g?void 0:g.type)==="classificationstoreBatch",P=(null==g?void 0:g.type)==="manyToManyRelation",B=(0,z.useMemo)(()=>new Set(["array","quantityValueArray","inputQuantityValueArray","dateArray"]),[]),M=(0,z.useMemo)(()=>new Set(["advancedDataObject","dataObjectArray","assetArray","advancedAssetArray"]),[]),R=!L||B.has(u??""),E=!P||M.has(u??""),O=!R||!E,H=m[tn(u)]??[],{data:X,isFetching:V}=ev({classId:x??""},{skip:void 0===x||!$&&!L}),U=(0,z.useMemo)(()=>((null==X?void 0:X.attributes)??[]).filter(e=>void 0!==e.key).map(e=>({key:e.key??"",title:e.title??e.name??e.key??"",localized:e.localized??!1})),[null==X?void 0:X.attributes]),G=$||L?U:H,_=G.map(e=>({value:e.key,label:e.title})),K=(null==(t=G.find(e=>{var t;return e.key===(null==g||null==(t=g.settings)?void 0:t.fieldName)}))?void 0:t.localized)??!1,Q=(null==g||null==(a=g.settings)?void 0:a.writeIfTargetIsNotEmpty)??!1,Y=P&&Q,Z=D||P,J=null==g||null==(r=g.settings)?void 0:r.keyId,ee=void 0!==x&&(null==g||null==(i=g.settings)?void 0:i.fieldName)!==void 0&&void 0!==u&&""!==u&&!O,{data:et}=ey({keyId:J??""},{skip:void 0===J||""===J}),ea=(0,z.useMemo)(()=>void 0===J||""===J?I("data-importer.mapping.advanced-modal.step-target.classification-store-key-placeholder"):(null==et?void 0:et.groupName)!==void 0&&(null==et?void 0:et.keyName)!==void 0?I("data-importer.mapping.advanced-modal.step-target.classification-store-key-in-group",{key:et.keyName,group:et.groupName}):J,[J,null==et?void 0:et.groupName,null==et?void 0:et.keyName,I]),er=(0,z.useMemo)(()=>R?E?void 0:I("data-importer.mapping.advanced-modal.step-target.type-error.manyToManyRelation"):I("data-importer.mapping.advanced-modal.step-target.type-error.classificationstoreBatch"),[R,E,I]);return(0,z.useEffect)(()=>{var e,t;Z&&(Q||(null==g||null==(e=g.settings)?void 0:e.overwriteMode)===void 0&&(null==g||null==(t=g.settings)?void 0:t.writeIfSourceIsEmpty)!==!0||j({...g,settings:{...g.settings,overwriteMode:void 0,writeIfSourceIsEmpty:!1}}))},[Q,Z]),(0,z.useEffect)(()=>{var e;k.current!==u&&(k.current=u,($||L)&&(null==g||null==(e=g.settings)?void 0:e.keyId)!==void 0&&j({...g,settings:{...g.settings,keyId:void 0}}))},[u,$,L]),(0,q.jsxs)("div",{className:w.twoColumnLayout,children:[(0,q.jsxs)("div",{className:w.leftColumn,children:[(0,q.jsx)("div",{className:w.leftHeader,children:(0,q.jsx)(W.Text,{className:w.leftHeaderTitle,strong:!0,children:I("data-importer.mapping.advanced-modal.step-target")})}),(0,q.jsxs)("div",{className:w.fieldsContainer,children:[(0,q.jsxs)("div",{children:[(0,q.jsx)("div",{className:w.fieldLabel,children:I("data-importer.mapping.advanced-modal.step-target.type")}),(0,q.jsx)("div",{className:w.selectSkeletonWrapper,children:(0,q.jsx)(W.Select,{className:w.selectFull,onChange:e=>{var t,a,r,i,o,n,l,s;let d=null==g?void 0:g.type,p=("classificationstore"===e||"classificationstoreBatch"===e)&&("direct"===d||"manyToManyRelation"===d),c={...null==g?void 0:g.settings,overwriteMode:"manyToManyRelation"===e?null==g||null==(t=g.settings)?void 0:t.overwriteMode:void 0,keyId:"classificationstore"===e||"classificationstoreBatch"===e?null==g||null==(a=g.settings)?void 0:a.keyId:void 0,fieldName:p||null==g||null==(r=g.settings)?void 0:r.fieldName,language:p||null==g||null==(i=g.settings)?void 0:i.language,writeIfTargetIsNotEmpty:"direct"===e||"manyToManyRelation"===e?(null==g||null==(o=g.settings)?void 0:o.writeIfTargetIsNotEmpty)??!0:null==g||null==(n=g.settings)?void 0:n.writeIfTargetIsNotEmpty,writeIfSourceIsEmpty:"direct"===e||"manyToManyRelation"===e?(null==g||null==(l=g.settings)?void 0:l.writeIfSourceIsEmpty)??!0:null==g||null==(s=g.settings)?void 0:s.writeIfSourceIsEmpty};j({...g,type:e,settings:c})},options:[{value:"direct",label:I("data-importer.mapping.item.data-target.type.direct")},{value:"classificationstore",label:I("data-importer.mapping.item.data-target.type.classificationstore")},{value:"classificationstoreBatch",label:I("data-importer.mapping.item.data-target.type.classificationstoreBatch")},{value:"manyToManyRelation",label:I("data-importer.mapping.item.data-target.type.manyToManyRelation")}],value:null==g?void 0:g.type})})]}),O&&(0,q.jsx)("div",{className:w.typeError,children:er}),!O&&(0,q.jsxs)(q.Fragment,{children:[(0,q.jsxs)("div",{children:[(0,q.jsx)("div",{className:w.fieldLabel,children:I("data-importer.mapping.advanced-modal.step-target.field-name")}),(0,q.jsx)("div",{className:w.selectSkeletonWrapper,children:(0,q.jsx)(W.Select,{className:w.selectFull,loadingSkeleton:V,onChange:e=>{var t;j({...g,settings:{...null==g?void 0:g.settings,fieldName:e,language:void 0,keyId:$||L||null==g||null==(t=g.settings)?void 0:t.keyId}})},options:_,showSearch:!0,value:null==g||null==(o=g.settings)?void 0:o.fieldName})})]}),$&&(0,q.jsxs)("div",{children:[(0,q.jsx)("div",{className:w.fieldLabel,children:I("data-importer.mapping.advanced-modal.step-target.classification-store-key")}),(0,q.jsxs)("div",{className:w.classificationStoreKeyRow,children:[(0,q.jsx)(W.Input,{className:w.classificationStoreKeyInput,readOnly:!0,value:ea}),(0,q.jsx)(W.Button,{disabled:!ee,onClick:()=>{F(!0)},type:"default",children:I("common.search")})]})]}),K&&(0,q.jsxs)("div",{children:[(0,q.jsx)("div",{className:w.fieldLabel,children:I("data-importer.mapping.item.data-target.language-placeholder")}),(0,q.jsx)("div",{className:w.selectSkeletonWrapper,children:(0,q.jsx)(W.Select,{className:w.selectFull,onChange:e=>{j({...g,settings:{...null==g?void 0:g.settings,language:e}})},options:h,showSearch:!0,value:null==g||null==(n=g.settings)?void 0:n.language})})]})]}),Z&&!O&&(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)("div",{className:w.overwriteLabel,children:I("data-importer.mapping.advanced-modal.step-target.overwrite")}),(0,q.jsx)(W.Switch,{checked:(null==g||null==(l=g.settings)?void 0:l.writeIfTargetIsNotEmpty)??!0,labelRight:(0,q.jsx)("span",{className:w.switchLabel,children:I("data-importer.mapping.advanced-modal.write-if-target-not-empty")}),onChange:e=>{j({...g,settings:{...null==g?void 0:g.settings,writeIfTargetIsNotEmpty:e,writeIfSourceIsEmpty:!!e}})},size:"small",tooltip:I("data-importer.mapping.advanced-modal.step-target.write-if-target-not-empty.tooltip")}),Y&&(0,q.jsxs)("div",{children:[(0,q.jsx)("div",{className:w.fieldLabel,children:I("data-importer.mapping.advanced-modal.step-target.overwrite-mode")}),(0,q.jsx)("div",{className:w.selectSkeletonWrapper,children:(0,q.jsx)(W.Select,{className:w.selectFull,onChange:e=>{j({...g,settings:{...null==g?void 0:g.settings,overwriteMode:e}})},options:[{value:"replace",label:I("data-importer.mapping.advanced-modal.step-target.overwrite-mode.replace")},{value:"merge",label:I("data-importer.mapping.advanced-modal.step-target.overwrite-mode.merge")}],value:null==g||null==(s=g.settings)?void 0:s.overwriteMode})})]}),(0,q.jsx)(W.Switch,{checked:(null==g||null==(d=g.settings)?void 0:d.writeIfSourceIsEmpty)??!0,disabled:!((null==g||null==(p=g.settings)?void 0:p.writeIfTargetIsNotEmpty)??!0),labelRight:(0,q.jsx)("span",{className:w.switchLabel,children:I("data-importer.mapping.advanced-modal.write-if-source-empty")}),onChange:e=>{j({...g,settings:{...null==g?void 0:g.settings,writeIfSourceIsEmpty:e}})},size:"small",tooltip:I("data-importer.mapping.advanced-modal.step-target.write-if-source-empty.tooltip")})]})]})]}),(0,q.jsxs)("div",{className:w.rightColumn,children:[(0,q.jsx)("div",{className:w.previewWrapper,children:(0,q.jsx)(tQ,{baseConfig:b,configName:f,currentMappingItem:y,mode:"result",refreshToken:v})}),(0,q.jsxs)("div",{className:T.navButtons,children:[(0,q.jsx)("button",{className:T.outlineButton,onClick:S,type:"button",children:I("data-importer.mapping.advanced-modal.step-target.previous-step")}),(0,q.jsx)(W.Button,{onClick:C,type:"primary",children:I("data-importer.mapping.advanced-modal.step-target.confirm-mapping")})]})]}),$&&void 0!==x&&(null==g||null==(c=g.settings)?void 0:c.fieldName)!==void 0&&void 0!==u&&(0,q.jsx)(ai,{classId:x,fieldName:g.settings.fieldName,onClose:()=>{F(!1)},onSelect:e=>{j({...g,settings:{...null==g?void 0:g.settings,keyId:e}}),F(!1)},open:N,transformationResultType:u})]})},an=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{modalBody:t`
      display: flex;
      flex-direction: column;
      gap: ${a.paddingSM}px;
    `,sectionWrapper:t`
      border: 1px solid ${a.colorBorderSecondary};
      border-radius: ${a.borderRadiusLG}px;
      overflow: hidden;
      background: ${a.colorBgContainer};
    `,sectionWrapperCollapsed:t`
      background: ${a.colorFillAlter};
    `,sectionBody:t`
      padding: ${a.paddingSM}px;
      border-top: 1px solid ${a.colorBorderSecondary};
    `,sectionBodyHidden:t`
      display: none;
    `,footer:t`
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: ${a.paddingXS}px;
      border-top: 1px solid ${a.colorBorderSecondary};
      padding-top: ${a.paddingSM}px;
      margin-top: ${a.paddingXXS}px;
    `}}),al=e=>{let{open:t,onClose:a,onSave:r,configName:i,classId:o,item:n,columnHeaderOptions:l,attributesMap:s,baseConfig:d}=e,{t:p}=(0,A.useTranslation)(),{styles:c,cx:m}=an(),u=(0,eg.useSettings)(),g=(0,z.useMemo)(()=>(u.validLanguages??[]).map(e=>({value:e,label:e})),[u.validLanguages]),[h,x]=(0,z.useState)(()=>structuredClone(n)),[f,v]=(0,z.useState)({source:!0,transformations:!1,target:!1}),[y,b]=(0,z.useState)(0),j=(0,z.useRef)(null),S=(0,z.useCallback)(()=>{null!==j.current&&clearTimeout(j.current),j.current=setTimeout(()=>{b(e=>e+1),j.current=null},800)},[]),[C,I]=(0,z.useState)(void 0),{data:w,isFetching:T,refetch:N}=ej(C,{skip:void 0===C,refetchOnMountOrArgChange:!1});(0,z.useEffect)(()=>{void 0!==w&&x(e=>({...e,transformationResultType:w.type}))},[w]),(0,z.useEffect)(()=>{t&&(x(structuredClone(n)),v({source:!0,transformations:!1,target:!1}))},[t,n]);let F=e=>{v(t=>({source:!1,transformations:!1,target:!1,[e]:!t[e]}))},k=h.transformationPipeline??[],D=e=>{x(t=>({...t,dataSourceIndex:e})),S()},$=async()=>{let e={name:i,bundleDataImporterCalculateTransformationResultTypeParameters:{currentConfig:{label:h.label,dataSourceIndex:h.dataSourceIndex,transformationPipeline:h.transformationPipeline,dataTarget:h.dataTarget}}},t=JSON.stringify(C)===JSON.stringify(e);if(I(e),t)try{await N()}catch{}},L=()=>{r(h),a()};return(0,q.jsx)(W.Modal,{footer:(0,q.jsxs)("div",{className:c.footer,children:[(0,q.jsx)(W.IconButton,{disabled:T,icon:{value:"refresh"},onClick:()=>{$()},title:p("data-importer.mapping.advanced-modal.recalculate-result-type")}),(0,q.jsx)(W.Button,{onClick:L,type:"primary",children:p("data-importer.mapping.advanced-modal.save")})]}),onCancel:a,open:t,title:p("data-importer.mapping.advanced-modal.title"),width:996,children:(0,q.jsxs)("div",{className:c.modalBody,children:[(0,q.jsxs)("div",{className:m(c.sectionWrapper,!f.source&&c.sectionWrapperCollapsed),children:[(0,q.jsx)(tG,{expanded:f.source,hasBorderBottom:f.source,onToggle:()=>{F("source")},step:1,title:p("data-importer.mapping.advanced-modal.step-source")}),f.source&&(0,q.jsx)("div",{className:c.sectionBody,children:(0,q.jsx)(tJ,{columnHeaderOptions:l,configName:i,dataSourceIndex:h.dataSourceIndex??[],onDataSourceIndexChange:D,onNext:()=>{F("transformations")}})})]}),(0,q.jsxs)("div",{className:m(c.sectionWrapper,!f.transformations&&c.sectionWrapperCollapsed),children:[(0,q.jsx)(tG,{expanded:f.transformations,hasBorderBottom:f.transformations,onToggle:()=>{F("transformations")},step:2,title:p("data-importer.mapping.advanced-modal.step-transformations")}),(0,q.jsx)("div",{className:m(c.sectionBody,!f.transformations&&c.sectionBodyHidden),children:(0,q.jsx)(ae,{baseConfig:d,columnHeaderOptions:l,configName:i,currentMappingItem:h,dataSourceIndex:h.dataSourceIndex??[],onDataSourceIndexChange:D,onNext:()=>{F("target")},onPipelineChange:e=>{x(t=>({...t,transformationPipeline:e})),S()},onPrev:()=>{F("source")},pipeline:k,previewRefreshToken:y})})]}),(0,q.jsxs)("div",{className:m(c.sectionWrapper,!f.target&&c.sectionWrapperCollapsed),children:[(0,q.jsx)(tG,{expanded:f.target,hasBorderBottom:f.target,onToggle:()=>{F("target")},step:3,title:p("data-importer.mapping.advanced-modal.step-target")}),(0,q.jsx)("div",{className:m(c.sectionBody,!f.target&&c.sectionBodyHidden),children:(0,q.jsx)(ao,{attributesMap:s,baseConfig:d,classId:o,configName:i,currentMappingItem:h,dataTarget:h.dataTarget,languageOptions:g,onConfirm:L,onDataTargetChange:e=>{x(t=>({...t,dataTarget:e}))},onPrev:()=>{F("transformations")},previewRefreshToken:y,transformationResultType:h.transformationResultType})})]})]})})},as=e=>{let{children:t,className:a}=e,{getStateClasses:r}=(0,W.useDroppable)(),i=[a,...r()].filter(Boolean).join(" ");return(0,q.jsx)("div",{className:i,children:t})},ad=e=>{let{isAdvanced:t,isWarningState:a,isInProgressState:r}=e,{styles:i}=tH(),{token:o}=tV.A.useToken(),n=t||a||r,l=a||r?o.colorWarning:o.colorIcon,s=(0,q.jsx)("span",{className:i.arrowSvg,children:(0,q.jsx)("svg",{fill:"none",height:"12",viewBox:"0 0 26 12",width:"26",xmlns:"http://www.w3.org/2000/svg",children:(0,q.jsx)("path",{d:"M25.5303 6.05377C25.8232 5.76087 25.8232 5.286 25.5303 4.99311L20.7574 0.220137C20.4645 -0.0727568 19.9896 -0.0727568 19.6967 0.220137C19.4038 0.51303 19.4038 0.987904 19.6967 1.2808L23.9393 5.52344L19.6967 9.76608C19.4038 10.059 19.4038 10.5338 19.6967 10.8267C19.9896 11.1196 20.4645 11.1196 20.7574 10.8267L25.5303 6.05377ZM0 5.52344V6.27344H25V5.52344V4.77344H0V5.52344Z",fill:l,fillOpacity:1})})});return n?(0,q.jsxs)("div",{className:[i.arrowCol,i.arrowColAdvanced].join(" "),children:[t&&!a&&!r&&(0,q.jsx)("span",{className:i.arrowGearIcon,children:(0,q.jsx)(W.Icon,{value:"settings"})}),(a||r)&&(0,q.jsx)("span",{className:i.arrowWarningBadge,children:(0,q.jsx)(W.Icon,{value:"warning-circle"})}),s]}):(0,q.jsxs)("div",{className:[i.arrowCol,i.arrowColSimple].join(" "),children:[(0,q.jsx)("div",{className:i.arrowLabelSpacer}),(0,q.jsx)("div",{className:i.arrowSelectRow,children:s})]})};function ap(e,t){return void 0!==e&&(null===t||e===t)}let ac=e=>{let{insertIndex:t,add:a,onInsertItem:r,onDropped:i,acceptedDataIndex:o}=e,{styles:n}=tH(),l=(0,z.useCallback)(e=>{let{dataIndex:o,label:n}=e.data;r(a,t,o,n),i(t)},[a,t,r,i]);return(0,q.jsx)(W.Droppable,{className:n.mappingDropZoneWrapper,disableDndActiveIndicator:!1,isValidContext:e=>e.type===tW&&ap(e.data.dataIndex,o??null),onDrop:l,variant:"default",children:(0,q.jsx)(as,{className:n.mappingDropZone})})},am=H().memo(e=>{let t,{fieldIndex:a,mappingId:r,remove:i,onRemoveItem:o,configName:n,columnHeaderOptions:l,classId:s,expanded:d,onToggle:p,itemLabel:c,dataSourceIndex:m,transformationResultType:u,selectedFieldName:g,attributesMap:h}=e,{t:x}=(0,A.useTranslation)(),{styles:f}=tH(),v=W.Form.useFormInstance(),[y,b]=(0,z.useState)(!1),j=(0,eg.useSettings)(),S=(0,z.useMemo)(()=>(j.validLanguages??[]).map(e=>({value:e,label:e})),[j.validLanguages]),C=(m??[]).length,I=void 0!==g&&""!==g,w=void 0!==u&&""!==u&&"default"!==u,T=C>1&&!I,N=(0,z.useCallback)(()=>(v.getFieldValue("mappingConfig")??[]).findIndex(e=>e.mappingId===r),[v,r]);(0,z.useEffect)(()=>{let e=N();if(e<0)return;let t=v.getFieldValue(["mappingConfig",e])??{},a=t.label??"",r=t.dataSourceIndex??[];if(""===a&&r.length>0){let t=l.find(e=>e.value===r[0]);void 0!==t&&v.setFieldValue(["mappingConfig",e,"label"],t.label,{triggerChange:!0})}},[l,v,N]);let F=h[tn(u)]??[],k=(0,z.useMemo)(()=>F.map(e=>({value:e.key,label:e.title})),[F]),D=F.find(e=>e.key===g),$=(null==D?void 0:D.localized)??!1,L=void 0!==c&&""!==c?c:x("data-importer.mapping.item.new-label"),P=(0,z.useCallback)(e=>{let t=N();if(t<0)return;let a=e.data.dataIndex,r=v.getFieldValue(["mappingConfig",t,"dataSourceIndex"])??[];r.includes(a)||v.setFieldValue(["mappingConfig",t,"dataSourceIndex"],[...r,a],{triggerChange:!0})},[v,N]);return(0,q.jsxs)(W.Droppable,{className:f.droppablePanel,disableDndActiveIndicator:!1,isValidContext:e=>e.type===tW,onDrop:P,variant:"default",children:[(0,q.jsx)(as,{className:f.panelDndWrapper,children:(0,q.jsx)(W.Panel,{active:d,border:!0,collapsible:!0,contentPadding:"none",onChange:()=>{p()},theme:"default",title:L,children:(0,q.jsxs)("div",{className:f.mappingItemContent,children:[(0,q.jsx)(W.Form.Item,{hidden:!0,name:[a,"transformationResultType"],children:(0,q.jsx)(W.Input,{})}),(0,q.jsxs)("div",{className:f.mappingLabelRow,children:[(0,q.jsx)("div",{className:f.mappingLabelInput,style:{flex:1},children:(0,q.jsx)(W.Form.Item,{name:[a,"label"],style:{marginBottom:0},children:(0,q.jsx)(W.Input,{placeholder:x("data-importer.mapping.item.label")})})}),(0,q.jsx)(W.IconTextButton,{icon:{value:"settings"},onClick:()=>{b(!0)},type:"default",children:x("data-importer.mapping.item.advanced")}),(0,q.jsx)(W.IconButton,{icon:{value:"trash"},onClick:()=>{o(a)},tooltip:{title:x("data-importer.mapping.item.delete")},type:"default"})]}),(0,q.jsx)("div",{className:f.mappingDivider}),(0,q.jsxs)("div",{className:f.sourcesDestRow,children:[(0,q.jsxs)("div",{className:f.sourcesDestCol,children:[(0,q.jsx)("div",{children:x("data-importer.mapping.item.source")}),(0,q.jsx)("div",{className:f.sourceDropZone,children:(0,q.jsx)(W.Form.Item,{name:[a,"dataSourceIndex"],style:{marginBottom:0},children:(0,q.jsx)(W.Select,{filterOption:tb,mode:"multiple",options:l,placeholder:x("data-importer.mapping.item.source-placeholder"),showSearch:!0})})})]}),(0,q.jsx)(ad,{isAdvanced:w,isInProgressState:T,isWarningState:C>0&&!I}),(0,q.jsxs)("div",{className:f.sourcesDestCol,children:[(0,q.jsx)("div",{children:x("data-importer.mapping.item.destination")}),w?(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)("div",{className:f.destinationTextBlock,children:(0,q.jsx)("span",{children:(null==D?void 0:D.title)??g??""})}),(0,q.jsx)(W.Form.Item,{hidden:!0,name:[a,"dataTarget","settings","fieldName"],style:{display:"none"},children:(0,q.jsx)(W.Input,{})})]}):T?(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)("div",{className:f.requiresAdvancedHint,children:x("data-importer.mapping.item.requires-advanced-setup")}),(0,q.jsx)(W.Form.Item,{hidden:!0,name:[a,"dataTarget","settings","fieldName"],style:{display:"none"},children:(0,q.jsx)(W.Input,{})})]}):(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Form.Item,{name:[a,"dataTarget","settings","fieldName"],style:{marginBottom:0},children:(0,q.jsx)(W.Select,{filterOption:tb,options:k,placeholder:x("data-importer.mapping.item.destination-placeholder"),showSearch:!0})}),$&&(0,q.jsx)(W.Form.Item,{name:[a,"dataTarget","settings","language"],style:{marginBottom:0},children:(0,q.jsx)(W.Select,{filterOption:tb,options:S,placeholder:x("data-importer.mapping.item.data-target.language-placeholder"),showSearch:!0})})]})]})]})]})})}),y&&(0,q.jsx)(al,{attributesMap:h,baseConfig:{loaderConfig:v.getFieldsValue(!0).loaderConfig,interpreterConfig:v.getFieldsValue(!0).interpreterConfig,resolverConfig:v.getFieldsValue(!0).resolverConfig,processingConfig:v.getFieldsValue(!0).processingConfig},classId:s,columnHeaderOptions:l,configName:n,item:(t=N())<0?{}:v.getFieldValue(["mappingConfig",t])??{},onClose:()=>{b(!1)},onSave:e=>{let t=N();t<0||v.setFieldValue(["mappingConfig",t],e,{triggerChange:!0}),b(!1)},open:y})]})});am.displayName="MappingItem";let au=H().memo(e=>{var t,a;let{fieldIndex:r,mappingId:i,insertIndex:o,activeFilter:n,isNew:l,add:s,onDropped:d,onInsertItem:p,acceptedDataIndex:c,attributesMap:m,expanded:u,onToggle:g,...h}=e,{styles:x,cx:f}=tH(),v=W.Form.useFormInstance(),y=(W.Form.useWatch("mappingConfig")??[]).find(e=>e.mappingId===i),b=v.getFieldValue(["mappingConfig",r]),j=y??b??{},S=j.dataSourceIndex,C=null!==n&&!(S??[]).includes(n);return(0,q.jsxs)("div",{className:f(C&&x.hiddenItem),children:[(0,q.jsx)(ac,{acceptedDataIndex:c,add:s,insertIndex:o,onDropped:d,onInsertItem:p}),(0,q.jsx)("div",{className:f(l&&x.mappingItemNew),children:(0,q.jsx)(am,{...h,attributesMap:m,dataSourceIndex:S,expanded:u,fieldIndex:r,itemLabel:j.label,mappingId:i,onToggle:g,selectedFieldName:null==(a=j.dataTarget)||null==(t=a.settings)?void 0:t.fieldName,transformationResultType:j.transformationResultType})})]})});au.displayName="MappingItemWithFilter";let ag=e=>{let{activeFilter:t,activeFilterLabel:a,fields:r,onAddMappingForFilter:i,add:o,onInsertItem:n,onDropped:l}=e,{t:s}=(0,A.useTranslation)(),{styles:d}=tH(),p=W.Form.useWatch("mappingConfig")??[],c=(0,z.useCallback)(e=>{let{dataIndex:a,label:i}=e.data;ap(a,t)&&(n(o,r.length,a,i),l(r.length))},[t,n,o,r.length,l]);return p.some(e=>(e.dataSourceIndex??[]).includes(t))?null:(0,q.jsx)(W.Droppable,{className:d.filterEmptyState,disableDndActiveIndicator:!1,isValidContext:e=>e.type===tW&&ap(e.data.dataIndex,t),onDrop:c,variant:"default",children:(0,q.jsxs)(as,{className:d.filterEmptyStateInner,children:[(0,q.jsx)("span",{children:s("data-importer.mapping.filter-empty",{source:a??t})}),(0,q.jsx)(W.IconTextButton,{icon:{value:"add"},onClick:i,type:"default",children:s("data-importer.mapping.add")})]})})},ah=e=>{let{add:t,onInsertItem:a,onDropped:r}=e,{t:i}=(0,A.useTranslation)(),{styles:o}=tH(),n=(0,z.useCallback)(e=>{let{dataIndex:i,label:o}=e.data;a(t,0,i,o),r(0)},[t,a,r]);return(0,q.jsx)(W.Droppable,{className:o.emptyState,disableDndActiveIndicator:!1,isValidContext:e=>e.type===tW,onDrop:n,variant:"default",children:(0,q.jsx)(as,{className:o.emptyStateInner,children:(0,q.jsx)("span",{children:i("data-importer.mapping.empty-state")})})})},ax=H().memo(e=>{let{fields:t,add:a,remove:r,hasItems:i,flashIndex:o,classId:n,configName:l,columnHeaderOptions:s,activeFilter:d,activeFilterLabel:p,expandedKeys:c,allVisibleCollapsed:m,attributesMap:u,onCollapseAll:g,onNewKey:h,onToggleKey:x,onAutoFill:f,onAddItem:v,onRemoveItem:y,onAddMappingForFilter:b,onInsertItem:j,onDropped:S,getMappingIdByIndex:C}=e,{t:I}=(0,A.useTranslation)(),{styles:w}=tH(),T=(0,z.useRef)(new Set(t.map(e=>e.key)));(0,z.useLayoutEffect)(()=>{let e=new Set(t.map(e=>e.key));e.forEach(e=>{T.current.has(e)||h(e)}),T.current=e},[t,h]);let N=(0,z.useMemo)(()=>{let e=T.current;return new Set(t.map(e=>e.key).filter(t=>!e.has(t)))},[t]),F=(0,z.useMemo)(()=>t.filter(e=>!0).map(e=>e.key),[t,d]),k=(0,z.useCallback)(e=>()=>{y(r,e)},[y,r]),D=(0,z.useMemo)(()=>{let e=t.map(e=>e.key),i=d??void 0;return t.map((t,p)=>{let m=C(t.name),g="all"===c||c.has(t.key)||N.has(t.key);return(0,q.jsx)(H().Fragment,{children:(0,q.jsx)(au,{acceptedDataIndex:i,activeFilter:d,add:a,attributesMap:u,classId:n,columnHeaderOptions:s,configName:l,expanded:g,fieldIndex:t.name,insertIndex:p,isNew:o===p,mappingId:m,onDropped:S,onInsertItem:j,onRemoveItem:k(t.name),onToggle:()=>{x(t.key,e)},remove:r})},t.key)})},[t,a,r,d,u,n,c,N,s,l,o,S,j,k,x,C]);return(0,q.jsxs)(q.Fragment,{children:[(0,q.jsxs)("div",{className:w.mappingsHeader,children:[(0,q.jsx)(W.Text,{className:w.mappingsTitle,children:I("data-importer.mapping.title-short")}),(0,q.jsxs)("div",{className:w.mappingsActions,children:[(0,q.jsx)(W.IconTextButton,{icon:{value:"new"},onClick:()=>{v(a,t.length)},type:"default",children:I("data-importer.mapping.add")}),(0,q.jsx)(W.Divider,{className:w.mappingsDivider,type:"vertical"}),(0,q.jsx)(W.IconTextButton,{icon:{value:"autofill"},onClick:()=>{f(t,a)},type:"default",children:I("data-importer.mapping.auto-fill")}),i&&(0,q.jsx)("span",{className:w.collapseAllLink,onClick:()=>{g(F)},children:I(m?"data-importer.mapping.expand-all":"data-importer.mapping.collapse-all")})]})]}),(0,q.jsxs)("div",{className:w.mappingsContent,children:[!i&&null===d&&(0,q.jsx)(ah,{add:a,onDropped:S,onInsertItem:j}),D,i&&(0,q.jsx)(ac,{acceptedDataIndex:d??void 0,add:a,insertIndex:t.length,onDropped:S,onInsertItem:j}),null!==d&&(0,q.jsx)(ag,{activeFilter:d,activeFilterLabel:p,add:a,fields:t,onAddMappingForFilter:()=>{b(a)},onDropped:S,onInsertItem:j})]})]})});ax.displayName="MappingsPanelContent";let af=e=>{let{classId:t,configName:a,columnHeaderOptions:r,activeFilter:i,activeFilterLabel:o,expandedKeys:n,attributesMap:l,onCollapseAll:s,onNewKey:d,onToggleKey:p,onAutoFill:c,onAddItem:m,onRemoveItem:u,onAddMappingForFilter:g,onInsertItem:h,getMappingIdByIndex:x}=e,{styles:f}=tH(),[v,y]=(0,z.useState)(null),b=(0,z.useRef)(null),j=(0,z.useCallback)(e=>{null!==b.current&&clearTimeout(b.current),y(e),b.current=setTimeout(()=>{y(null)},400)},[]);return(0,q.jsx)("div",{className:f.panel,children:(0,q.jsx)(W.Form.List,{name:"mappingConfig",children:(e,f)=>{let{add:y,remove:b}=f,S=e.length>0,C="all"!==n&&e.every(e=>!n.has(e.key));return(0,q.jsx)(ax,{activeFilter:i,activeFilterLabel:o,add:y,allVisibleCollapsed:C,attributesMap:l,classId:t,columnHeaderOptions:r,configName:a,expandedKeys:n,fields:e,flashIndex:v,getMappingIdByIndex:x,hasItems:S,onAddItem:m,onAddMappingForFilter:g,onAutoFill:c,onCollapseAll:s,onDropped:j,onInsertItem:h,onNewKey:d,onRemoveItem:u,onToggleKey:p,remove:b})}})})},av=e=>{let{options:t,valueRef:a,errorRef:r}=e,{t:i}=(0,A.useTranslation)(),[o,n]=(0,z.useState)(void 0),[l,s]=(0,z.useState)(!1);return r.current=s,(0,q.jsxs)("div",{style:{marginTop:12},children:[(0,q.jsxs)("div",{style:{marginBottom:4},children:[i("data-importer.mapping.new-modal.source-label"),(0,q.jsx)("span",{style:{color:"#ff4d4f",marginLeft:4},children:"*"})]}),(0,q.jsx)(W.Select,{allowClear:!0,filterOption:tb,onChange:e=>{n(e),a.current=e,void 0!==e&&s(!1)},options:t,placeholder:i("data-importer.mapping.item.source-placeholder"),showSearch:!0,status:l?"error":void 0,style:{width:"100%"},value:o}),l&&(0,q.jsx)("div",{style:{color:"#ff4d4f",fontSize:12,marginTop:4},children:i("data-importer.mapping.new-modal.source-required")})]})};function ay(e){return{key:e.key??e.name??"",title:e.title??e.name??e.key??"",localized:!!e.localized}}function ab(e,t){let a=arguments.length>2&&void 0!==arguments[2]?arguments[2]:"manual";return{mappingId:tl(),label:t,dataSourceIndex:[e],transformationResultType:"autofill"===a?"default":void 0,dataTarget:{type:"direct",settings:{..."autofill"===a&&{fieldName:e},writeIfTargetIsNotEmpty:!0,writeIfSourceIsEmpty:!0}}}}let aj=e=>{let{configName:t,isActive:a}=e,{styles:r}=tH(),{t:i}=(0,A.useTranslation)(),o=(0,W.useFormModal)(),n=W.Form.useFormInstance(),{columnHeaderOptions:l,initialLoadDone:s,sourceRows:d,hasPreviewError:p,attributesMap:c,classId:m,getMappingConfig:u}=function(e,t){var a,r;let i=W.Form.useFormInstance(),o=(0,A.useAppDispatch)(),{data:n,isSuccess:l,requestId:s}=tt({name:e}),[d,p]=(0,z.useState)([]),[c,m]=(0,z.useState)(!1),[u,g]=(0,z.useState)([]),[h,x]=(0,z.useState)(!1),[f,v]=(0,z.useState)({}),[y,b]=(0,z.useState)(void 0),[j,S]=(0,z.useState)(void 0),[C,I]=(0,z.useState)(!1),{data:w,refetch:T,isFetching:N,isError:F,isSuccess:k}=eF(y,{skip:void 0===y,refetchOnMountOrArgChange:!1}),{data:D,refetch:$,isFetching:L,isError:P,isSuccess:B}=ek(j,{skip:void 0===j,refetchOnMountOrArgChange:!1}),M=null==n||null==(r=n.configuration)||null==(a=r.resolverConfig)?void 0:a.dataObjectClassId,R=W.Form.useWatch(["resolverConfig","dataObjectClassId"])??M,E=W.Form.useWatch(e=>(e.mappingConfig??[]).map(e=>e.transformationResultType??"")),O=(0,z.useCallback)(()=>i.getFieldsValue().mappingConfig??[],[i]),q=(0,z.useCallback)(()=>tp(i.getFieldsValue(!0),(null==n?void 0:n.configuration)??{}),[i,n]),H=(0,z.useCallback)(()=>{let e=q();return{loaderConfig:e.loaderConfig,interpreterConfig:e.interpreterConfig}},[q]);(0,z.useEffect)(()=>{k&&void 0!==w&&p((w.columnHeaders??[]).map(e=>({value:String(e.dataIndex??e.id??""),label:String(e.label??e.dataIndex??e.id??"")})))},[k,w]),(0,z.useEffect)(()=>{F&&p([])},[F]),(0,z.useEffect)(()=>{if(!B||void 0===D)return;let e=(D.dataPreview??[]).map(e=>t_(e));g(e),x(0===e.length)},[B,D]),(0,z.useEffect)(()=>{P&&(g([]),x(!0))},[P]),(0,z.useEffect)(()=>{let e=void 0!==j&&!L;m(void 0!==y&&!N&&C&&e)},[y,j,N,L,C]);let X=W.Form.useWatch(["loaderConfig","type"]),V=W.Form.useWatch(["interpreterConfig","type"]),U=JSON.stringify(H()),[G,_]=(0,z.useState)(""),[K,Q]=(0,z.useState)(void 0);return(0,z.useEffect)(()=>{if(!l||!t)return;let a=U!==G,r=void 0!==s&&s!==K;(a||r)&&(m(!1),I(!1),x(!1),b({name:e,bundleDataImporterCopyPreviewParameters:{currentConfig:H()}}),S({name:e,bundleDataImporterLoadPreviewParameters:{currentConfig:H(),recordNumber:0}}),r&&!a&&void 0!==y&&void 0!==j&&Promise.all([T().catch(()=>void 0),$().catch(()=>void 0)]),(async()=>{try{let e=i.getFieldValue(["resolverConfig","dataObjectClassId"])??M,t=((null==n?void 0:n.configuration)??{}).mappingConfig??[],a=new Set;void 0!==e&&""!==e&&(a.add(void 0),t.forEach(e=>{a.add(e.transformationResultType)}));let l=Array.from(a),s=l.map(async t=>await o(te.endpoints.bundleDataImporterDataTypeLoadClassAttributes.initiate({classId:e,transformationResultType:t,systemWrite:!0},{forceRefetch:r}))),d=await Promise.all(s);if(l.length>0){let e={};d.forEach((t,a)=>{var r;let i=l[a],o=((null==(r=t.data)?void 0:r.attributes)??[]).map(ay);e[void 0===i||""===i||"default"===i?"__default__":i]=o}),v(e)}}finally{I(!0)}})(),_(U),Q(s))},[l,t,e,U,G,s,K,H,y,j,T,$,o,i,n,M,X,V]),(0,z.useEffect)(()=>{if(void 0===R||""===R||!c)return;let e=O(),t=new Set;if(e.forEach(e=>{let a=tn(e.transformationResultType);void 0===f[a]&&t.add(a)}),0===t.size)return;let a=Array.from(t);Promise.all(a.map(async e=>await o(te.endpoints.bundleDataImporterDataTypeLoadClassAttributes.initiate({classId:R,transformationResultType:"__default__"===e?void 0:e,systemWrite:!0})))).then(e=>{v(t=>{let r={...t};return e.forEach((e,t)=>{var i;r[a[t]]=((null==(i=e.data)?void 0:i.attributes)??[]).map(ay)}),r})})},[E,c,R]),{columnHeaderOptions:d,initialLoadDone:c,sourceRows:u,hasPreviewError:h,attributesMap:f,setAttributesMap:v,classId:R,mappingTrtList:E,getMappingConfig:O}}(t,a),[g,h]=(0,z.useState)(new Set),[x,f]=(0,z.useState)(null),v=(0,z.useMemo)(()=>{var e;return null===x?null:(null==(e=d.find(e=>e.dataIndex===x))?void 0:e.label)??x},[x,d]);return(0,q.jsx)(W.Content,{loading:!s,children:(0,q.jsxs)("div",{className:r.mappingLayout,children:[(0,q.jsx)("div",{className:r.mappingLayoutLeft,children:(0,q.jsx)(tX,{activeFilter:x,configName:t,hasPreviewError:p,onAddMappingFromSource:(e,t)=>{let a=u(),r=ab(e,t,"manual");n.setFieldValue("mappingConfig",[...a,r],{triggerChange:!0})},onSetFilter:f,sourceRows:d})}),(0,q.jsx)("div",{className:r.mappingLayoutCenter,children:(0,q.jsx)("div",{className:r.mappingLayoutCenterArrow,children:(0,q.jsxs)("svg",{fill:"none",height:"38",viewBox:"0 0 38 38",width:"38",xmlns:"http://www.w3.org/2000/svg",children:[(0,q.jsxs)("g",{clipPath:"url(#panel-arrow-clip)",children:[(0,q.jsx)("path",{d:"M26.9167 12.6641L33.25 18.9974L26.9167 25.3307",stroke:"currentColor",strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:"2"}),(0,q.jsx)("path",{d:"M33.25 19L24.7095 19C22.9533 19.0001 21.2242 18.5666 19.6758 17.738C18.1274 16.9094 16.8075 15.7113 15.8333 14.25C14.8592 12.7887 13.5393 11.5906 11.9909 10.762C10.4424 9.93337 8.71337 9.49988 6.95717 9.5L4.75 9.5",stroke:"currentColor",strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:"2"}),(0,q.jsx)("path",{d:"M33.25 19L24.7095 19C22.9533 18.9999 21.2242 19.4334 19.6758 20.262C18.1274 21.0906 16.8075 22.2887 15.8333 23.75C14.8592 25.2113 13.5393 26.4094 11.9909 27.238C10.4424 28.0666 8.71337 28.5001 6.95717 28.5L4.75 28.5",stroke:"currentColor",strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:"2"})]}),(0,q.jsx)("defs",{children:(0,q.jsx)("clipPath",{id:"panel-arrow-clip",children:(0,q.jsx)("rect",{fill:"white",height:"38",transform:"translate(38 1.66103e-06) rotate(90)",width:"38"})})})]})})}),(0,q.jsx)("div",{className:r.mappingLayoutRight,children:(0,q.jsx)(X.FieldWidthProvider,{fieldWidthValues:{small:9999,medium:9999,large:9999},children:(0,q.jsx)(af,{activeFilter:x,activeFilterLabel:v,attributesMap:c,classId:m,columnHeaderOptions:l,configName:t,expandedKeys:g,getMappingIdByIndex:e=>(function(e,t){let a=e.getFieldValue(["mappingConfig",t]),r=null==a?void 0:a.mappingId;if(void 0!==r&&""!==r)return r;let i=tl();return e.setFieldValue(["mappingConfig",t,"mappingId"],i,{triggerChange:!1}),i})(n,e),onAddItem:(e,t)=>{let a={current:void 0},r={current:void 0};o.confirm({title:i("data-importer.mapping.new-modal.title"),content:(0,q.jsx)(av,{errorRef:r,options:l,valueRef:a}),okText:i("data-importer.mapping.add"),onOk:async()=>{var t,i;let o=a.current;if(void 0===o)return null==(i=r.current)||i.call(r,!0),await Promise.reject(Error("source required"));let n=(null==(t=l.find(e=>e.value===o))?void 0:t.label)??o;e(ab(o,n,"manual"),0),null!==x&&x!==o&&f(o)}})},onAddMappingForFilter:e=>{if(null===x)return;let t=v??x;e(ab(x,t,"manual"))},onAutoFill:(e,t)=>{let a=u(),r=new Set;a.forEach(e=>{(e.dataSourceIndex??[]).forEach(e=>r.add(e))}),l.forEach(e=>{r.has(e.value)||t({...ab(e.value,e.label,"autofill")})})},onCollapseAll:e=>{h(t=>{let a="all"===t?new Set(e):t;if(e.every(e=>!a.has(e))){let t=new Set(a);return e.forEach(e=>t.add(e)),t}{let t=new Set(a);return e.forEach(e=>t.delete(e)),t}})},onInsertItem:(e,t,a,r)=>{e(ab(a,r,"manual"),t)},onNewKey:e=>{h(t=>{if("all"===t)return"all";let a=new Set(t);return a.add(e),a})},onRemoveItem:(e,t)=>{null!==x&&(u().filter((e,a)=>a!==t).some(e=>(e.dataSourceIndex??[]).includes(x))||f(null)),e(t)},onToggleKey:(e,t)=>{h(a=>{let r=new Set("all"===a?t:a);return r.has(e)?r.delete(e):r.add(e),r})}})})})]})})},aS=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{fullWidth:t`
      width: 100%;
    `,loggingGroups:t`
      display: flex;
      flex-direction: column;
      gap: ${a.paddingSM}px;
      width: 100%;
    `,loggingGroup:t`
      width: 100%;
    `,loggingGroupTitle:t`
      margin-bottom: ${a.paddingXS}px;
      font-size: ${a.fontSize}px;
      font-weight: 600;
      color: ${a.colorTextHeading};
    `,loggingItem:t`
      margin-bottom: ${a.paddingXS}px;
    `,loggingItemLast:t`
      margin-bottom: 0;
    `}}),aC=e=>{let{configName:t}=e,{t:a}=(0,A.useTranslation)(),{styles:r,cx:i}=aS(),o=W.Form.useFormInstance(),{data:n}=tt({name:t}),l=((null==n?void 0:n.columnHeaders)??[]).map(e=>({value:"string"==typeof e?e:e.dataIndex??"",label:"string"==typeof e?e:e.label??e.dataIndex??""})),s=[{value:"sequential",label:a("data-importer.processing.execution-type.sequential")},{value:"parallel",label:a("data-importer.processing.execution-type.parallel")}],d=[{value:"delete",label:a("data-importer.processing.cleanup.strategy.delete")},{value:"unpublish",label:a("data-importer.processing.cleanup.strategy.unpublish")}],p=W.Form.useWatch(["processingConfig","logging","disableInfoLogs"]),c=W.Form.useWatch(["processingConfig","logging","disableErrorLogs"]);return(0,z.useEffect)(()=>{!0===p&&o.setFieldValue(["processingConfig","logging","disableInfoFileObjects"],!0)},[p,o]),(0,z.useEffect)(()=>{!0===c&&o.setFieldValue(["processingConfig","logging","disableErrorFileObjects"],!0)},[c,o]),(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(tx,{children:a("data-importer.processing.title")}),(0,q.jsxs)(ty,{title:a("data-importer.processing.execution.title"),children:[(0,q.jsx)(W.Form.Item,{label:a("data-importer.processing.execution-type"),name:["processingConfig","executionType"],tooltip:a("data-importer.processing.execution-type.tooltip"),children:(0,q.jsx)(W.Select,{filterOption:tb,options:s,showSearch:!0})}),(0,q.jsx)(W.Form.Item,{name:["processingConfig","doArchiveImportFile"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:a("data-importer.processing.archive-import-file")})}),(0,q.jsx)(W.Form.Item,{name:["processingConfig","disableVersioning"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:a("data-importer.processing.disable-versioning")})})]}),(0,q.jsxs)(ty,{title:a("data-importer.processing.id-delta.title"),children:[(0,q.jsx)(W.Form.Item,{label:a("data-importer.processing.id-data-index"),name:["processingConfig","idDataIndex"],tooltip:a("data-importer.processing.id-data-index.tooltip"),children:(0,q.jsx)(W.Select,{allowClear:!0,filterOption:tb,options:l,placeholder:a("data-importer.processing.id-data-index-placeholder"),showSearch:!0})}),(0,q.jsxs)(W.Form.Conditional,{condition:e=>{var t;return!!(null==(t=e.processingConfig)?void 0:t.idDataIndex)},children:[(0,q.jsx)(W.Form.Item,{name:["processingConfig","doDeltaCheck"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:a("data-importer.processing.delta-check")})}),(0,q.jsx)(W.Form.Item,{name:["processingConfig","cleanup","doCleanup"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:a("data-importer.processing.cleanup.do-cleanup")})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t,a;return!!(null==(a=e.processingConfig)||null==(t=a.cleanup)?void 0:t.doCleanup)},children:(0,q.jsx)(ty,{theme:"fieldset",title:a("data-importer.processing.cleanup.title"),children:(0,q.jsx)(W.Form.Item,{label:a("data-importer.processing.cleanup.strategy"),name:["processingConfig","cleanup","strategy"],tooltip:a("data-importer.processing.cleanup.strategy.tooltip"),children:(0,q.jsx)(W.Select,{filterOption:tb,options:d,showSearch:!0})})})})]})]}),(0,q.jsx)(ty,{title:a("data-importer.processing.logging.title"),children:(0,q.jsxs)("div",{className:r.loggingGroups,children:[(0,q.jsxs)("div",{className:r.loggingGroup,children:[(0,q.jsx)("div",{className:r.loggingGroupTitle,children:a("data-importer.processing.logging.info.title")}),(0,q.jsx)(W.Form.Item,{className:r.loggingItem,name:["processingConfig","logging","disableInfoLogs"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:a("data-importer.processing.logging.info.disable-logs")})}),(0,q.jsx)(W.Form.Item,{className:i(r.loggingItem,r.loggingItemLast),name:["processingConfig","logging","disableInfoFileObjects"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{disabled:!0===p,labelRight:a("data-importer.processing.logging.info.disable-file-objects")})})]}),(0,q.jsxs)("div",{className:r.loggingGroup,children:[(0,q.jsx)("div",{className:r.loggingGroupTitle,children:a("data-importer.processing.logging.error.title")}),(0,q.jsx)(W.Form.Item,{className:r.loggingItem,name:["processingConfig","logging","disableErrorLogs"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{labelRight:a("data-importer.processing.logging.error.disable-logs")})}),(0,q.jsx)(W.Form.Item,{className:i(r.loggingItem,r.loggingItemLast),name:["processingConfig","logging","disableErrorFileObjects"],valuePropName:"checked",children:(0,q.jsx)(W.Switch,{disabled:!0===c,labelRight:a("data-importer.processing.logging.error.disable-file-objects")})})]})]})})]})},aI=(0,V.createStyles)(e=>{let{css:t,token:a}=e;return{tabLayout:t`
      height: 100%;
      min-height: 0;
    `,stepContent:t`
      flex: 1;
      min-height: 0;
      overflow-y: auto;
      padding: ${a.paddingXS}px ${a.paddingSM}px;
    `,stepContentHidden:t`
      display: none;
    `,stepContentMapping:t`
      flex: 1;
      height: 0;
      overflow: auto;
    `,stepContentMappingHidden:t`
      display: none;
    `}}),aw=e=>{let{configName:t}=e,{t:a}=(0,A.useTranslation)(),{styles:r}=aI(),[i,o]=(0,z.useState)(0),{data:n}=tt({name:t}),l=((null==n?void 0:n.columnHeaders)??[]).map(e=>({value:"string"==typeof e?e:e.dataIndex??"",label:"string"==typeof e?e:e.label??e.dataIndex??""})),s=[{title:a("data-importer.data-setup.steps.data-source.title")},{title:a("data-importer.data-setup.steps.preview-import.title")},{title:a("data-importer.data-setup.steps.resolver.title")},{title:a("data-importer.data-setup.steps.mapping.title")},{title:a("data-importer.data-setup.steps.processing-settings.title")}],d=3===i;return(0,q.jsxs)(W.Flex,{className:r.tabLayout,vertical:!0,children:[(0,q.jsx)(W.Box,{margin:{x:"small"},children:(0,q.jsx)(W.Steps,{current:i,items:s,onChange:o,size:"small",type:"navigation"})}),(0,q.jsx)("div",{className:`${r.stepContentMapping}${d?"":` ${r.stepContentMappingHidden}`}`,children:(0,q.jsx)(aj,{configName:t,isActive:d})}),(0,q.jsx)("div",{className:`${r.stepContent}${0===i?"":` ${r.stepContentHidden}`}`,children:(0,q.jsx)(tM,{configName:t})}),(0,q.jsx)("div",{className:`${r.stepContent}${1===i?"":` ${r.stepContentHidden}`}`,children:(0,q.jsx)(tO,{configName:t,isActive:1===i})}),(0,q.jsx)("div",{className:`${r.stepContent}${2===i?"":` ${r.stepContentHidden}`}`,children:(0,q.jsx)(tz,{columnHeaderOptions:l,configName:t})}),(0,q.jsx)("div",{className:`${r.stepContent}${4===i?"":` ${r.stepContentHidden}`}`,children:(0,q.jsx)(aC,{configName:t})})]})},aT=(0,V.createStyles)(e=>{let{token:t,css:a}=e;return{progressLabel:a`
      font-size: 12px;
      font-weight: 400;
      line-height: 22px;
      color: ${t.colorText};
      margin: 0;
    `,progressWrapper:a`
      width: 100%;

      /* antd Progress root is inline-flex by default — override to fill full width */
      .ant-progress {
        display: block;
        width: 100%;
        margin-bottom: 0;
      }

      /* Ensure the inner bar line fills the wrapper */
      .ant-progress-inner {
        width: 100% !important;
      }

      /* Pad the inner text so it sits 8px from the left edge */
      .ant-progress-text {
        padding-inline-start: ${t.paddingXS}px;
      }
    `,colorFill:t.colorFill,colorBgLayout:t.colorBgLayout}});var aN=a(6514),aF=a(8221),ak=a.n(aF);let aD=()=>{let{t:e}=(0,A.useTranslation)(),t=W.Form.useFormInstance(),a=W.Form.useWatch(["executionConfig","cronDefinition"])??"",r=W.Form.useWatch(["loaderConfig","type"]),[i,o]=(0,z.useState)(a),[n,l]=(0,z.useState)(!1),s=(0,z.useCallback)(ak()(e=>{o(e),l(!1)},500),[]);(0,z.useEffect)(()=>()=>{s.cancel()},[s]),(0,z.useEffect)(()=>{a.trim().length>0&&l(!0),s(a)},[a,s]);let d=0===i.trim().length,{data:p,isFetching:c}=eA({cronExpression:i},{skip:d}),m=(n||c)&&a.trim().length>0;(0,z.useEffect)(()=>{n||c||t.validateFields([["executionConfig","cronDefinition"]],{dirty:!1}).catch(()=>{})},[p,c,n,d]);let{t:u}=(0,A.useTranslation)(),g=(0,z.useMemo)(()=>({async validator(e,t){void 0===t||0===t.trim().length?await Promise.resolve():n||c||void 0===p?await Promise.reject(Error("")):p.isValid?await Promise.resolve():await Promise.reject(Error(p.message))}}),[n,c,p,u]);return(0,q.jsx)(W.Form.Item,{hasFeedback:!m,label:(0,q.jsxs)(W.Flex,{align:"center",gap:8,children:[(0,q.jsx)("span",{children:e("data-importer.execution.cron-definition")}),(0,q.jsx)(W.Button,{href:"https://crontab.guru/",icon:(0,q.jsx)(W.Icon,{value:"share-nodes"}),rel:"noopener noreferrer",target:"_blank",type:"link",children:e("data-importer.execution.cron-generator")})]}),name:["executionConfig","cronDefinition"],rules:[g],validateStatus:m?"":void 0,children:(0,q.jsx)(W.Input,{disabled:"push"===r,onBlur:()=>{s.flush()},placeholder:"0 2 * * *",suffix:m?(0,q.jsx)(aN.A,{spin:!0}):(0,q.jsx)("span",{})})})},a$=e=>{let{isDirty:t,isStarting:a,onStart:r,label:i}=e,{t:o}=(0,A.useTranslation)(),n="push"===W.Form.useWatch(["loaderConfig","type"]),l=n||t||a,s=t?o("data-importer.execution.start-import.tooltip-dirty"):n?o("data-importer.execution.start-import.tooltip-push"):void 0;return(0,q.jsx)(W.Tooltip,{title:s,children:(0,q.jsx)("span",{children:(0,q.jsx)(W.Button,{disabled:l,loading:a&&!n,onClick:r,type:"primary",children:i})})})},aL=e=>{let{configName:t,isDirty:a}=e,{t:r}=(0,A.useTranslation)(),i=(0,W.useMessage)(),{styles:o}=aT(),[n,{isLoading:l}]=e$(),[s,{isLoading:d}]=eS(),{data:p,refetch:c}=eC({name:t},{pollingInterval:5e3}),[m,u]=(0,z.useState)(!1),[g,h]=(0,z.useState)(null),[x,f]=(0,z.useState)(!1),[v,y]=(0,z.useState)(!1);(0,z.useEffect)(()=>{(null==p?void 0:p.isRunning)===!0&&(u(!1),h(null),y(!1))},[null==p?void 0:p.isRunning]),(0,z.useEffect)(()=>{(null==p?void 0:p.isRunning)===!1&&(f(!1),(p.processedItems??0)>0&&y(!0))},[null==p?void 0:p.isRunning]);let b=!x&&(m||((null==p?void 0:p.isRunning)??!1)),j=(null==g?void 0:g.progress)??(null==p?void 0:p.progress)??0,S=(null==g?void 0:g.processedItems)??(null==p?void 0:p.processedItems)??0,C=(null==g?void 0:g.totalItems)??(null==p?void 0:p.totalItems)??0,I=async()=>{let e=await n({name:t});if("error"in e){void 0!==e.error&&(0,eg.trackError)(new eg.ApiError(e.error)),i.error(r("data-importer.execution.start-import.error"));return}e.data.success?(i.success(r("data-importer.execution.start-import.success")),h({processedItems:0,totalItems:(null==p?void 0:p.totalItems)??0,progress:0}),u(!0),y(!1)):i.error(r("data-importer.execution.start-import.error")),c()},w=async()=>{let e=await s({name:t});if("error"in e){void 0!==e.error&&(0,eg.trackError)(new eg.ApiError(e.error)),i.error(r("data-importer.execution.cancel.error"));return}i.success(r("data-importer.execution.cancel.success")),f(!0),u(!1),h(null),y(!1),c()},T=[{value:"recurring",label:r("data-importer.execution.schedule-type.recurring")},{value:"job",label:r("data-importer.execution.schedule-type.job")}];return(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(ty,{title:r("data-importer.execution.manual-execution"),children:(0,q.jsx)(a$,{isDirty:a,isStarting:l,label:r("data-importer.execution.start-import"),onStart:()=>{I()}})}),(0,q.jsxs)(ty,{title:r("data-importer.execution.settings.title"),children:[(0,q.jsx)(W.Form.Item,{initialValue:"recurring",label:r("data-importer.execution.schedule-type"),name:["executionConfig","scheduleType"],children:(0,q.jsx)(W.Select,{options:T})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return((null==(t=e.executionConfig)?void 0:t.scheduleType)??"recurring")==="recurring"},children:(0,q.jsx)(ty,{theme:"fieldset",title:r("data-importer.execution.schedule-type.recurring"),children:(0,q.jsx)(aD,{})})}),(0,q.jsx)(W.Form.Conditional,{condition:e=>{var t;return(null==(t=e.executionConfig)?void 0:t.scheduleType)==="job"},children:(0,q.jsx)(W.Form.Item,{label:r("data-importer.execution.scheduled-at"),name:["executionConfig","scheduledAt"],children:(0,q.jsx)(W.DatePicker,{showTime:!0})})})]}),(0,q.jsx)(ty,{noWidthLimit:!0,title:r("data-importer.execution.status.title"),children:b?(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)("p",{className:o.progressLabel,children:r("data-importer.execution.status.current-progress")}),(0,q.jsx)("div",{className:o.progressWrapper,children:(0,q.jsx)(W.Progress,{format:()=>r("data-importer.execution.status.processing",{processedItems:S,totalItems:C}),percent:Math.round(100*j),percentPosition:{align:"start",type:"inner"},size:[-1,32],status:"active",strokeColor:o.colorFill,trailColor:"rgba(0, 0, 0, 0.06)"})}),(0,q.jsx)(W.Button,{loading:d,onClick:()=>{w()},children:r("data-importer.execution.status.cancel")})]}):(0,q.jsx)(W.Text,{children:r(v?"data-importer.execution.status.finished":"data-importer.execution.status.not-running")})})]})};var aP=a(969);let aB=(0,V.createStyles)(e=>{let{css:t}=e;return{fullWidth:t`
      width: 100%;
    `}}),aM="YYYY-MM-DD HH:mm",aR=e=>{let{children:t}=e,[a,r]=(0,z.useState)("filter"),i=(0,z.useMemo)(()=>({entries:[],buttons:[],sizing:"default",highlights:[],activeTab:a,setEntries:()=>{},setButtons:()=>{},setSizing:()=>{},setHighlights:()=>{},setActiveTab:r,addEntry:()=>{},removeEntry:()=>{},addButton:()=>{},removeButton:()=>{},toggleHighlight:()=>{},openTab:e=>{r(e)},closeTab:()=>{r("")},toggleTab:e=>{r(t=>t===e?"":e)}}),[a]);return(0,q.jsx)(W.SidebarContext.Provider,{value:i,children:t})},aA=[{key:"filter",icon:(0,q.jsx)(W.Icon,{options:{width:"16px",height:"16px"},value:"filter"}),component:(0,q.jsx)(()=>{let{t:e}=(0,A.useTranslation)(),{styles:t}=aB(),[a]=W.Form.useForm(),{dateFrom:r,setDateFrom:i,dateTo:o,setDateTo:n,relatedObjectId:l,setRelatedObjectId:s,message:d,setMessage:p,pid:c,setPid:m,resetFilters:u,updateFilters:g,isLoading:h}=(0,aP.useFilter)();return(0,q.jsx)(W.ContentLayout,{renderToolbar:(0,q.jsxs)(W.Toolbar,{theme:"secondary",children:[(0,q.jsx)(W.IconTextButton,{disabled:h,icon:{value:"close"},onClick:()=>{u(),a.resetFields()},type:"link",children:e("sidebar.clear-all-filters")}),(0,q.jsx)(W.Button,{disabled:h,loading:h,onClick:g,type:"primary",children:e("button.apply")})]}),children:(0,q.jsx)(W.Content,{padded:!0,children:(0,q.jsx)(W.Form,{form:a,layout:"vertical",children:(0,q.jsxs)(W.Space,{className:t.fullWidth,direction:"vertical",size:"none",children:[(0,q.jsx)(W.Title,{children:e("application-logger.sidebar.search-parameter")}),(0,q.jsx)(W.Form.Item,{label:e("application-logger.filter.date-from"),name:"dateFrom",children:(0,q.jsx)(W.DatePicker,{className:"w-full",format:aM,onChange:e=>{i(e)},outputType:"dateString",showTime:{format:"HH:mm"},value:r})}),(0,q.jsx)(W.Form.Item,{label:e("application-logger.filter.date-to"),name:"dateTo",children:(0,q.jsx)(W.DatePicker,{className:"w-full",format:aM,onChange:e=>{n(e)},outputType:"dateString",showTime:{format:"HH:mm"},value:o})}),(0,q.jsx)(W.Form.Item,{label:e("application-logger.filter.priority"),name:"priority",children:(0,q.jsx)(aP.PrioritySelect,{})}),(0,q.jsx)(W.Form.Item,{label:e("application-logger.filter.message"),name:"message",children:(0,q.jsx)(W.Input,{onChange:e=>{let t=e.target.value;p(""!==t?t:null)},value:d??void 0})}),(0,q.jsx)(W.Form.Item,{label:e("application-logger.filter.related-object-id"),name:"relatedObjectId",children:(0,q.jsx)(W.Input,{min:"0",onChange:e=>{let t=e.target.value;s(""!==t?parseInt(t):null)},step:"1",type:"number",value:l??void 0})}),(0,q.jsx)(W.Form.Item,{label:e("application-logger.filter.pid"),name:"pid",children:(0,q.jsx)(W.Input,{min:"0",onChange:e=>{let t=e.target.value;m(""!==t?parseInt(t):null)},step:"1",type:"number",value:c??void 0})})]})})})})},{})}],aE=e=>{let{configName:t}=e,{t:a}=(0,A.useTranslation)(),r=(0,A.useAppDispatch)(),[i,o]=(0,z.useState)(1),[n,l]=(0,z.useState)(20),{columnFilters:s,setIsLoading:d}=(0,aP.useFilter)(),p=[...s,{key:"component",type:"equals",filterValue:"DATA-IMPORTER "+t}],{data:c,isFetching:m}=(0,aP.useBundleApplicationLoggerGetCollectionQuery)({body:{filters:{page:i,pageSize:n,columnFilters:p}}}),u=(null==c?void 0:c.totalItems)??0,g=(0,z.useCallback)(()=>{r(aP.api.util.invalidateTags(ex.invalidatingTags.APPLICATION_LOGGER()))},[r]),{refreshInterval:h,setRefreshInterval:x}=(e=>{let[t,a]=(0,z.useState)(void 0),r=(0,z.useCallback)(e,[e]);return(0,z.useEffect)(()=>{if((0,to.isNil)(t))return;let e=setInterval(()=>{r()},1e3*parseInt(t));return()=>{clearInterval(e)}},[t,r]),{refreshInterval:t,setRefreshInterval:a}})(g);return(0,z.useEffect)(()=>{d(m)},[m]),(0,q.jsx)(aR,{children:(0,q.jsx)(W.ContentLayout,{className:"h-full",renderSidebar:(0,q.jsx)(W.Sidebar,{entries:aA}),renderToolbar:(0,q.jsxs)(W.Toolbar,{justify:"space-between",theme:"secondary",children:[(0,q.jsxs)(W.Flex,{align:"center",gap:8,children:[!(0,to.isNil)(h)&&(0,q.jsx)("span",{children:a("application-logger.refresh-interval")}),(0,q.jsx)(W.CreatableSelect,{allowClear:!0,inputType:"number",minWidth:200,numberInputProps:{min:1},onChange:x,onCreateOption:e=>({value:e,label:a("application-logger.refresh-interval.seconds",{seconds:e})}),options:[{value:"3",label:a("application-logger.refresh-interval.seconds",{seconds:3})},{value:"5",label:a("application-logger.refresh-interval.seconds",{seconds:5})},{value:"10",label:a("application-logger.refresh-interval.seconds",{seconds:10})},{value:"30",label:a("application-logger.refresh-interval.seconds",{seconds:30})},{value:"60",label:a("application-logger.refresh-interval.seconds",{seconds:60})}],placeholder:a("application-logger.refresh-interval.select"),validate:e=>!isNaN(parseInt(e))&&parseInt(e)>0,value:h})]}),(0,q.jsxs)(W.Flex,{children:[(0,q.jsx)(W.IconButton,{disabled:m,icon:{value:"refresh"},onClick:g}),u>0&&(0,q.jsxs)(q.Fragment,{children:[(0,q.jsx)(W.Divider,{size:"small",type:"vertical"}),(0,q.jsx)(W.Pagination,{current:i,defaultPageSize:n,onChange:(e,t)=>{o(e),l(t)},showSizeChanger:!0,showTotal:e=>a("pagination.show-total",{total:e}),total:u})]})]})]}),children:(0,q.jsx)(W.Content,{loading:m,padded:!0,children:(0,q.jsx)(aP.ApplicationLoggerTable,{items:(null==c?void 0:c.items)??[]})})})})},aO=e=>{let{configName:t}=e;return(0,q.jsx)(aP.FilterProvider,{children:(0,q.jsx)(aE,{configName:t})})},aq=e=>{var t;let{configName:a,onChange:r,onDelete:o}=e,{t:n}=(0,A.useTranslation)(),{data:l,error:s,isLoading:d,isFetching:p,refetch:c,requestId:m}=tt({name:a},{refetchOnMountOrArgChange:!0}),[u,{error:g,isLoading:h}]=ta();(0,z.useEffect)(()=>{(0,to.isNil)(s)||(0,eg.trackError)(new eg.ApiError(s))},[s]),(0,z.useEffect)(()=>{(0,to.isNil)(g)||(0,eg.trackError)(new eg.ApiError(g))},[g]);let x=d||p,f=(null==l?void 0:l.configuration)??{},v=(null==l||null==(t=l.userPermissions)?void 0:t.update)??!0,y=async(e,t)=>{var r;let i=await u({name:a,bundleDataImporterConfigurationSaveParameters:{configuration:e,modificationDate:t}});if("error"in i)throw new eg.ApiError(i.error??{});return{modificationDate:null==(r=i.data)?void 0:r.modificationDate}},{form:b,isDirty:j,initialValues:S,handleSave:C,handleValuesChange:I}=(0,i.useDetailView)({configName:a,configData:f,modificationDate:null==l?void 0:l.modificationDate,isLoading:x,requestId:m,transformToForm:tg,transformToBackend:tp,onSave:y,onChange:r}),w=[{key:"general",label:n("data-importer.tabs.general"),children:(0,q.jsx)(i.GeneralTab,{adapterTypeLabel:n("data-importer.adapter.dataImporterDataObject")})},{key:"data-setup",label:n("data-importer.tabs.data-setup"),children:(0,q.jsx)(aw,{configName:a})},{key:"execution",label:n("data-importer.tabs.execution"),children:(0,q.jsx)(aL,{configName:a,isDirty:j})},{key:"import-logs",label:n("data-importer.tabs.import-logs"),children:(0,q.jsx)(aO,{configName:a})},{key:"permissions",label:n("data-importer.tabs.permissions"),children:(0,q.jsx)(i.PermissionsTab,{isWriteable:v})}],T=(0,q.jsx)(i.ConfigToolbar,{configName:a,isDirty:j,isLoading:x,isSaving:h,isWriteable:v,onDelete:o,onRefresh:c,onSave:C});return(0,q.jsx)(i.BaseDetailView,{disabled:!v,form:b,initialValues:S,isLoading:x,onValuesChange:I,requestId:m??"",tabs:w,toolbar:T})};class az extends i.DynamicTypeDataHubAdapterAbstract{getIcon(){return{type:"name",value:"data-objects-importer"}}renderDetailView(e){return(0,q.jsx)(aq,{...e})}constructor(...e){super(...e),this.id="dataImporterDataObject"}}az=(0,R.Cg)([(0,A.injectable)()],az),void 0!==(e=a.hmd(e)).hot&&e.hot.accept();let aH={name:"data-importer-plugin",onInit:e=>{let{container:t}=e;t.bind(String(o)).to(az).inSingletonScope()},onStartup:e=>{let{moduleSystem:t}=e;t.registerModule(e8),console.log("Hello from data importer bundle.")}}}}]);