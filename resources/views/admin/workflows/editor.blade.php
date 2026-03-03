<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen" x-data="workflowEditor()" x-init="init()">

            {{-- Toolbar --}}
            <div class="mb-4 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    <a href="{{ route('admin.workflows.index') }}" class="hover:text-red-700 dark:hover:text-red-300">Automation</a> /
                    <span class="text-red-700 dark:text-red-300 font-medium">{{ $workflow->name }}</span>
                </p>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                            {{ $workflow->name }}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ml-2
                                {{ $workflow->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                {{ $workflow->status === 'draft' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                {{ $workflow->status === 'disabled' ? 'bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300' : '' }}">
                                {{ ucfirst($workflow->status) }}
                            </span>
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Version {{ $workflow->current_version }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button @click="saveGraph()" :disabled="saving"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-lg transition">
                            <i class="fa-solid fa-floppy-disk mr-2"></i>
                            <span x-text="saving ? 'Saving...' : 'Save'"></span>
                        </button>
                        <button @click="validateWorkflow()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition">
                            <i class="fa-solid fa-check-double mr-2"></i> Validate
                        </button>
                        <button @click="publishWorkflow()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition">
                            <i class="fa-solid fa-rocket mr-2"></i> Publish
                        </button>
                        <button @click="runWorkflow(false)"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                            <i class="fa-solid fa-play mr-2"></i> Run
                        </button>
                        <button @click="runWorkflow(true)"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition">
                            <i class="fa-solid fa-flask mr-2"></i> Dry Run
                        </button>
                    </div>
                </div>
            </div>

            {{-- Status Messages --}}
            <template x-if="statusMessage">
                <div class="mb-4 p-3 rounded-lg text-sm font-medium"
                    :class="statusType === 'success' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                            statusType === 'error' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'">
                    <span x-text="statusMessage"></span>
                    <button @click="statusMessage = ''" class="float-right font-bold">&times;</button>
                </div>
            </template>

            {{-- Validation Errors --}}
            <template x-if="validationErrors.length > 0">
                <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <p class="text-sm font-semibold text-red-700 dark:text-red-400 mb-1">Validation Errors:</p>
                    <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400">
                        <template x-for="err in validationErrors" :key="err">
                            <li x-text="err"></li>
                        </template>
                    </ul>
                </div>
            </template>

            {{-- Editor Layout: Palette + Canvas + Inspector --}}
            <div class="flex gap-4" style="height: calc(100vh - 280px); min-height: 400px;">

                {{-- Node Palette (Left Panel) --}}
                <div class="w-64 flex-shrink-0 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-y-auto">
                    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Node Palette</h3>
                    </div>

                    {{-- Triggers --}}
                    <div class="p-3">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Triggers</h4>
                        <template x-for="node in catalog.triggers" :key="node.action_type">
                            <div class="flex items-center gap-2 p-2 mb-1.5 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg cursor-grab text-sm hover:shadow-sm transition"
                                 draggable="true" @dragstart="onDragStart($event, node)"
                                 :aria-label="'Drag to add ' + node.label + ' trigger'">
                                <i class="fa-solid fa-bolt text-purple-600 dark:text-purple-400 w-4"></i>
                                <span class="text-gray-700 dark:text-gray-300 font-medium" x-text="node.label"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Conditions --}}
                    <div class="p-3 pt-0">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Conditions</h4>
                        <template x-for="node in catalog.conditions" :key="node.action_type">
                            <div class="flex items-center gap-2 p-2 mb-1.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg cursor-grab text-sm hover:shadow-sm transition"
                                 draggable="true" @dragstart="onDragStart($event, node)"
                                 :aria-label="'Drag to add ' + node.label + ' condition'">
                                <i class="fa-solid fa-diamond text-amber-600 dark:text-amber-400 w-4"></i>
                                <span class="text-gray-700 dark:text-gray-300 font-medium" x-text="node.label"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Actions --}}
                    <div class="p-3 pt-0">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Actions</h4>
                        <template x-for="node in catalog.actions" :key="node.action_type">
                            <div class="flex items-center gap-2 p-2 mb-1.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg cursor-grab text-sm hover:shadow-sm transition"
                                 draggable="true" @dragstart="onDragStart($event, node)"
                                 :aria-label="'Drag to add ' + node.label + ' action'">
                                <i class="fa-solid fa-gear text-blue-600 dark:text-blue-400 w-4"></i>
                                <span class="text-gray-700 dark:text-gray-300 font-medium" x-text="node.label"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Canvas (Center) --}}
                <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 relative overflow-hidden"
                     @dragover.prevent @drop="onDrop($event)"
                     id="workflow-canvas" role="application" aria-label="Workflow canvas - drag nodes here">

                    {{-- Canvas Background Grid --}}
                    <div class="absolute inset-0" style="background-image: radial-gradient(circle, #e5e7eb 1px, transparent 1px); background-size: 20px 20px;" aria-hidden="true"></div>

                    {{-- Edges SVG Layer --}}
                    <svg class="absolute inset-0 w-full h-full pointer-events-none" style="z-index: 1;">
                        <g x-ref="edgesLayer"></g>
                        <defs>
                            <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
                                <polygon points="0 0, 10 3.5, 0 7" fill="#6b7280"/>
                            </marker>
                        </defs>
                        {{-- Connecting line preview --}}
                        <line x-show="connecting"
                              :x1="connectStart.x" :y1="connectStart.y"
                              :x2="connectEnd.x" :y2="connectEnd.y"
                              stroke="#ef4444" stroke-width="2" stroke-dasharray="5,5"/>
                    </svg>

                    {{-- Nodes --}}
                    <template x-for="node in nodes" :key="node.node_id">
                        <div class="absolute cursor-move select-none rounded-xl shadow-md border-2 transition-shadow"
                             :class="{
                                 'border-purple-400 bg-purple-50 dark:bg-purple-900/30': node.type === 'trigger',
                                 'border-amber-400 bg-amber-50 dark:bg-amber-900/30': node.type === 'condition',
                                 'border-blue-400 bg-blue-50 dark:bg-blue-900/30': node.type === 'action',
                                 'ring-2 ring-red-500': selectedNode && selectedNode.node_id === node.node_id
                             }"
                             :style="'left:' + (node.position?.x || 100) + 'px; top:' + (node.position?.y || 100) + 'px; z-index: 10; min-width: 160px;'"
                             @mousedown="startDragNode($event, node)"
                             @click.stop="selectNode(node)"
                             :aria-label="node.label" role="button" tabindex="0"
                             @keydown.delete="removeNode(node.node_id)"
                             @keydown.backspace="removeNode(node.node_id)">

                            {{-- Node Header --}}
                            <div class="flex items-center gap-2 px-3 py-2 border-b"
                                 :class="{
                                     'border-purple-200 dark:border-purple-700': node.type === 'trigger',
                                     'border-amber-200 dark:border-amber-700': node.type === 'condition',
                                     'border-blue-200 dark:border-blue-700': node.type === 'action',
                                 }">
                                <i :class="{
                                    'fa-solid fa-bolt text-purple-600': node.type === 'trigger',
                                    'fa-solid fa-diamond text-amber-600': node.type === 'condition',
                                    'fa-solid fa-gear text-blue-600': node.type === 'action',
                                }" class="text-xs"></i>
                                <span class="text-xs font-bold uppercase tracking-wider"
                                      :class="{
                                          'text-purple-700 dark:text-purple-300': node.type === 'trigger',
                                          'text-amber-700 dark:text-amber-300': node.type === 'condition',
                                          'text-blue-700 dark:text-blue-300': node.type === 'action',
                                      }" x-text="node.type"></span>
                            </div>

                            {{-- Node Body --}}
                            <div class="px-3 py-2">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200" x-text="node.label"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="node.action_type"></p>
                            </div>

                            {{-- Connection Handles --}}
                            <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-4 h-4 bg-gray-400 dark:bg-gray-500 rounded-full border-2 border-white dark:border-gray-800 cursor-crosshair hover:bg-red-500 transition"
                                 @mousedown.stop="startConnect($event, node)"
                                 title="Drag to connect" aria-label="Connect from this node"></div>
                            <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 w-4 h-4 bg-gray-400 dark:bg-gray-500 rounded-full border-2 border-white dark:border-gray-800 hover:bg-green-500 transition"
                                 @mouseup.stop="endConnect(node)"
                                 title="Drop connection here" aria-label="Connect to this node"></div>

                            {{-- Delete button --}}
                            <button class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600 transition opacity-0 group-hover:opacity-100"
                                    :class="selectedNode && selectedNode.node_id === node.node_id ? 'opacity-100' : 'opacity-0 hover:opacity-100'"
                                    @click.stop="removeNode(node.node_id)" aria-label="Remove node">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </template>

                    {{-- Empty State --}}
                    <template x-if="nodes.length === 0">
                        <div class="absolute inset-0 flex items-center justify-center" style="z-index: 5;">
                            <div class="text-center">
                                <i class="fa-regular fa-diagram-project text-5xl text-gray-300 dark:text-gray-600 mb-3"></i>
                                <p class="text-gray-500 dark:text-gray-400 font-medium">Drag nodes from the palette to build your workflow</p>
                                <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">Connect nodes by dragging from bottom to top handles</p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Inspector Panel (Right) --}}
                <div class="w-72 flex-shrink-0 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-y-auto">
                    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Inspector</h3>
                    </div>

                    <template x-if="!selectedNode">
                        <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                            <i class="fa-regular fa-hand-pointer text-3xl mb-2"></i>
                            <p class="text-sm">Select a node to inspect and configure it</p>
                        </div>
                    </template>

                    <template x-if="selectedNode">
                        <div class="p-4 space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Label</label>
                                <input type="text" x-model="selectedNode.label" @input="markDirty()"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Type</label>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize" x-text="selectedNode.type"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Template</label>
                                <select x-model="selectedNode.action_type" @change="onTemplateChanged(selectedNode)"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-gray-900 dark:text-white">
                                    <template x-for="template in getNodeTypeOptions(selectedNode.type)" :key="template.action_type">
                                        <option :value="template.action_type" x-text="template.label"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Preset</label>
                                <select x-model="selectedPresetKey" @change="applyPresetToSelected(selectedPresetKey)"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-gray-900 dark:text-white">
                                    <option value="">Custom</option>
                                    <template x-for="preset in getNodePresets(selectedNode)" :key="preset.key">
                                        <option :value="preset.key" x-text="preset.label"></option>
                                    </template>
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Pre-made values auto-fill node configuration.</p>
                            </div>

                            {{-- Dynamic Config Fields --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Configuration</label>

                                <template x-if="Object.keys(getConfigSchema(selectedNode)).length === 0">
                                    <p class="text-xs text-gray-400">No additional configuration required for this node.</p>
                                </template>

                                <template x-for="field in Object.keys(getConfigSchema(selectedNode))" :key="field">
                                    <div class="mb-3">
                                        <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1" x-text="formatFieldLabel(field)"></label>

                                        <template x-if="fieldOptions(selectedNode, field).length > 0">
                                            <select :value="stringValue(getConfigValue(selectedNode, field))"
                                                @change="setConfigValue(selectedNode, field, $event.target.value)"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-gray-900 dark:text-white">
                                                <template x-for="option in fieldOptions(selectedNode, field)" :key="option">
                                                    <option :value="option" x-text="option"></option>
                                                </template>
                                            </select>
                                        </template>

                                        <template x-if="fieldOptions(selectedNode, field).length === 0 && isArrayField(selectedNode, field)">
                                            <input type="text"
                                                :value="arrayConfigToInput(getConfigValue(selectedNode, field))"
                                                @input="setArrayConfigValue(selectedNode, field, $event.target.value)"
                                                :placeholder="arrayPlaceholder(selectedNode, field)"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-gray-900 dark:text-white">
                                        </template>

                                        <template x-if="fieldOptions(selectedNode, field).length === 0 && !isArrayField(selectedNode, field) && isLongTextField(selectedNode, field)">
                                            <textarea rows="3"
                                                :value="stringValue(getConfigValue(selectedNode, field))"
                                                @input="setConfigValue(selectedNode, field, $event.target.value)"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-gray-900 dark:text-white"></textarea>
                                        </template>

                                        <template x-if="fieldOptions(selectedNode, field).length === 0 && !isArrayField(selectedNode, field) && !isLongTextField(selectedNode, field)">
                                            <input :type="isIntegerField(selectedNode, field) ? 'number' : 'text'"
                                                :value="stringValue(getConfigValue(selectedNode, field))"
                                                @input="setConfigValue(selectedNode, field, isIntegerField(selectedNode, field) ? Number($event.target.value || 0) : $event.target.value)"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-gray-900 dark:text-white">
                                        </template>
                                    </div>
                                </template>
                            </div>

                            {{-- Connections --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Connections</label>
                                <div class="space-y-1">
                                    <template x-for="edge in edges.filter(e => e.source_node_id === selectedNode.node_id || e.target_node_id === selectedNode.node_id)" :key="edge.source_node_id + edge.target_node_id">
                                        <div class="flex items-center justify-between text-xs bg-gray-50 dark:bg-gray-700 rounded p-2">
                                            <span class="text-gray-600 dark:text-gray-400" x-text="edge.source_node_id + ' -> ' + edge.target_node_id"></span>
                                            <button @click="removeEdge(edge)" class="text-red-500 hover:text-red-700">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Remove Node --}}
                            <button @click="removeNode(selectedNode.node_id)"
                                class="w-full mt-4 px-4 py-2 text-sm font-medium text-red-700 bg-red-50 dark:bg-red-900/20 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition">
                                <i class="fa-solid fa-trash-can mr-2"></i> Remove Node
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </main>
    </div>

    <script>
    function workflowEditor() {
        return {
            nodes: @json($latestVersion?->nodes ?? []),
            edges: @json($latestVersion?->edges ?? []),
            catalog: @json($catalog),
            selectedNode: null,
            selectedPresetKey: '',
            saving: false,
            syncing: false,
            dirty: false,
            graphHash: @json($initialGraphHash),
            syncToken: @json($initialSyncToken),
            statusMessage: '',
            statusType: 'info',
            validationErrors: [],
            savePromise: null,
            syncIntervalHandle: null,

            // Drag state
            draggingNode: null,
            dragOffset: { x: 0, y: 0 },
            lastDragMoved: false,

            // Connection state
            connecting: false,
            connectSourceNode: null,
            connectStart: { x: 0, y: 0 },
            connectEnd: { x: 0, y: 0 },

            nodeCounter: 0,
            mouseMoveHandler: null,
            mouseUpHandler: null,

            init() {
                this.nodes = (this.nodes || []).map(n => this.normalizeNode(n));
                this.edges = (this.edges || []).map(e => this.normalizeEdge(e));
                this.nodeCounter = this.nodes.length;

                this.renderEdges();

                if (!this.mouseMoveHandler) {
                    this.mouseMoveHandler = (e) => {
                        if (this.draggingNode) {
                            const canvas = document.getElementById('workflow-canvas');
                            const rect = canvas.getBoundingClientRect();
                            this.draggingNode.position.x = Math.max(0, e.clientX - rect.left - this.dragOffset.x);
                            this.draggingNode.position.y = Math.max(0, e.clientY - rect.top - this.dragOffset.y);
                            this.lastDragMoved = true;
                            this.renderEdges();
                        }
                        if (this.connecting) {
                            const canvas = document.getElementById('workflow-canvas');
                            const rect = canvas.getBoundingClientRect();
                            this.connectEnd = { x: e.clientX - rect.left, y: e.clientY - rect.top };
                        }
                    };
                    document.addEventListener('mousemove', this.mouseMoveHandler);
                }

                if (!this.mouseUpHandler) {
                    this.mouseUpHandler = () => {
                        if (this.lastDragMoved) {
                            this.markDirty();
                        }
                        this.lastDragMoved = false;
                        this.draggingNode = null;
                        if (this.connecting) {
                            this.connecting = false;
                            this.connectSourceNode = null;
                        }
                    };
                    document.addEventListener('mouseup', this.mouseUpHandler);
                }

                if (!this.syncIntervalHandle) {
                    this.syncIntervalHandle = setInterval(() => this.syncFromServer(), 10000);
                }
            },

            normalizeNode(node) {
                return {
                    node_id: node.node_id,
                    type: node.type,
                    action_type: node.action_type,
                    label: node.label,
                    config: node.config || {},
                    position: node.position || { x: 100, y: 100 },
                };
            },

            normalizeEdge(edge) {
                return {
                    source_node_id: edge.source_node_id,
                    target_node_id: edge.target_node_id,
                    label: edge.label ?? null,
                    condition_branch: edge.condition_branch ?? null,
                };
            },

            markDirty() {
                this.dirty = true;
            },

            onDragStart(event, catalogNode) {
                event.dataTransfer.setData('application/json', JSON.stringify(catalogNode));
                event.dataTransfer.effectAllowed = 'copy';
            },

            onDrop(event) {
                event.preventDefault();
                const data = event.dataTransfer.getData('application/json');
                if (!data) return;

                const catalogNode = JSON.parse(data);
                const canvas = document.getElementById('workflow-canvas');
                const rect = canvas.getBoundingClientRect();

                this.nodeCounter++;
                const newNode = {
                    node_id: 'node_' + this.nodeCounter + '_' + Date.now(),
                    type: catalogNode.type,
                    action_type: catalogNode.action_type,
                    label: catalogNode.label,
                    config: this.buildDefaultConfig(catalogNode),
                    position: {
                        x: Math.max(10, event.clientX - rect.left - 80),
                        y: Math.max(10, event.clientY - rect.top - 30)
                    }
                };

                this.nodes.push(newNode);
                this.selectNode(newNode);
                this.markDirty();
                this.renderEdges();
            },

            selectNode(node) {
                this.selectedNode = node;
                if (!this.selectedNode.config) {
                    this.selectedNode.config = {};
                }
                this.syncSelectedPresetKey();
            },

            syncSelectedPresetKey() {
                if (!this.selectedNode) {
                    this.selectedPresetKey = '';
                    return;
                }
                const presets = this.getNodePresets(this.selectedNode);
                const matched = presets.find(preset => this.isEqualConfig(preset.config || {}, this.selectedNode.config || {}));
                this.selectedPresetKey = matched ? matched.key : '';
            },

            onTemplateChanged(node) {
                if (!node) return;
                const template = this.getCatalogNode(node.type, node.action_type);
                if (!template) return;

                node.label = template.label;
                node.config = this.buildDefaultConfig(template);
                this.selectedPresetKey = template.default_preset || '';
                if (this.selectedPresetKey) {
                    this.applyPresetToSelected(this.selectedPresetKey);
                }
                this.markDirty();
            },

            applyPresetToSelected(presetKey) {
                if (!this.selectedNode) return;
                const preset = this.getNodePresets(this.selectedNode).find(item => item.key === presetKey);
                if (!preset) {
                    this.markDirty();
                    return;
                }
                this.selectedNode.config = {
                    ...(this.selectedNode.config || {}),
                    ...(preset.config || {}),
                };
                this.markDirty();
            },

            getNodeTypeOptions(type) {
                return this.catalog[(type || '') + 's'] || [];
            },

            getCatalogNode(type, actionType) {
                const group = this.getNodeTypeOptions(type);
                return group.find(item => item.action_type === actionType) || null;
            },

            getNodePresets(node) {
                const template = this.getCatalogNode(node.type, node.action_type);
                return template?.presets || [];
            },

            getConfigSchema(node) {
                const template = this.getCatalogNode(node.type, node.action_type);
                return template?.config_schema || {};
            },

            fieldOptions(node, field) {
                const template = this.getCatalogNode(node.type, node.action_type);
                if (this.isArrayField(node, field)) {
                    return [];
                }
                return template?.ui?.[field] || [];
            },

            ruleForField(node, field) {
                return this.getConfigSchema(node)[field] || '';
            },

            isArrayField(node, field) {
                return String(this.ruleForField(node, field)).split('|').includes('array');
            },

            isIntegerField(node, field) {
                return String(this.ruleForField(node, field)).split('|').includes('integer');
            },

            isLongTextField(node, field) {
                const name = String(field || '').toLowerCase();
                return name.includes('message') || name.includes('reason') || name.includes('description');
            },

            getConfigValue(node, field) {
                return (node.config || {})[field];
            },

            setConfigValue(node, field, value) {
                if (!node.config) {
                    node.config = {};
                }
                node.config[field] = value;
                this.markDirty();
                this.syncSelectedPresetKey();
            },

            setArrayConfigValue(node, field, inputValue) {
                if (!node.config) {
                    node.config = {};
                }
                const values = String(inputValue || '')
                    .split(',')
                    .map(item => item.trim())
                    .filter(item => item.length > 0);
                node.config[field] = values.every(item => /^-?\d+$/.test(item))
                    ? values.map(item => Number(item))
                    : values;
                this.markDirty();
                this.syncSelectedPresetKey();
            },

            arrayConfigToInput(value) {
                if (!Array.isArray(value)) return '';
                return value.join(',');
            },

            arrayPlaceholder(node, field) {
                if (field === 'branch_ids') return '1,2,3';
                if (field === 'categories') return 'vaccine,antibiotic';
                return 'a,b,c';
            },

            formatFieldLabel(field) {
                return String(field || '')
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, char => char.toUpperCase());
            },

            stringValue(value) {
                if (value === null || typeof value === 'undefined') {
                    return '';
                }
                return String(value);
            },

            buildDefaultConfig(catalogNode) {
                const defaults = {};
                const schema = catalogNode?.config_schema || {};
                const ui = catalogNode?.ui || {};
                const defaultPreset = (catalogNode?.presets || []).find(item => item.key === catalogNode.default_preset);
                if (defaultPreset?.config) {
                    Object.assign(defaults, defaultPreset.config);
                }

                Object.keys(schema).forEach((field) => {
                    if (Object.prototype.hasOwnProperty.call(defaults, field)) return;
                    const rule = String(schema[field] || '');
                    if (rule.split('|').includes('array')) {
                        defaults[field] = [];
                    } else if (rule.split('|').includes('integer')) {
                        defaults[field] = 0;
                    } else if (Array.isArray(ui[field]) && ui[field].length > 0) {
                        defaults[field] = ui[field][0];
                    } else {
                        defaults[field] = '';
                    }
                });

                return defaults;
            },

            isEqualConfig(left, right) {
                return JSON.stringify(left || {}) === JSON.stringify(right || {});
            },

            rebindSelectedNode() {
                if (!this.selectedNode) return;
                const selectedId = this.selectedNode.node_id;
                this.selectedNode = this.nodes.find(node => node.node_id === selectedId) || null;
                this.syncSelectedPresetKey();
            },

            startDragNode(event, node) {
                if (event.target.closest('[title="Drag to connect"]') || event.target.closest('button')) return;
                const canvas = document.getElementById('workflow-canvas');
                const rect = canvas.getBoundingClientRect();
                this.dragOffset = {
                    x: event.clientX - rect.left - (node.position?.x || 0),
                    y: event.clientY - rect.top - (node.position?.y || 0)
                };
                this.lastDragMoved = false;
                this.draggingNode = node;
            },

            startConnect(event, node) {
                this.connecting = true;
                this.connectSourceNode = node;
                const canvas = document.getElementById('workflow-canvas');
                const rect = canvas.getBoundingClientRect();
                const center = this.getNodeCenter(node.node_id);
                this.connectStart = center;
                this.connectEnd = { x: event.clientX - rect.left, y: event.clientY - rect.top };
            },

            endConnect(targetNode) {
                if (this.connecting && this.connectSourceNode && this.connectSourceNode.node_id !== targetNode.node_id) {
                    // Check for duplicate edge
                    const exists = this.edges.find(e =>
                        e.source_node_id === this.connectSourceNode.node_id &&
                        e.target_node_id === targetNode.node_id
                    );
                    if (!exists) {
                        this.edges.push({
                            source_node_id: this.connectSourceNode.node_id,
                            target_node_id: targetNode.node_id,
                            label: null,
                            condition_branch: null
                        });
                        this.markDirty();
                        this.renderEdges();
                    }
                }
                this.connecting = false;
                this.connectSourceNode = null;
            },

            getNodeCenter(nodeId) {
                const node = this.nodes.find(n => n.node_id === nodeId);
                if (!node || !node.position) return { x: 0, y: 0 };
                return {
                    x: (node.position.x || 0) + 80,
                    y: (node.position.y || 0) + 30
                };
            },

            removeNode(nodeId) {
                this.nodes = this.nodes.filter(n => n.node_id !== nodeId);
                this.edges = this.edges.filter(e => e.source_node_id !== nodeId && e.target_node_id !== nodeId);
                if (this.selectedNode && this.selectedNode.node_id === nodeId) {
                    this.selectedNode = null;
                    this.selectedPresetKey = '';
                }
                this.markDirty();
                this.renderEdges();
            },

            removeEdge(edge) {
                this.edges = this.edges.filter(e =>
                    !(e.source_node_id === edge.source_node_id && e.target_node_id === edge.target_node_id)
                );
                this.markDirty();
                this.renderEdges();
            },

            renderEdges() {
                if (!this.$refs?.edgesLayer) return;

                const lines = this.edges.map((edge) => {
                    const source = this.getNodeCenter(edge.source_node_id);
                    const target = this.getNodeCenter(edge.target_node_id);

                    const valid =
                        Number.isFinite(source.x) &&
                        Number.isFinite(source.y) &&
                        Number.isFinite(target.x) &&
                        Number.isFinite(target.y);

                    if (!valid) return '';

                    return `<line x1="${source.x}" y1="${source.y}" x2="${target.x}" y2="${target.y}" stroke="#6b7280" stroke-width="2" marker-end="url(#arrowhead)"></line>`;
                }).join('');

                this.$refs.edgesLayer.innerHTML = lines;
            },

            buildPayload() {
                return {
                    nodes: this.nodes.map(n => ({
                        node_id: n.node_id,
                        type: n.type,
                        action_type: n.action_type,
                        label: n.label,
                        config: n.config || {},
                        position: n.position || { x: 100, y: 100 },
                    })),
                    edges: this.edges.map(e => ({
                        source_node_id: e.source_node_id,
                        target_node_id: e.target_node_id,
                        label: e.label,
                        condition_branch: e.condition_branch,
                    })),
                };
            },

            buildTriggerPayload() {
                const payload = {};
                const triggerNodes = this.nodes.filter(node => node.type === 'trigger');

                if (triggerNodes.length > 0) {
                    const primaryTrigger = triggerNodes[0];
                    payload.trigger_type = primaryTrigger.action_type;

                    if (primaryTrigger.config && typeof primaryTrigger.config === 'object') {
                        Object.assign(payload, primaryTrigger.config);
                    }
                }

                return payload;
            },

            async requestJson(url, options = {}) {
                const response = await fetch(url, options);
                let data = {};
                try {
                    data = await response.json();
                } catch (error) {
                    data = {};
                }

                if (!response.ok) {
                    const err = new Error(data.message || data.error || `Request failed (${response.status})`);
                    err.payload = data;
                    throw err;
                }

                return data;
            },

            async saveGraph({ silent = false } = {}) {
                if (this.savePromise) {
                    return this.savePromise;
                }

                this.saving = true;
                if (!silent) {
                    this.statusMessage = '';
                    this.validationErrors = [];
                }

                const idempotencyKey = `save-${Date.now()}-${Math.random().toString(36).slice(2)}`;
                this.savePromise = this.requestJson('{{ route("admin.workflows.save-graph", $workflow) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Idempotency-Key': idempotencyKey,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.buildPayload()),
                }).then((data) => {
                    this.graphHash = data.graph_hash || this.graphHash;
                    this.syncToken = data.sync_token || this.syncToken;
                    if (data.version) {
                        this.nodes = (data.version.nodes || []).map(n => this.normalizeNode(n));
                        this.edges = (data.version.edges || []).map(e => this.normalizeEdge(e));
                        this.rebindSelectedNode();
                        this.renderEdges();
                    }
                    this.dirty = false;
                    if (!silent) {
                        this.statusMessage = 'Workflow saved successfully.';
                        this.statusType = 'success';
                    }
                    return data;
                }).catch((err) => {
                    if (err.payload?.errors) {
                        this.validationErrors = err.payload.errors;
                    }
                    this.statusMessage = err.message || 'Failed to save workflow.';
                    this.statusType = 'error';
                    throw err;
                }).finally(() => {
                    this.saving = false;
                    this.savePromise = null;
                });

                return this.savePromise;
            },

            async validateWorkflow() {
                this.validationErrors = [];
                try {
                    await this.saveGraph({ silent: true });
                    const data = await this.requestJson('{{ route("admin.workflows.validate", $workflow) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });
                    if (data.valid) {
                        this.statusMessage = 'Workflow is valid.';
                        this.statusType = 'success';
                    } else {
                        this.validationErrors = data.errors || [];
                        this.statusMessage = 'Validation failed.';
                        this.statusType = 'error';
                    }
                } catch (err) {
                    if (err.payload?.errors) {
                        this.validationErrors = err.payload.errors;
                    }
                    this.statusMessage = err.message || 'Validation error.';
                    this.statusType = 'error';
                }
            },

            async publishWorkflow() {
                if (!confirm('Publish this workflow? It will become active.')) return;
                try {
                    await this.saveGraph({ silent: true });
                    await this.requestJson('{{ route("admin.workflows.publish", $workflow) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });
                    this.statusMessage = 'Workflow published successfully.';
                    this.statusType = 'success';
                    setTimeout(() => location.reload(), 1000);
                } catch (err) {
                    if (err.payload?.errors) {
                        this.validationErrors = err.payload.errors;
                    }
                    this.statusMessage = err.message || 'Publish failed.';
                    this.statusType = 'error';
                }
            },

            async runWorkflow(dryRun) {
                try {
                    const idempotencyKey = `run-${Date.now()}-${Math.random().toString(36).slice(2)}`;
                    const data = await this.requestJson('{{ route("admin.workflows.run", $workflow) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Idempotency-Key': idempotencyKey,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ dry_run: dryRun, trigger_payload: this.buildTriggerPayload() })
                    });
                    this.statusMessage = (dryRun ? 'Dry run' : 'Run') + ' completed: ' + data.run.status;
                    this.statusType = data.run.status === 'completed' ? 'success' : 'error';
                } catch (err) {
                    this.statusMessage = err.message || 'Run failed.';
                    this.statusType = 'error';
                }
            },

            async syncFromServer() {
                if (this.saving || this.dirty || this.syncing) {
                    return;
                }

                this.syncing = true;
                try {
                    const params = this.syncToken ? '?since=' + encodeURIComponent(this.syncToken) : '';
                    const data = await this.requestJson(`{{ route('admin.workflows.graph-state', $workflow) }}${params}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    if (!data.changed) {
                        this.syncToken = data.sync_token || this.syncToken;
                        return;
                    }

                    if (data.version) {
                        this.nodes = (data.version.nodes || []).map(n => this.normalizeNode(n));
                        this.edges = (data.version.edges || []).map(e => this.normalizeEdge(e));
                        this.rebindSelectedNode();
                        this.renderEdges();
                    }

                    this.graphHash = data.graph_hash || this.graphHash;
                    this.syncToken = data.sync_token || this.syncToken;
                } catch (err) {
                    // Keep polling silent for transient network issues.
                } finally {
                    this.syncing = false;
                }
            }
        };
    }
    </script>
</x-app-layout>
