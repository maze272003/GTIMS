<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen" x-data="workflowEditor()">

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
                    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div class="lg:hidden">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Canvas</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Swipe the page for panels, or pan inside the canvas to review a larger graph.</p>
                        </div>
                        <div class="hidden lg:flex items-center gap-1.5">
                            <button type="button" @click="zoomIn()"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition text-sm" title="Zoom In">
                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                            </button>
                            <button type="button" @click="zoomOut()"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition text-sm" title="Zoom Out">
                                <i class="fa-solid fa-magnifying-glass-minus"></i>
                            </button>
                            <button type="button" @click="resetZoom()"
                                class="inline-flex items-center justify-center px-2 h-8 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition text-xs font-medium" title="Reset Zoom">
                                <span x-text="Math.round(zoomLevel * 100) + '%'"></span>
                            </button>
                        </div>
                        <button type="button" @click="showMobilePanel('palette')"
                            class="inline-flex items-center rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-purple-700 bg-purple-50 hover:bg-purple-100 transition dark:bg-purple-900/30 dark:text-purple-300">
                            <i class="fa-solid fa-plus mr-1"></i> Nodes
                        </button>
                    </div>

                    <div x-ref="canvasViewport" class="relative flex-1 overflow-auto"
                         style="-webkit-overflow-scrolling: touch;"
                         @wheel.prevent="handleWheelZoom($event)"
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
                                    <marker id="arrowhead-suggestion" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
                                        <polygon points="0 0, 10 3.5, 0 7" fill="#d1d5db" opacity="0.45"/>
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

                                        <template x-if="fieldOptions(selectedNode, field).length === 0 && isArrayField(selectedNode, field) && isBranchField(field)">
                                            <div>
                                                <div class="space-y-1 max-h-40 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700">
                                                    <template x-for="branch in branches" :key="'branch-chk-' + branch.id">
                                                        <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-600 rounded px-1 py-0.5">
                                                            <input type="checkbox"
                                                                :checked="(getConfigValue(selectedNode, field) || []).map(Number).includes(branch.id)"
                                                                @change="toggleBranchSelection(selectedNode, field, branch.id)"
                                                                class="rounded border-gray-300 dark:border-gray-600 text-red-600 focus:ring-red-500">
                                                            <span class="text-gray-700 dark:text-gray-300" x-text="branch.name"></span>
                                                            <span class="text-[10px] text-gray-400" x-text="branch.is_main ? '(Main)' : ''"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                                <p class="text-[10px] text-gray-400 mt-1" x-text="(getConfigValue(selectedNode, field) || []).length + ' branch(es) selected'"></p>
                                            </div>
                                        </template>

                                        <template x-if="fieldOptions(selectedNode, field).length === 0 && isArrayField(selectedNode, field) && !isBranchField(field)">
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

                                        <template x-if="fieldOptions(selectedNode, field).length === 0 && !isArrayField(selectedNode, field) && !isLongTextField(selectedNode, field) && isSingleBranchField(field)">
                                            <select :value="stringValue(getConfigValue(selectedNode, field))"
                                                @change="setConfigValue(selectedNode, field, Number($event.target.value || 0))"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-gray-900 dark:text-white">
                                                <option value="">— Select Branch —</option>
                                                <template x-for="branch in branches" :key="'sbranch-' + branch.id">
                                                    <option :value="branch.id" x-text="branch.name + (branch.is_main ? ' (Main)' : '')"></option>
                                                </template>
                                            </select>
                                        </template>

                                        <template x-if="fieldOptions(selectedNode, field).length === 0 && !isArrayField(selectedNode, field) && !isLongTextField(selectedNode, field) && !isSingleBranchField(field)">
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
                                            <span class="break-all text-gray-600 dark:text-gray-400" x-text="getNodeLabel(edge.source_node_id) + ' → ' + getNodeLabel(edge.target_node_id)"></span>
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
    @php
        $workflowEditorConfig = [
            'nodes' => $latestVersion?->nodes ?? [],
            'edges' => $latestVersion?->edges ?? [],
            'catalog' => $catalog,
            'branches' => $branches ?? [],
            'initialGraphHash' => $initialGraphHash,
            'initialSyncToken' => $initialSyncToken,
            'urls' => [
                'saveGraph' => route('admin.workflows.save-graph', $workflow),
                'validate' => route('admin.workflows.validate', $workflow),
                'publish' => route('admin.workflows.publish', $workflow),
                'run' => route('admin.workflows.run', $workflow),
                'graphState' => route('admin.workflows.graph-state', $workflow),
                'versions' => route('admin.workflows.versions', $workflow),
                'rollbackBase' => url('admin/workflows/' . $workflow->id . '/versions'),
            ],
        ];
    @endphp
    <script id="workflow-editor-config" type="application/json">@json($workflowEditorConfig)</script>
</x-app-layout>
