<?php

// {
//   "data": {
//     "script": {
//       "displayName": "Ontology Report",
//       "relativeName": "ontology_report",
//       "description": "To extract and visualize the ontology of a model.",
//       "outputType": "BROWSER",
//       "scriptType": "PHP"
//     }
//   }
// }

use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.core.js',            array('version' => 'auto', 'relative' => false));
// HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.tiqGraphQL.js',      array('version' => 'auto', 'relative' => false));
// HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.components.min.js',  array('version' => 'auto', 'relative' => false));
// HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.charts.min.js',      array('version' => 'auto', 'relative' => false));

require_once 'thinkiq_context.php';
$context = new Context();

use Joomla\CMS\Factory;
$user = Factory::getUser();

?>

<script src="https://unpkg.com/@hpcc-js/wasm@2.20.0/dist/graphviz.umd.js"></script>
<script src="https://unpkg.com/d3-graphviz@5.6.0/build/d3-graphviz.js"></script>

<div id="app">

    <div class="row">            
        <div class="col-12">
            <h1 class="pb-2 pt-2" style="font-size:2.5rem; color:#126181;">
                {{pageTitle}}
                <a v-if="true" class="float-end btn btn-sm btn-link mt-2" style="font-size:1rem; color:#126181;" v-bind:href="`/applications/ide?node_ids=${context.std_inputs.script_id}&selected=${context.std_inputs.script_id}`" target="_blank">source</a>
            </h1>
            <hr style="border-color:#126181; border-width:medium;" />
        </div>   
    </div>

    <div class="row mb-3">
        <div class="col-2" style="padding-top: 2.4rem;">
            <div v-if="rootObjects.length">
                <strong>Root objects:</strong>
                <div class="mt-2">
                    <div v-for="root in rootObjects" :key="root.fqnRoot" class="form-check">
                        <input type="checkbox"
                               class="form-check-input"
                               v-model="root.included"
                               @change="RebuildAndRender"
                               :id="`root_chk_${root.fqnRoot}`">
                        <label class="form-check-label" :for="`root_chk_${root.fqnRoot}`">
                            {{ root.displayName }}
                        </label>
                    </div>
                </div>
            </div>

            <div v-if="VisibleRelationshipGroups.length" class="mt-4">
                <strong>Relationships:</strong>
                <div v-for="group in VisibleRelationshipGroups" :key="group.key" class="mt-2">
                    <div class="text-muted" style="font-size:0.8rem;">{{ group.title }}</div>
                    <div v-for="(rel, idx) in group.types" :key="rel.name" class="form-check">
                        <input type="checkbox"
                               class="form-check-input"
                               v-model="rel.included"
                               @change="RebuildAndRender"
                               :id="`rel_chk_${group.key}_${idx}`">
                        <label class="form-check-label" :for="`rel_chk_${group.key}_${idx}`">
                            {{ rel.name }}
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-10">
            <div class="d-flex justify-content-end mb-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="Diagram actions">
                    <button class="btn btn-outline-secondary"
                            @click="CopyGviz"
                            title="Copy the raw Graphviz DOT source to the clipboard">
                        {{ copyGvizLabel }}
                    </button>
                    <button class="btn btn-outline-secondary"
                            @click="DownloadSvg"
                            title="Download the current diagram as an SVG file (best for editing in vector tools)">
                        Download SVG
                    </button>
                    <button class="btn btn-outline-secondary"
                            @click="DownloadPng"
                            title="Download the current diagram as a PNG image (best for PowerPoint and viewing)">
                        Download PNG
                    </button>
                </div>
            </div>

            <div id="graph"
                 style="width:100%; max-width:100%; height:70vh; max-height:70vh;
                        overflow:hidden; border:1px solid #e0e0e0; border-radius:4px;
                        background:#fafafa;"></div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center py-2"
                 style="border-top: 1px solid #e0e0e0; cursor: pointer; user-select: none;"
                 @click="tableVisible = !tableVisible">
                <h5 class="mb-0" style="color:#126181;">
                    <i class="fa fa-chevron-right" :style="{
                        display: 'inline-block',
                        width: '1rem',
                        transition: 'transform 0.15s ease',
                        transform: tableVisible ? 'rotate(90deg)' : 'rotate(0deg)'
                    }"></i>
                    Link details ({{ ExistingLinksByCountDesc.length }})
                </h5>
                <button v-if="tableVisible"
                        class="btn btn-sm btn-outline-secondary"
                        @click.stop="DownloadCsv"
                        title="Download the link details table as a CSV file">
                    Download CSV
                </button>
            </div>
            <table v-show="tableVisible" class="table mt-2">
                <thead>
                    <tr>
                    <th scope="col">Count</th>
                    <th scope="col">Link Type</th>
                    <th scope="col">From Type</th>
                    <th scope="col">To Type</th>
                    <th scope="col">To Kind</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="aLink in ExistingLinksByCountDesc">
                        <th scope="row">{{aLink.count}}</th>
                        <td>{{aLink.linkTypeName}}</td>
                        <td>{{aLink.subjectTypeName}}</td>
                        <td>{{aLink.objectTypeName}}</td>
                        <td>{{aLink.objectIsType ? "Type" : "Instance"}}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>


<script>
    var WinDoc = window.document;
    
    // we need a clipboard so we can copy / paste
    var clipboard = navigator.clipboard;

    var app = createApp({
        // el: "#app",
        data() {
            return {
                clipboard: clipboard,
                pageTitle: "Extract Ontology from Model",
                context:<?php echo json_encode($context)?>,
                user:<?php echo json_encode($user)?>,
                types: [],
                objects: [],
                links: [],
                existingLinks: [],
                rootObjects: [],          // [{ fqnRoot, displayName, included }] — drives the root checkbox sidebar
                relationshipTypes: [],    // [{ name, included }] — drives the relationship-type checkbox sidebar
                diagram: "",              // latest DOT source — kept around so Copy GViz can grab it
                tableVisible: false,      // collapsible "Link details" section, hidden by default
                copyGvizLabel: "Copy GViz", // toggles to "Copied!" briefly after a successful copy
                d3: d3
            }
        },
        mounted: async function () {
            WinDoc.title = this.pageTitle;
            await this.LoadModelAsync();
        },
        computed: {
            ExistingLinksByCountDesc: function(){
                // copy before sorting — the DOT builder relies on the
                // insertion order of `existingLinks`, and Array.sort mutates
                return [...this.existingLinks].sort((a,b)=>a.count <= b.count ? 1 : -1);
            },
            // restrict the relationships sidebar to types that actually appear
            // inside the currently-selected root subtrees, split into sections
            // by what sits at the object end of the link. The underlying
            // `relationshipTypes` array still holds every type the model has
            // ever exposed, so a user's checkbox state survives roots toggling
            // off and back on.
            VisibleRelationshipGroups: function(){
                if(!this.relationshipTypes.length) return [];

                let includedRoots = new Set(
                    this.rootObjects.filter(r => r.included).map(r => r.fqnRoot)
                );
                let filteredObjectIds = new Set(
                    this.objects
                        .filter(o => o.fqn && o.fqn.length > 0 && includedRoots.has(o.fqn[0]))
                        .map(o => o.id)
                );
                let typeIds = new Set(this.types.map(t => t.id));

                // which object-end kinds each relationship type actually shows
                // in scope. A set rather than a single value because nothing in
                // the schema stops a type from doing both — "Can Use Sighting
                // Type" is all-type today, but that's data, not a guarantee.
                let objectEndKinds = new Map();
                // "Contains" is synthesized from partOf, so it is always
                // instance -> instance, and present whenever any object
                // survives the root filter
                if(filteredObjectIds.size > 0){
                    objectEndKinds.set("Contains", new Set(["instance"]));
                }
                this.links.forEach(r => {
                    if(!filteredObjectIds.has(r.subjectId)) return;
                    let relName = r.relationshipType ? r.relationshipType.displayName : null;
                    if(!relName) return;
                    // a type end is always in scope, since types are global
                    let kind = filteredObjectIds.has(r.objectId) ? "instance"
                             : (typeIds.has(r.objectId) ? "type" : null);
                    if(kind == null) return;
                    if(!objectEndKinds.has(relName)){
                        objectEndKinds.set(relName, new Set());
                    }
                    objectEndKinds.get(relName).add(kind);
                });

                let groups = [
                    { key: "instance", title: "Instance → Instance", types: [] },
                    { key: "type",     title: "Instance → Type",     types: [] },
                    { key: "mixed",    title: "Mixed",               types: [] }
                ];
                this.relationshipTypes.forEach(rt => {
                    let kinds = objectEndKinds.get(rt.name);
                    if(!kinds) return;   // not present in the selected roots
                    let key = kinds.size > 1 ? "mixed" : (kinds.has("type") ? "type" : "instance");
                    groups.find(g => g.key == key).types.push(rt);
                });

                return groups.filter(g => g.types.length > 0);
            }
        },
        methods: {

            LoadModelAsync: async function(){

                let query = `
                query q1 {
                    tiqTypes {
                        id
                        displayName
                    }
                    objects(filter: { typeId: { isNull: false } }) {
                        id
                        displayName
                        typeId
                        typeName
                        fqn
                        partOf{
                            typeId
                            typeName
                        }
                    }
                    relationships{
                        relationshipTypeName
                        subjectId
                        objectId
                        relationshipType{
                            displayName
                        }
                    }
                }
                `;

                let aResponse = await tiqJSHelper.invokeGraphQLAsync(query);
                this.types = aResponse.data.tiqTypes;
                this.objects = aResponse.data.objects;
                this.links = aResponse.data.relationships;

                // discover the distinct root "branches" — keyed by fqn[0] so the
                // checkbox list reflects every top-level subtree present in the
                // full ontology pull. Filtering itself happens client-side.
                let seenRoots = new Set();
                this.rootObjects = [];
                this.objects.forEach(o => {
                    if(o.fqn && o.fqn.length > 0 && !seenRoots.has(o.fqn[0])){
                        seenRoots.add(o.fqn[0]);
                        // prefer the displayName of the actual root object when present
                        let rootObj = this.objects.find(x => x.partOf == null && x.fqn && x.fqn[0] == o.fqn[0]);
                        this.rootObjects.push({
                            fqnRoot: o.fqn[0],
                            displayName: rootObj ? rootObj.displayName : o.fqn[0],
                            included: true
                        });
                    }
                });
                this.rootObjects.sort((a,b) => a.displayName.localeCompare(b.displayName));

                // populate the relationship-type checkbox list — "Contains" is
                // a synthesized pseudo-relationship from partOf, the rest come
                // from the actual relationships query
                let relTypeNames = new Set(["Contains"]);
                this.links.forEach(r => {
                    if(r.relationshipType && r.relationshipType.displayName){
                        relTypeNames.add(r.relationshipType.displayName);
                    }
                });
                this.relationshipTypes = Array.from(relTypeNames)
                    .sort((a,b) => a.localeCompare(b))
                    .map(name => ({ name, included: true }));

                this.RebuildAndRender();
            },

            TypeDisplayName: function(typeId){
                let aType = this.types.find(x => x.id == typeId);
                return aType ? aType.displayName : typeId;
            },

            // resolve the object end of a relationship. Most relationship
            // types link instance -> instance, but some — "Can Use Sighting
            // Type" being the case that surfaced this — link an instance
            // straight at a *type*: the objectId resolves against tiqTypes,
            // not objects. Those used to be dropped silently, because the
            // instance lookup came up empty. Returns null when the end falls
            // outside the selected roots (or can't be resolved at all).
            ResolveObjectEnd: function(objectId, filteredObjectIds){
                let aObject = this.objects.find(x => x.id == objectId);
                if(aObject != null){
                    if(!filteredObjectIds.has(aObject.id)) return null;
                    return {
                        typeRelativeName : aObject.typeName,
                        typeName : this.TypeDisplayName(aObject.typeId),
                        isType : false
                    };
                }
                // types are global, so a type end is always in scope
                let aType = this.types.find(x => x.id == objectId);
                if(aType == null) return null;
                return {
                    // prefix keeps the aggregation key from colliding with an
                    // instance-side typeName that happens to read the same
                    typeRelativeName : `#type:${aType.id}`,
                    typeName : aType.displayName,
                    isType : true
                };
            },

            _DotEscape: function(value){
                return String(value == null ? "" : value)
                    .replace(/\\/g, "\\\\")
                    .replace(/"/g, '\\"');
            },

            // render `existingLinks` as readable DOT — one edge per line,
            // grouped into a commented section per link type, so the Copy
            // GViz output can be pasted somewhere and actually read
            _BuildDot: function(){
                let lines = [
                    "digraph G {",
                    "    graph [rankdir=TB]",
                    '    node  [shape=ellipse, fontname="Helvetica", fontsize=11]',
                    '    edge  [fontname="Helvetica", fontsize=10]'
                ];

                // section order follows first appearance, which puts the
                // Contains backbone first and the relationships after it
                let linkTypeNames = [];
                this.existingLinks.forEach(aLink => {
                    if(!linkTypeNames.includes(aLink.linkTypeName)){
                        linkTypeNames.push(aLink.linkTypeName);
                    }
                });

                linkTypeNames.forEach(linkTypeName => {
                    let sectionLinks = this.existingLinks.filter(x => x.linkTypeName == linkTypeName);
                    lines.push("");
                    lines.push(`    // ${linkTypeName}`);
                    sectionLinks.forEach(aLink => {
                        let label = this._DotEscape(`${aLink.linkTypeName} (${aLink.count})`);
                        let attributes = [`label="${label}"`];
                        if(aLink.linkTypeName == "Contains"){
                            // containment is the skeleton of the drawing, so
                            // weight it: dot keeps weighted edges short and
                            // straight, and the tree comes out as the spine
                            attributes.push("weight=8");
                        } else {
                            // every other link type is an overlay on that tree.
                            // Letting them constrain rank assignment is what
                            // used to stretch the graph — "Made Sighting" spans
                            // seven full ranks, so dot shoved those edges out to
                            // the canvas margins to get around the nodes in
                            // between. Unconstrained, they're drawn where they
                            // fall without distorting the hierarchy.
                            attributes.push("constraint=false");
                        }
                        // dashed purple with a hollow head marks an instance ->
                        // type reference: the head of the arrow is the type
                        // itself, not a set of instances of it
                        if(aLink.objectIsType){
                            attributes.push("style=dashed", 'color="#7b1fa2"', 'fontcolor="#7b1fa2"', "arrowhead=onormal");
                        }
                        lines.push(`    "${this._DotEscape(aLink.subjectTypeName)}" -> "${this._DotEscape(aLink.objectTypeName)}" [${attributes.join(", ")}]`);
                    });
                });

                lines.push("}");
                return lines.join("\n");
            },

            _buildCleanSvg: function(){
                // produce a detached SVG clone suitable for export — strips
                // the inline screen-fit style so natural viewBox dimensions
                // drive layout, and ensures the standard XML namespaces are
                // present so the file is valid standalone
                let live = WinDoc.querySelector("#graph svg");
                if(!live){ return null; }
                let clone = live.cloneNode(true);
                clone.removeAttribute("style");
                if(!clone.getAttribute("xmlns")){
                    clone.setAttribute("xmlns", "http://www.w3.org/2000/svg");
                }
                if(!clone.getAttribute("xmlns:xlink")){
                    clone.setAttribute("xmlns:xlink", "http://www.w3.org/1999/xlink");
                }
                return clone;
            },

            _timestampForFilename: function(){
                return new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
            },

            _triggerDownload: function(blob, filename){
                let url = URL.createObjectURL(blob);
                let link = WinDoc.createElement("a");
                link.href = url;
                link.download = filename;
                WinDoc.body.appendChild(link);
                link.click();
                WinDoc.body.removeChild(link);
                URL.revokeObjectURL(url);
            },

            DownloadSvg: function(){
                let clone = this._buildCleanSvg();
                if(!clone){ return; }

                let svgString = '<?xml version="1.0" standalone="no"?>\n'
                    + new XMLSerializer().serializeToString(clone);
                let blob = new Blob([svgString], { type: "image/svg+xml;charset=utf-8" });
                this._triggerDownload(blob, `ontology_${this._timestampForFilename()}.svg`);
            },

            CopyGviz: function(){
                if(!this.diagram){ return; }
                let self = this;
                this.clipboard.writeText(this.diagram).then(() => {
                    self.copyGvizLabel = "Copied!";
                    setTimeout(() => { self.copyGvizLabel = "Copy GViz"; }, 1500);
                }).catch(err => {
                    console.error("Failed to copy GViz source to clipboard", err);
                    self.copyGvizLabel = "Copy failed";
                    setTimeout(() => { self.copyGvizLabel = "Copy GViz"; }, 1500);
                });
            },

            DownloadCsv: function(){
                let rows = [["Count", "Link Type", "From Type", "To Type", "To Kind"]];
                this.ExistingLinksByCountDesc.forEach(link => {
                    rows.push([
                        link.count,
                        link.linkTypeName,
                        link.subjectTypeName,
                        link.objectTypeName,
                        link.objectIsType ? "Type" : "Instance"
                    ]);
                });
                // RFC 4180-ish escaping — quote any cell with commas, quotes,
                // or newlines and double-up embedded quotes
                let csv = rows.map(row =>
                    row.map(cell => {
                        let s = String(cell == null ? "" : cell);
                        if(/[",\n\r]/.test(s)){
                            return '"' + s.replace(/"/g, '""') + '"';
                        }
                        return s;
                    }).join(",")
                ).join("\r\n");

                let blob = new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8" });
                this._triggerDownload(blob, `ontology_links_${this._timestampForFilename()}.csv`);
            },

            DownloadPng: function(){
                let clone = this._buildCleanSvg();
                if(!clone){ return; }

                // determine pixel dimensions from the viewBox; fall back to the
                // live SVG's bounding box if no viewBox is present
                let width = 0, height = 0;
                let vb = clone.getAttribute("viewBox");
                if(vb){
                    let parts = vb.split(/[\s,]+/).map(Number);
                    width = parts[2];
                    height = parts[3];
                }
                if(!width || !height){
                    let live = WinDoc.querySelector("#graph svg");
                    if(live){
                        let bbox = live.getBBox();
                        width = bbox.width;
                        height = bbox.height;
                    }
                }
                if(!width || !height){ width = 1200; height = 800; }

                // 2x scale for crisper output in slide decks at typical sizes
                let scale = 2;
                let canvas = WinDoc.createElement("canvas");
                canvas.width = Math.ceil(width * scale);
                canvas.height = Math.ceil(height * scale);
                let ctx = canvas.getContext("2d");
                ctx.scale(scale, scale);
                // PowerPoint and most viewers expect an opaque background
                ctx.fillStyle = "#ffffff";
                ctx.fillRect(0, 0, width, height);

                let svgString = new XMLSerializer().serializeToString(clone);
                let svgDataUrl = "data:image/svg+xml;charset=utf-8,"
                    + encodeURIComponent(svgString);

                let stamp = this._timestampForFilename();
                let self = this;
                let img = new Image();
                img.onload = function(){
                    ctx.drawImage(img, 0, 0, width, height);
                    canvas.toBlob(function(blob){
                        if(!blob){
                            console.error("Canvas toBlob returned null for PNG export");
                            return;
                        }
                        self._triggerDownload(blob, `ontology_${stamp}.png`);
                    }, "image/png");
                };
                img.onerror = function(e){
                    console.error("Failed to rasterize SVG for PNG export", e);
                };
                img.src = svgDataUrl;
            },

            RebuildAndRender: function(){
                // determine which root subtrees and which relationship types
                // the user wants visible
                let includedRoots = new Set(
                    this.rootObjects.filter(r => r.included).map(r => r.fqnRoot)
                );
                let includedRels = new Set(
                    this.relationshipTypes.filter(r => r.included).map(r => r.name)
                );
                let filteredObjects = this.objects.filter(o =>
                    o.fqn && o.fqn.length > 0 && includedRoots.has(o.fqn[0])
                );
                let filteredObjectIds = new Set(filteredObjects.map(o => o.id));

                // rebuild the type-level link aggregation from the filtered set
                this.existingLinks = [];

                // build contains linkages — only when "Contains" is enabled
                if(includedRels.has("Contains")){
                    filteredObjects.forEach(aObject => {
                        let existingLink = this.existingLinks.find(x=>
                            x.subjectTypeRelativeName == (aObject.partOf == null ? "root" : aObject.partOf.typeName) &&
                            x.objectTypeRelativeName == aObject.typeName &&
                            x.linkTypeName == "Contains"
                        );
                        if(existingLink==null){
                            this.existingLinks.push({
                                subjectTypeRelativeName : aObject.partOf == null ? "root" : aObject.partOf.typeName,
                                subjectTypeName : aObject.partOf == null ? "Root" : this.TypeDisplayName(aObject.partOf.typeId),
                                objectTypeRelativeName : aObject.typeName,
                                objectTypeName : this.TypeDisplayName(aObject.typeId),
                                linkTypeName : "Contains",
                                objectIsType : false,
                                count : 1
                            });
                        } else {
                            existingLink.count++;
                        }
                    });
                }

                // build relationship linkages — drop any relationship whose
                // endpoints fall outside the currently selected roots, or
                // whose type the user has switched off
                this.links.forEach(aRelationship => {
                    if(!filteredObjectIds.has(aRelationship.subjectId)) return;
                    let relName = aRelationship.relationshipType ? aRelationship.relationshipType.displayName : null;
                    if(!relName || !includedRels.has(relName)) return;
                    let aSubject = this.objects.find(x=>x.id==aRelationship.subjectId);
                    let aObjectEnd = this.ResolveObjectEnd(aRelationship.objectId, filteredObjectIds);
                    if(aObjectEnd == null) return;
                    let existingLink = this.existingLinks.find(x=>
                        x.subjectTypeRelativeName == aSubject.typeName &&
                        x.objectTypeRelativeName == aObjectEnd.typeRelativeName &&
                        x.linkTypeName == relName
                    );
                    if(existingLink==null){
                        this.existingLinks.push({
                            subjectTypeRelativeName : aSubject.typeName,
                            subjectTypeName : this.TypeDisplayName(aSubject.typeId),
                            objectTypeRelativeName : aObjectEnd.typeRelativeName,
                            objectTypeName : aObjectEnd.typeName,
                            linkTypeName : relName,
                            objectIsType : aObjectEnd.isType,
                            count : 1
                        });
                    } else {
                        existingLink.count++;
                    }
                });

                // build digraph string — stash on the instance so Copy GViz
                // can hand the user the exact source that produced what they
                // see on screen
                let diagram = this._BuildDot();
                this.diagram = diagram;

                // render diagram — reusing the cached d3-graphviz instance on
                // the #graph selection means the wheel-zoom / drag-pan
                // behavior attached on first render survives subsequent
                // re-renders triggered by the root checkboxes. (Earlier we
                // wiped innerHTML here, which orphaned the zoom listeners.)
                this.d3.select("#graph")
                    .graphviz()
                        .zoom(true)
                        .dot(diagram)
                        .render(() => {
                            // strip graphviz's fixed pixel width/height so the
                            // SVG fills the constrained #graph box; the viewBox
                            // handles aspect-preserving fit-to-container
                            let svg = WinDoc.querySelector("#graph svg");
                            if(svg){
                                svg.removeAttribute("width");
                                svg.removeAttribute("height");
                                svg.style.width = "100%";
                                svg.style.height = "100%";
                                svg.style.display = "block";
                            }
                        });
            },
        },
    })
    .mount('#app');
</script>
