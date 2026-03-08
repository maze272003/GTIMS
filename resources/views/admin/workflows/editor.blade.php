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
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Version {{ $workflow->current_version }}
                            <button @click="showVersionPanel = !showVersionPanel"
                                class="ml-2 text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 text-xs font-medium">
                                <i class="fa-solid fa-clock-rotate-left mr-0.5"></i> History
                            </button>
                        </p>
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

            {{-- Version History Panel (collapsible) --}}
            <div x-show="showVersionPanel" x-cloak x-transition
                 class="mb-4 bg-purple-50 dark:bg-purple-900/10 rounded-xl border border-purple-200 dark:border-purple-800 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-purple-200 dark:border-purple-800">
                    <h3 class="text-sm font-bold text-purple-800 dark:text-purple-300"><i class="fa-solid fa-clock-rotate-left mr-1"></i> Version History</h3>
                    <button @click="showVersionPanel = false" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times text-xs"></i></button>
                </div>
                <div class="p-4 max-h-64 overflow-y-auto" x-init="$watch('showVersionPanel', v => { if(v) loadVersionHistory(); })">
                    <template x-if="loadingVersions">
                        <div class="text-center py-4 text-gray-500"><i class="fa-solid fa-spinner fa-spin"></i></div>
                    </template>
                    <template x-if="!loadingVersions && versionHistory.length === 0">
                        <p class="text-center text-sm text-gray-500 py-4">No versions found.</p>
                    </template>
                    <div class="space-y-2">
                        <template x-for="v in versionHistory" :key="v.id">
                            <div class="flex flex-col gap-2 p-3 rounded-lg border text-xs sm:flex-row sm:items-center sm:justify-between"
                                 :class="v.status === 'published' ? 'bg-green-50 dark:bg-green-900/10 border-green-300 dark:border-green-700' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                                <div>
                                    <span class="font-bold text-gray-800 dark:text-gray-100" x-text="'v' + v.version_number"></span>
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium"
                                          :class="v.status === 'published' ? 'bg-green-100 text-green-700' : v.status === 'archived' ? 'bg-gray-100 text-gray-600' : 'bg-yellow-100 text-yellow-700'"
                                          x-text="v.status"></span>
                                    <span class="ml-2 text-gray-400" x-text="(v.nodes_count || 0) + ' nodes'"></span>
                                    <template x-if="v.change_summary">
                                        <p class="text-gray-500 mt-0.5" x-text="v.change_summary"></p>
                                    </template>
                                </div>
                                <template x-if="v.status !== 'published'">
                                    <button @click="rollbackToVersion(v.id, v.version_number)"
                                        class="inline-flex items-center px-2 py-1 text-[10px] font-medium text-orange-700 bg-orange-50 rounded hover:bg-orange-100 transition">
                                        <i class="fa-solid fa-rotate-left mr-0.5"></i> Rollback
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="mb-4 lg:hidden">
                <div class="rounded-xl border border-gray-200 bg-white/90 p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800/90">
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" @click="showMobilePanel('palette')"
                            class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold transition"
                            :class="mobilePanel === 'palette'
                                ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'">
                            <i class="fa-solid fa-shapes mr-1.5"></i> Palette
                        </button>
                        <button type="button" @click="showMobilePanel('canvas')"
                            class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold transition"
                            :class="mobilePanel === 'canvas'
                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'">
                            <i class="fa-solid fa-diagram-project mr-1.5"></i> Canvas
                        </button>
                        <button type="button" @click="showMobilePanel('inspector')"
                            class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold transition"
                            :class="mobilePanel === 'inspector'
                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'">
                            <i class="fa-solid fa-sliders mr-1.5"></i> Inspector
                        </button>
                    </div>
                    <p class="mt-3 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        Swipe sideways to switch panels. Tap the <span class="font-semibold">+</span> buttons to add nodes, drag cards in the canvas to move them, and tap a connector then another node to link them.
                    </p>
                </div>
            </div>

            {{-- Editor Layout: Palette + Canvas + Inspector --}}
            <div x-ref="editorPanels" @scroll.passive="syncMobilePanelFromScroll()"
                class="flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory lg:overflow-visible lg:pb-0 lg:snap-none"
                style="height: calc(100vh - 280px); min-height: 420px; scroll-behavior: smooth; -webkit-overflow-scrolling: touch;">

                {{-- Node Palette (Left Panel) --}}
                <div x-ref="palettePanel"
                    class="w-full flex-none snap-start bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-y-auto lg:w-72">
                    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Node Palette</h3>
                            <button type="button" @click="showMobilePanel('canvas')"
                                class="inline-flex items-center rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-red-700 bg-red-50 hover:bg-red-100 transition dark:bg-red-900/30 dark:text-red-300 lg:hidden">
                                <i class="fa-solid fa-arrow-right mr-1"></i> Canvas
                            </button>
                        </div>
                    </div>

                    {{-- Compatibility Guide --}}
                    <div class="p-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Compatibility Guide</h4>

                        <template x-if="!activeGuideTriggerType">
                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                Drop or select a trigger to highlight compatible conditions and actions.
                            </p>
                        </template>

                        <template x-if="activeGuideTriggerType">
                            <div class="space-y-3">
                                <div class="text-xs text-gray-700 dark:text-gray-300">
                                    <span class="font-semibold">Active Trigger:</span>
                                    <span x-text="activeGuideTriggerLabel()"></span>
                                </div>

                                <template x-if="availableGuideTriggers().length > 1">
                                    <div>
                                        <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Trigger Context</label>
                                        <select x-model="activeGuideTriggerType" @change="refreshCompatibilityGuide(activeGuideTriggerType)"
                                            class="w-full px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 text-gray-900 dark:text-white">
                                            <template x-for="item in availableGuideTriggers()" :key="'guide-' + item.action_type">
                                                <option :value="item.action_type" x-text="item.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>

                                <div>
                                    <p class="text-[10px] font-semibold text-amber-700 dark:text-amber-300 uppercase mb-1">Suggested Conditions</p>
                                    <template x-if="compatibleNodes('conditions').length === 0">
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">No mapped condition suggestions.</p>
                                    </template>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="item in compatibleNodes('conditions')" :key="'cond-suggest-' + item.action_type">
                                            <button type="button" @click="addSuggestedNode(item)"
                                                class="inline-flex items-center px-2 py-1 rounded-md bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-[11px] font-medium hover:bg-amber-200 dark:hover:bg-amber-900/60 transition">
                                                <i class="fa-solid fa-plus mr-1 text-[10px]"></i>
                                                <span x-text="item.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-[10px] font-semibold text-blue-700 dark:text-blue-300 uppercase mb-1">Suggested Actions</p>
                                    <template x-if="compatibleNodes('actions').length === 0">
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">No mapped action suggestions.</p>
                                    </template>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="item in compatibleNodes('actions')" :key="'action-suggest-' + item.action_type">
                                            <button type="button" @click="addSuggestedNode(item)"
                                                class="inline-flex items-center px-2 py-1 rounded-md bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 text-[11px] font-medium hover:bg-blue-200 dark:hover:bg-blue-900/60 transition">
                                                <i class="fa-solid fa-plus mr-1 text-[10px]"></i>
                                                <span x-text="item.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Triggers --}}
                    <div class="p-3">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Triggers</h4>
                        <template x-for="node in catalog.triggers" :key="node.action_type">
                            <div class="flex items-center gap-2 p-2 mb-1.5 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg cursor-grab text-sm hover:shadow-sm transition"
                                 :class="triggerPaletteClass(node)"
                                  draggable="true" @dragstart="onDragStart($event, node)"
                                  :aria-label="'Drag to add ' + node.label + ' trigger'">
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <i class="fa-solid fa-bolt text-purple-600 dark:text-purple-400 w-4"></i>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium" x-text="node.label"></span>
                                </div>
                                <button type="button" @click.stop="addNodeFromPalette(node)"
                                    class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-white/90 text-purple-700 shadow-sm ring-1 ring-purple-200 transition hover:bg-white dark:bg-gray-800 dark:text-purple-300 dark:ring-purple-700"
                                    :aria-label="'Add ' + node.label + ' trigger'">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Conditions --}}
                    <div class="p-3 pt-0">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Conditions</h4>
                        <template x-for="node in catalog.conditions" :key="node.action_type">
                            <div class="flex items-center gap-2 p-2 mb-1.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg cursor-grab text-sm hover:shadow-sm transition"
                                 :class="paletteCompatibilityClass(node)"
                                  draggable="true" @dragstart="onDragStart($event, node)"
                                  :aria-label="'Drag to add ' + node.label + ' condition'">
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <i class="fa-solid fa-diamond text-amber-600 dark:text-amber-400 w-4"></i>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium" x-text="node.label"></span>
                                </div>
                                <span x-show="isCompatiblePaletteNode(node.type, node.action_type)"
                                    class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    Suggested
                                </span>
                                <button type="button" @click.stop="addNodeFromPalette(node)"
                                    class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-white/90 text-amber-700 shadow-sm ring-1 ring-amber-200 transition hover:bg-white dark:bg-gray-800 dark:text-amber-300 dark:ring-amber-700"
                                    :aria-label="'Add ' + node.label + ' condition'">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Actions --}}
                    <div class="p-3 pt-0">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Actions</h4>
                        <template x-for="node in catalog.actions" :key="node.action_type">
                            <div class="flex items-center gap-2 p-2 mb-1.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg cursor-grab text-sm hover:shadow-sm transition"
                                 :class="paletteCompatibilityClass(node)"
                                  draggable="true" @dragstart="onDragStart($event, node)"
                                  :aria-label="'Drag to add ' + node.label + ' action'">
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <i class="fa-solid fa-gear text-blue-600 dark:text-blue-400 w-4"></i>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium" x-text="node.label"></span>
                                </div>
                                <span x-show="isCompatiblePaletteNode(node.type, node.action_type)"
                                    class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    Suggested
                                </span>
                                <button type="button" @click.stop="addNodeFromPalette(node)"
                                    class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-white/90 text-blue-700 shadow-sm ring-1 ring-blue-200 transition hover:bg-white dark:bg-gray-800 dark:text-blue-300 dark:ring-blue-700"
                                    :aria-label="'Add ' + node.label + ' action'">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Canvas (Center) --}}
                <div x-ref="canvasPanel"
                    class="flex w-full flex-none snap-start flex-col bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden lg:w-auto lg:flex-1 lg:min-w-0">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700 lg:hidden">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Canvas</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Swipe the page for panels, or pan inside the canvas to review a larger graph.</p>
                        </div>
                        <button type="button" @click="showMobilePanel('palette')"
                            class="inline-flex items-center rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-purple-700 bg-purple-50 hover:bg-purple-100 transition dark:bg-purple-900/30 dark:text-purple-300">
                            <i class="fa-solid fa-plus mr-1"></i> Nodes
                        </button>
                    </div>

                    <div x-ref="canvasViewport" class="relative flex-1 overflow-auto"
                         style="-webkit-overflow-scrolling: touch;"
                         @dragover.prevent @drop="onDrop($event)"
                         id="workflow-canvas" role="application" aria-label="Workflow canvas - drag nodes here">
                        <div x-ref="canvasSurface" class="relative min-h-full min-w-full"
                             @click.self="cancelConnect()"
                             :style="workspaceStyle()">
                            {{-- Canvas Background Grid --}}
                            <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(circle, #e5e7eb 1px, transparent 1px); background-size: 20px 20px;" aria-hidden="true"></div>

                            {{-- Edges SVG Layer --}}
                            <svg class="absolute inset-0 pointer-events-none" :width="workspaceSize().width" :height="workspaceSize().height" style="z-index: 1;">
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
                                <div class="group absolute cursor-move select-none rounded-xl shadow-md border-2 transition-shadow"
                                     :data-node-id="node.node_id"
                                     :class="{
                                         'border-purple-400 bg-purple-50 dark:bg-purple-900/30': node.type === 'trigger',
                                         'border-amber-400 bg-amber-50 dark:bg-amber-900/30': node.type === 'condition',
                                         'border-blue-400 bg-blue-50 dark:bg-blue-900/30': node.type === 'action',
                                         'ring-2 ring-red-500': selectedNode && selectedNode.node_id === node.node_id
                                     }"
                                     :style="nodeStyle(node)"
                                     @pointerdown="startDragNode($event, node)"
                                     @click.stop="handleNodeTap(node)"
                                     :aria-label="node.label" role="button" tabindex="0"
                                     @keydown.enter.stop="selectNode(node)"
                                     @keydown.space.prevent.stop="selectNode(node)"
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
                                    <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-5 h-5 bg-gray-400 dark:bg-gray-500 rounded-full border-2 border-white dark:border-gray-800 cursor-crosshair hover:bg-red-500 transition"
                                         style="touch-action: none;"
                                         @pointerdown.stop="startConnect($event, node)"
                                         title="Drag to connect" aria-label="Connect from this node"></div>
                                    <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 w-5 h-5 bg-gray-400 dark:bg-gray-500 rounded-full border-2 border-white dark:border-gray-800 hover:bg-green-500 transition"
                                         style="touch-action: none;"
                                         @pointerdown.stop
                                         @pointerup.stop="endConnect(node)"
                                         title="Drop connection here" aria-label="Connect to this node"></div>

                                    {{-- Delete button --}}
                                    <button class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600 transition opacity-0 group-hover:opacity-100"
                                            :class="selectedNode && selectedNode.node_id === node.node_id ? 'opacity-100' : 'opacity-0 hover:opacity-100'"
                                            @click.stop="removeNode(node.node_id)" aria-label="Remove node">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </template>

                            {{-- Empty State --}}
                            <template x-if="nodes.length === 0">
                                <div class="absolute inset-0 flex items-center justify-center px-6" style="z-index: 5;">
                                    <div class="text-center max-w-xs">
                                        <i class="fa-regular fa-diagram-project text-5xl text-gray-300 dark:text-gray-600 mb-3"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">Build your workflow from the palette.</p>
                                        <p class="text-gray-400 dark:text-gray-500 text-sm mt-1 hidden sm:block">Drag nodes from the palette and connect them from the bottom handle to the top handle.</p>
                                        <p class="text-gray-400 dark:text-gray-500 text-sm mt-1 sm:hidden">Tap the <span class="font-semibold">+</span> buttons in the palette, then drag cards or tap a connector and another node to link them.</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Inspector Panel (Right) --}}
                <div x-ref="inspectorPanel"
                    class="w-full flex-none snap-start bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-y-auto lg:w-72">
                    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Inspector</h3>
                            <button type="button" @click="showMobilePanel('canvas')"
                                class="inline-flex items-center rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 transition dark:bg-blue-900/30 dark:text-blue-300 lg:hidden">
                                <i class="fa-solid fa-arrow-left mr-1"></i> Canvas
                            </button>
                        </div>
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
                                        <div class="flex items-center justify-between gap-2 text-xs bg-gray-50 dark:bg-gray-700 rounded p-2">
                                            <span class="break-all text-gray-600 dark:text-gray-400" x-text="edge.source_node_id + ' -> ' + edge.target_node_id"></span>
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
            showVersionPanel: false,
            versionHistory: [],
            loadingVersions: false,
            activeGuideTriggerType: null,
            mobilePanel: 'canvas',
            canvasViewportSize: { width: 0, height: 0 },
            compatibilityMap: {
                low_stock_reached: {
                    conditions: ['quantity_threshold', 'branch_matches', 'category_matches'],
                    actions: ['notify', 'create_reorder_suggestion', 'create_transfer_request', 'log_audit_event', 'create_hold'],
                },
                stock_received: {
                    conditions: ['branch_matches', 'category_matches', 'expiry_threshold'],
                    actions: ['notify', 'create_transfer_request', 'webhook_call', 'generate_report', 'log_audit_event'],
                },
                expiry_in_x_days: {
                    conditions: ['expiry_threshold', 'branch_matches'],
                    actions: ['create_hold', 'notify', 'generate_report', 'log_audit_event'],
                },
                order_created: {
                    conditions: ['category_matches', 'branch_matches', 'data_field_matches', 'user_has_permission'],
                    actions: ['notify', 'create_hold', 'create_transfer_request', 'sync_crm_erp', 'webhook_call', 'log_audit_event'],
                },
                order_approved: {
                    conditions: ['category_matches', 'branch_matches', 'quantity_threshold'],
                    actions: ['auto_allocate_order', 'notify', 'create_transfer_request', 'generate_report', 'log_audit_event'],
                },
                order_canceled: {
                    conditions: ['branch_matches', 'data_field_matches'],
                    actions: ['release_hold', 'notify', 'log_audit_event'],
                },
                daily_schedule: {
                    conditions: ['branch_matches', 'data_field_matches', 'sla_overdue'],
                    actions: ['generate_report', 'notify', 'sync_crm_erp', 'webhook_call', 'log_audit_event'],
                },
                employee_onboarding_started: {
                    conditions: ['data_field_matches', 'user_has_permission'],
                    actions: ['map_form_fields', 'sync_crm_erp', 'create_google_doc', 'notify', 'completion_gate'],
                },
                document_approval_requested: {
                    conditions: ['data_field_matches', 'sla_overdue', 'user_has_permission'],
                    actions: ['notify', 'escalate_overdue_task', 'log_audit_event', 'completion_gate'],
                },
                data_sync_requested: {
                    conditions: ['sync_status_matches', 'data_field_matches'],
                    actions: ['map_form_fields', 'sync_crm_erp', 'notify', 'escalate_overdue_task', 'completion_gate'],
                },
                it_service_ticket_created: {
                    conditions: ['sla_overdue', 'data_field_matches'],
                    actions: ['notify', 'escalate_overdue_task', 'create_google_doc', 'completion_gate'],
                },
                compliance_window_started: {
                    conditions: ['user_has_permission', 'data_field_matches'],
                    actions: ['generate_report', 'notify', 'sync_crm_erp', 'log_audit_event', 'completion_gate'],
                },
            },

            // Drag state
            draggingNode: null,
            dragOffset: { x: 0, y: 0 },
            lastDragMoved: false,

            // Connection state
            connecting: false,
            connectSourceNode: null,
            connectPointerType: null,
            connectStart: { x: 0, y: 0 },
            connectEnd: { x: 0, y: 0 },

            nodeCounter: 0,
            pointerMoveHandler: null,
            pointerUpHandler: null,
            resizeHandler: null,

            init() {
                this.nodes = (this.nodes || []).map(n => this.normalizeNode(n));
                this.edges = (this.edges || []).map(e => this.normalizeEdge(e));
                this.nodeCounter = this.nodes.length;

                this.refreshCompatibilityGuide();
                this.$nextTick(() => {
                    this.updateViewportMetrics();
                    this.showMobilePanel(this.mobilePanel, 'auto');
                    this.renderEdges();
                });

                if (!this.pointerMoveHandler) {
                    this.pointerMoveHandler = (e) => {
                        if (this.draggingNode) {
                            const point = this.clientToCanvasPosition(e.clientX, e.clientY);
                            const size = this.workspaceSize();
                            this.draggingNode.position.x = Math.max(0, Math.min(point.x - this.dragOffset.x, size.width - this.nodeCardWidth()));
                            this.draggingNode.position.y = Math.max(0, Math.min(point.y - this.dragOffset.y, size.height - this.nodeCardHeight()));
                            this.lastDragMoved = true;
                            this.renderEdges();
                        }
                        if (this.connecting) {
                            this.connectEnd = this.clientToCanvasPosition(e.clientX, e.clientY);
                        }
                    };
                    document.addEventListener('pointermove', this.pointerMoveHandler);
                }

                if (!this.pointerUpHandler) {
                    this.pointerUpHandler = () => {
                        if (this.lastDragMoved) {
                            this.markDirty();
                        }
                        this.lastDragMoved = false;
                        this.draggingNode = null;
                        if (this.connecting && this.connectPointerType !== 'touch') {
                            this.connecting = false;
                            this.connectSourceNode = null;
                            this.connectPointerType = null;
                        }
                    };
                    document.addEventListener('pointerup', this.pointerUpHandler);
                    document.addEventListener('pointercancel', this.pointerUpHandler);
                }

                if (!this.resizeHandler) {
                    this.resizeHandler = () => {
                        this.updateViewportMetrics();
                        this.showMobilePanel(this.mobilePanel, 'auto');
                        this.renderEdges();
                    };
                    window.addEventListener('resize', this.resizeHandler);
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

            isDesktopLayout() {
                return window.innerWidth >= 1024;
            },

            getCanvasViewport() {
                return this.$refs?.canvasViewport || document.getElementById('workflow-canvas');
            },

            getCanvasSurface() {
                return this.$refs?.canvasSurface || this.getCanvasViewport();
            },

            updateViewportMetrics() {
                const viewport = this.getCanvasViewport();
                this.canvasViewportSize = {
                    width: viewport?.clientWidth || 0,
                    height: viewport?.clientHeight || 0,
                };
            },

            nodeCardWidth() {
                return 176;
            },

            nodeCardHeight() {
                return 88;
            },

            workspaceSize() {
                const viewportWidth = this.canvasViewportSize.width || 0;
                const viewportHeight = this.canvasViewportSize.height || 0;
                const maxX = this.nodes.reduce((carry, node) => {
                    return Math.max(carry, (node.position?.x || 0) + this.nodeCardWidth() + 48);
                }, 0);
                const maxY = this.nodes.reduce((carry, node) => {
                    return Math.max(carry, (node.position?.y || 0) + this.nodeCardHeight() + 64);
                }, 0);

                return {
                    width: Math.max(viewportWidth, maxX, this.isDesktopLayout() ? 820 : 560),
                    height: Math.max(viewportHeight, maxY, 420),
                };
            },

            workspaceStyle() {
                const size = this.workspaceSize();
                return `width:${size.width}px; height:${size.height}px;`;
            },

            nodeStyle(node) {
                return `left:${node.position?.x || 100}px; top:${node.position?.y || 100}px; z-index:10; width:${this.nodeCardWidth()}px; touch-action:none;`;
            },

            clientToCanvasPosition(clientX, clientY) {
                const surface = this.getCanvasSurface();
                const rect = surface?.getBoundingClientRect();
                if (!rect) {
                    return { x: 0, y: 0 };
                }

                return {
                    x: Math.max(0, clientX - rect.left),
                    y: Math.max(0, clientY - rect.top),
                };
            },

            showMobilePanel(panel, behavior = 'smooth') {
                this.mobilePanel = panel;

                if (this.isDesktopLayout()) {
                    this.$nextTick(() => this.updateViewportMetrics());
                    return;
                }

                const refs = {
                    palette: this.$refs?.palettePanel,
                    canvas: this.$refs?.canvasPanel,
                    inspector: this.$refs?.inspectorPanel,
                };

                const target = refs[panel];
                if (!target) {
                    return;
                }

                target.scrollIntoView({
                    behavior,
                    block: 'nearest',
                    inline: 'start',
                });

                if (panel === 'canvas') {
                    this.$nextTick(() => {
                        this.updateViewportMetrics();
                        this.renderEdges();
                    });
                }
            },

            syncMobilePanelFromScroll() {
                if (this.isDesktopLayout() || !this.$refs?.editorPanels) {
                    return;
                }

                const scrollLeft = this.$refs.editorPanels.scrollLeft;
                const panels = [
                    ['palette', this.$refs?.palettePanel],
                    ['canvas', this.$refs?.canvasPanel],
                    ['inspector', this.$refs?.inspectorPanel],
                ].filter(([, element]) => element);

                const closestPanel = panels
                    .map(([name, element]) => ({
                        name,
                        distance: Math.abs((element.offsetLeft || 0) - scrollLeft),
                    }))
                    .sort((left, right) => left.distance - right.distance)[0];

                if (closestPanel) {
                    this.mobilePanel = closestPanel.name;
                }
            },

            availableGuideTriggers() {
                const types = Array.from(new Set(
                    this.nodes
                        .filter(node => node.type === 'trigger')
                        .map(node => node.action_type)
                        .filter(Boolean)
                ));

                return types.map(actionType => {
                    const catalogNode = this.getCatalogNode('trigger', actionType);
                    return {
                        action_type: actionType,
                        label: catalogNode?.label || actionType,
                    };
                });
            },

            activeGuideTriggerLabel() {
                if (!this.activeGuideTriggerType) return 'None';
                const item = this.availableGuideTriggers().find(trigger => trigger.action_type === this.activeGuideTriggerType);
                if (item) return item.label;
                const catalogNode = this.getCatalogNode('trigger', this.activeGuideTriggerType);
                return catalogNode?.label || this.activeGuideTriggerType;
            },

            refreshCompatibilityGuide(preferredTriggerType = null) {
                const availableTypes = this.availableGuideTriggers().map(item => item.action_type);
                if (availableTypes.length === 0) {
                    this.activeGuideTriggerType = null;
                    return;
                }

                if (preferredTriggerType && availableTypes.includes(preferredTriggerType)) {
                    this.activeGuideTriggerType = preferredTriggerType;
                    return;
                }

                if (this.selectedNode?.type === 'trigger' && availableTypes.includes(this.selectedNode.action_type)) {
                    this.activeGuideTriggerType = this.selectedNode.action_type;
                    return;
                }

                if (this.activeGuideTriggerType && availableTypes.includes(this.activeGuideTriggerType)) {
                    return;
                }

                this.activeGuideTriggerType = availableTypes[0];
            },

            compatibleNodes(groupKey) {
                if (!this.activeGuideTriggerType) return [];

                const mapEntry = this.compatibilityMap[this.activeGuideTriggerType];
                if (!mapEntry) return [];

                const group = this.catalog[groupKey] || [];
                const compatibleTypes = mapEntry[groupKey] || [];

                return compatibleTypes
                    .map(actionType => group.find(node => node.action_type === actionType))
                    .filter(Boolean);
            },

            isCompatiblePaletteNode(nodeType, actionType) {
                if (!this.activeGuideTriggerType) return false;
                const mapEntry = this.compatibilityMap[this.activeGuideTriggerType];
                if (!mapEntry) return false;

                if (nodeType === 'condition') {
                    return (mapEntry.conditions || []).includes(actionType);
                }
                if (nodeType === 'action') {
                    return (mapEntry.actions || []).includes(actionType);
                }

                return false;
            },

            paletteCompatibilityClass(node) {
                if (!this.activeGuideTriggerType) return '';
                if (!['condition', 'action'].includes(node.type)) return '';
                return this.isCompatiblePaletteNode(node.type, node.action_type)
                    ? 'ring-2 ring-emerald-300 dark:ring-emerald-700'
                    : 'opacity-45 saturate-75';
            },

            triggerPaletteClass(node) {
                if (!this.activeGuideTriggerType) return '';
                return node.action_type === this.activeGuideTriggerType
                    ? 'ring-2 ring-red-300 dark:ring-red-700'
                    : 'opacity-70';
            },

            createNodeFromCatalog(catalogNode, position = null) {
                this.nodeCounter++;
                return {
                    node_id: 'node_' + this.nodeCounter + '_' + Date.now(),
                    type: catalogNode.type,
                    action_type: catalogNode.action_type,
                    label: catalogNode.label,
                    config: this.buildDefaultConfig(catalogNode),
                    position: position || { x: 100, y: 100 },
                };
            },

            guideAnchorTriggerNode() {
                if (!this.activeGuideTriggerType) return null;
                const matches = this.nodes.filter(node =>
                    node.type === 'trigger' && node.action_type === this.activeGuideTriggerType
                );
                return matches.length > 0 ? matches[matches.length - 1] : null;
            },

            suggestedNodePosition(nodeType) {
                const anchor = this.guideAnchorTriggerNode();
                const baseX = anchor?.position?.x ?? 120;
                const baseY = anchor?.position?.y ?? 120;
                const sameTypeCount = this.nodes.filter(node => node.type === nodeType).length;
                if (!this.isDesktopLayout()) {
                    return {
                        x: 24,
                        y: Math.max(24, baseY + ((sameTypeCount + 1) * 104)),
                    };
                }
                const xOffset = nodeType === 'condition' ? 220 : 380;
                return {
                    x: Math.max(10, baseX + xOffset),
                    y: Math.max(10, baseY + ((sameTypeCount % 6) * 78)),
                };
            },

            nextPaletteInsertPosition(nodeType) {
                const viewport = this.getCanvasViewport();
                const baseX = (viewport?.scrollLeft || 0) + 24;
                const baseY = (viewport?.scrollTop || 0) + 24;
                const nodeCount = this.nodes.length;

                if (!this.isDesktopLayout()) {
                    return {
                        x: baseX,
                        y: baseY + (nodeCount * 104),
                    };
                }

                const xOffsets = {
                    trigger: 24,
                    condition: 280,
                    action: 536,
                };

                return {
                    x: baseX + (xOffsets[nodeType] ?? 24),
                    y: baseY + ((this.nodes.filter(node => node.type === nodeType).length % 5) * 92),
                };
            },

            scrollNodeIntoView(node) {
                const viewport = this.getCanvasViewport();
                if (!viewport || !node?.position) {
                    return;
                }

                const left = Math.max(0, node.position.x - 24);
                const top = Math.max(0, node.position.y - 24);

                viewport.scrollTo({
                    left,
                    top,
                    behavior: 'smooth',
                });
            },

            addNodeFromPalette(catalogNode) {
                if (!catalogNode) return;

                const newNode = this.createNodeFromCatalog(catalogNode, this.nextPaletteInsertPosition(catalogNode.type));
                this.nodes.push(newNode);
                this.selectNode(newNode);
                this.markDirty();
                this.showMobilePanel('canvas');
                this.$nextTick(() => {
                    this.updateViewportMetrics();
                    this.renderEdges();
                    this.scrollNodeIntoView(newNode);
                });
            },

            addSuggestedNode(catalogNode) {
                if (!catalogNode) return;
                const newNode = this.createNodeFromCatalog(catalogNode, this.suggestedNodePosition(catalogNode.type));
                this.nodes.push(newNode);
                this.selectNode(newNode);
                this.markDirty();
                this.showMobilePanel('canvas');
                this.$nextTick(() => {
                    this.updateViewportMetrics();
                    this.renderEdges();
                    this.scrollNodeIntoView(newNode);
                });
            },

            markDirty() {
                this.dirty = true;
            },

            setStatus(message, type = 'info') {
                this.statusMessage = message || '';
                this.statusType = type || 'info';
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
                const point = this.clientToCanvasPosition(event.clientX, event.clientY);

                const newNode = this.createNodeFromCatalog(catalogNode, {
                    x: Math.max(10, point.x - (this.nodeCardWidth() / 2)),
                    y: Math.max(10, point.y - 36)
                });

                this.nodes.push(newNode);
                this.selectNode(newNode);
                this.markDirty();
                this.$nextTick(() => {
                    this.updateViewportMetrics();
                    this.renderEdges();
                    this.scrollNodeIntoView(newNode);
                });
            },

            selectNode(node) {
                this.selectedNode = node;
                if (!this.selectedNode.config) {
                    this.selectedNode.config = {};
                }
                this.syncSelectedPresetKey();
                if (this.selectedNode.type === 'trigger') {
                    this.refreshCompatibilityGuide(this.selectedNode.action_type);
                } else {
                    this.refreshCompatibilityGuide();
                }
            },

            handleNodeTap(node) {
                if (this.connecting && this.connectSourceNode) {
                    if (this.connectSourceNode.node_id === node.node_id) {
                        this.cancelConnect();
                        return;
                    }

                    this.endConnect(node);
                    this.selectNode(node);
                    return;
                }

                this.selectNode(node);
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
                if (node.type === 'trigger') {
                    this.refreshCompatibilityGuide(node.action_type);
                } else {
                    this.refreshCompatibilityGuide();
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
                if (this.selectedNode) {
                    const selectedId = this.selectedNode.node_id;
                    this.selectedNode = this.nodes.find(node => node.node_id === selectedId) || null;
                }
                this.syncSelectedPresetKey();
                this.refreshCompatibilityGuide();
                this.$nextTick(() => {
                    this.updateViewportMetrics();
                    this.renderEdges();
                });
            },

            startDragNode(event, node) {
                if (
                    event.target.closest('[aria-label="Connect from this node"]') ||
                    event.target.closest('[aria-label="Connect to this node"]') ||
                    event.target.closest('button')
                ) return;
                event.preventDefault();
                const point = this.clientToCanvasPosition(event.clientX, event.clientY);
                this.dragOffset = {
                    x: point.x - (node.position?.x || 0),
                    y: point.y - (node.position?.y || 0)
                };
                this.lastDragMoved = false;
                this.draggingNode = node;
            },

            startConnect(event, node) {
                event.preventDefault();
                this.connecting = true;
                this.connectSourceNode = node;
                this.connectPointerType = event.pointerType || 'mouse';
                const center = this.getNodeCenter(node.node_id);
                this.connectStart = center;
                this.connectEnd = this.clientToCanvasPosition(event.clientX, event.clientY);
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
                        this.$nextTick(() => this.renderEdges());
                    }
                }
                this.connectPointerType = null;
                this.connecting = false;
                this.connectSourceNode = null;
            },

            cancelConnect() {
                this.connecting = false;
                this.connectSourceNode = null;
                this.connectPointerType = null;
            },

            getNodeCenter(nodeId) {
                const node = this.nodes.find(n => n.node_id === nodeId);
                if (!node || !node.position) return { x: 0, y: 0 };

                const surface = this.getCanvasSurface();
                const element = surface?.querySelector(`[data-node-id="${nodeId}"]`);
                if (surface && element) {
                    const surfaceRect = surface.getBoundingClientRect();
                    const rect = element.getBoundingClientRect();
                    return {
                        x: (rect.left - surfaceRect.left) + (rect.width / 2),
                        y: (rect.top - surfaceRect.top) + (rect.height / 2),
                    };
                }

                return {
                    x: (node.position.x || 0) + (this.nodeCardWidth() / 2),
                    y: (node.position.y || 0) + (this.nodeCardHeight() / 2),
                };
            },

            removeNode(nodeId) {
                this.nodes = this.nodes.filter(n => n.node_id !== nodeId);
                this.edges = this.edges.filter(e => e.source_node_id !== nodeId && e.target_node_id !== nodeId);
                if (this.selectedNode && this.selectedNode.node_id === nodeId) {
                    this.selectedNode = null;
                    this.selectedPresetKey = '';
                }
                this.refreshCompatibilityGuide();
                this.markDirty();
                this.$nextTick(() => {
                    this.updateViewportMetrics();
                    this.renderEdges();
                });
            },

            removeEdge(edge) {
                this.edges = this.edges.filter(e =>
                    !(e.source_node_id === edge.source_node_id && e.target_node_id === edge.target_node_id)
                );
                this.markDirty();
                this.$nextTick(() => this.renderEdges());
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
                    }

                    this.graphHash = data.graph_hash || this.graphHash;
                    this.syncToken = data.sync_token || this.syncToken;
                } catch (err) {
                    // Keep polling silent for transient network issues.
                } finally {
                    this.syncing = false;
                }
            },

            async loadVersionHistory() {
                this.loadingVersions = true;
                try {
                    const data = await this.requestJson('{{ route("admin.workflows.versions", $workflow) }}', {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                    });
                    this.versionHistory = data.versions || [];
                } catch (e) {
                    console.error('Failed to load version history', e);
                } finally {
                    this.loadingVersions = false;
                }
            },

            async rollbackToVersion(versionId, versionNumber) {
                const confirmed = typeof Swal !== 'undefined'
                    ? (await Swal.fire({
                        title: 'Rollback to v' + versionNumber + '?',
                        text: 'A new version will be created. Current published version will be archived.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Rollback',
                        confirmButtonColor: '#ea580c'
                    })).isConfirmed
                    : confirm('Rollback to v' + versionNumber + '?');

                if (!confirmed) return;

                try {
                    const data = await this.requestJson(`/admin/workflows/{{ $workflow->id }}/versions/${versionId}/rollback`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                    });

                    if (data.success) {
                        this.setStatus('Rolled back successfully. Reloading...', 'success');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        this.setStatus(data.error || 'Rollback failed.', 'error');
                    }
                } catch (e) {
                    this.setStatus('Rollback failed: ' + e.message, 'error');
                }
            }
        };
    }
    </script>
</x-app-layout>
