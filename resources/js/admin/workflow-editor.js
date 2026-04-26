const CONFIG_ELEMENT_ID = 'workflow-editor-config';

function readWorkflowEditorConfig() {
    const element = document.getElementById(CONFIG_ELEMENT_ID);
    if (!element) {
        return {};
    }

    try {
        return JSON.parse(element.textContent || '{}');
    } catch (error) {
        console.error('Failed to parse workflow editor config', error);
        return {};
    }
}

function createWorkflowEditor() {
    const initialConfig = readWorkflowEditorConfig();

    return {
        nodes: [],
        edges: [],
        catalog: {
            triggers: [],
            conditions: [],
            actions: [],
        },
        urls: {},
        branches: [],
        inspectorOptions: {
            products: [],
            users: [],
            userLevels: [],
            permissions: [],
        },
        selectedNode: null,
        selectedPresetKey: '',
        saving: false,
        syncing: false,
        dirty: false,
        graphHash: null,
        syncToken: null,
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
        zoomLevel: 1,
        zoomMin: 0.25,
        zoomMax: 2,
        zoomStep: 0.1,
        pinchStartDistance: null,
        pinchStartZoom: null,
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

        draggingNode: null,
        dragOffset: { x: 0, y: 0 },
        lastDragMoved: false,

        connecting: false,
        connectSourceNode: null,
        connectPointerType: null,
        connectStart: { x: 0, y: 0 },
        connectEnd: { x: 0, y: 0 },

        nodeCounter: 0,
        pointerMoveHandler: null,
        pointerUpHandler: null,
        resizeHandler: null,
        edgeRenderFrame: null,

        init() {
            this.applyConfig(Object.keys(initialConfig).length > 0 ? initialConfig : readWorkflowEditorConfig());

            this.refreshCompatibilityGuide();
            this.$nextTick(() => {
                this.updateViewportMetrics();
                this.showMobilePanel(this.mobilePanel, 'auto');
                this.renderEdges();
                this.setupPinchZoom();
            });

            if (!this.pointerMoveHandler) {
                this.pointerMoveHandler = (event) => {
                    if (this.draggingNode) {
                        const point = this.clientToCanvasPosition(event.clientX, event.clientY);
                        const size = this.workspaceSize();
                        this.draggingNode.position.x = Math.max(0, Math.min(point.x - this.dragOffset.x, size.width - this.nodeCardWidth()));
                        this.draggingNode.position.y = Math.max(0, Math.min(point.y - this.dragOffset.y, size.height - this.nodeCardHeight()));
                        this.lastDragMoved = true;
                        this.renderEdges();
                    }

                    if (this.connecting) {
                        this.connectEnd = this.clientToCanvasPosition(event.clientX, event.clientY);
                        this.renderEdges();
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
                        this.cancelConnect();
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

        applyConfig(config = {}) {
            this.catalog = {
                triggers: config.catalog?.triggers || [],
                conditions: config.catalog?.conditions || [],
                actions: config.catalog?.actions || [],
            };
            this.urls = config.urls || {};
            this.branches = config.branches || [];
            this.inspectorOptions = {
                products: config.inspectorOptions?.products || [],
                users: config.inspectorOptions?.users || [],
                userLevels: config.inspectorOptions?.userLevels || [],
                permissions: config.inspectorOptions?.permissions || [],
            };
            this.graphHash = config.initialGraphHash ?? null;
            this.syncToken = config.initialSyncToken ?? null;
            this.nodes = (config.nodes || []).map((node) => this.normalizeNode(node));
            this.edges = (config.edges || []).map((edge) => this.normalizeEdge(edge));
            this.nodeCounter = this.nodes.length;
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

            return `width:${size.width}px; height:${size.height}px; transform: scale(${this.zoomLevel}); transform-origin: 0 0;`;
        },

        nodeStyle(node) {
            return `left:${node.position?.x || 100}px; top:${node.position?.y || 100}px; z-index:10; width:${this.nodeCardWidth()}px; touch-action:none;`;
        },

        clientToCanvasPosition(clientX, clientY) {
            const viewport = this.getCanvasViewport();
            const surface = this.getCanvasSurface();
            const rect = surface?.getBoundingClientRect();

            if (!rect) {
                return { x: 0, y: 0 };
            }

            const scrollLeft = viewport?.scrollLeft || 0;
            const scrollTop = viewport?.scrollTop || 0;
            const viewportRect = viewport?.getBoundingClientRect();
            const offsetX = clientX - (viewportRect?.left || 0) + scrollLeft;
            const offsetY = clientY - (viewportRect?.top || 0) + scrollTop;

            return {
                x: Math.max(0, offsetX / this.zoomLevel),
                y: Math.max(0, offsetY / this.zoomLevel),
            };
        },

        handleWheelZoom(event) {
            if (event.ctrlKey || event.metaKey) {
                const delta = event.deltaY > 0 ? -this.zoomStep : this.zoomStep;
                this.setZoom(this.zoomLevel + delta);
            } else {
                const viewport = this.getCanvasViewport();
                if (viewport) {
                    viewport.scrollTop += event.deltaY;
                    viewport.scrollLeft += event.deltaX;
                }
            }
        },

        setupPinchZoom() {
            const viewport = this.getCanvasViewport();
            if (!viewport) {
                return;
            }

            viewport.addEventListener('touchstart', (event) => {
                if (event.touches.length === 2) {
                    event.preventDefault();
                    const dx = event.touches[0].clientX - event.touches[1].clientX;
                    const dy = event.touches[0].clientY - event.touches[1].clientY;
                    this.pinchStartDistance = Math.hypot(dx, dy);
                    this.pinchStartZoom = this.zoomLevel;
                }
            }, { passive: false });

            viewport.addEventListener('touchmove', (event) => {
                if (event.touches.length === 2 && this.pinchStartDistance !== null) {
                    event.preventDefault();
                    const dx = event.touches[0].clientX - event.touches[1].clientX;
                    const dy = event.touches[0].clientY - event.touches[1].clientY;
                    const distance = Math.hypot(dx, dy);
                    const scale = distance / this.pinchStartDistance;
                    this.setZoom(this.pinchStartZoom * scale);
                }
            }, { passive: false });

            viewport.addEventListener('touchend', () => {
                this.pinchStartDistance = null;
                this.pinchStartZoom = null;
            });
        },

        setZoom(level) {
            const clamped = Math.max(this.zoomMin, Math.min(this.zoomMax, level));
            this.zoomLevel = Math.round(clamped * 100) / 100;
            this.$nextTick(() => this.renderEdges());
        },

        zoomIn() {
            this.setZoom(this.zoomLevel + this.zoomStep);
        },

        zoomOut() {
            this.setZoom(this.zoomLevel - this.zoomStep);
        },

        resetZoom() {
            this.setZoom(1);
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
                    .filter((node) => node.type === 'trigger')
                    .map((node) => node.action_type)
                    .filter(Boolean),
            ));

            return types.map((actionType) => {
                const catalogNode = this.getCatalogNode('trigger', actionType);

                return {
                    action_type: actionType,
                    label: catalogNode?.label || actionType,
                };
            });
        },

        activeGuideTriggerLabel() {
            if (!this.activeGuideTriggerType) {
                return 'None';
            }

            const item = this.availableGuideTriggers().find((trigger) => trigger.action_type === this.activeGuideTriggerType);
            if (item) {
                return item.label;
            }

            const catalogNode = this.getCatalogNode('trigger', this.activeGuideTriggerType);

            return catalogNode?.label || this.activeGuideTriggerType;
        },

        refreshCompatibilityGuide(preferredTriggerType = null) {
            const availableTypes = this.availableGuideTriggers().map((item) => item.action_type);
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
            if (!this.activeGuideTriggerType) {
                return [];
            }

            const mapEntry = this.compatibilityMap[this.activeGuideTriggerType];
            if (!mapEntry) {
                return [];
            }

            const group = this.catalog[groupKey] || [];
            const compatibleTypes = mapEntry[groupKey] || [];

            return compatibleTypes
                .map((actionType) => group.find((node) => node.action_type === actionType))
                .filter(Boolean);
        },

        isCompatiblePaletteNode(nodeType, actionType) {
            if (!this.activeGuideTriggerType) {
                return false;
            }

            const mapEntry = this.compatibilityMap[this.activeGuideTriggerType];
            if (!mapEntry) {
                return false;
            }

            if (nodeType === 'condition') {
                return (mapEntry.conditions || []).includes(actionType);
            }

            if (nodeType === 'action') {
                return (mapEntry.actions || []).includes(actionType);
            }

            return false;
        },

        paletteCompatibilityClass(node) {
            if (!this.activeGuideTriggerType) {
                return '';
            }

            if (!['condition', 'action'].includes(node.type)) {
                return '';
            }

            return this.isCompatiblePaletteNode(node.type, node.action_type)
                ? 'ring-2 ring-emerald-300 dark:ring-emerald-700'
                : 'opacity-45 saturate-75';
        },

        triggerPaletteClass(node) {
            if (!this.activeGuideTriggerType) {
                return '';
            }

            return node.action_type === this.activeGuideTriggerType
                ? 'ring-2 ring-red-300 dark:ring-red-700'
                : 'opacity-70';
        },

        createNodeFromCatalog(catalogNode, position = null) {
            this.nodeCounter++;

            return {
                node_id: `node_${this.nodeCounter}_${Date.now()}`,
                type: catalogNode.type,
                action_type: catalogNode.action_type,
                label: catalogNode.label,
                config: this.buildDefaultConfig(catalogNode),
                position: position || { x: 100, y: 100 },
            };
        },

        guideAnchorTriggerNode() {
            if (!this.activeGuideTriggerType) {
                return null;
            }

            const matches = this.nodes.filter((node) => node.type === 'trigger' && node.action_type === this.activeGuideTriggerType);

            return matches.length > 0 ? matches[matches.length - 1] : null;
        },

        suggestedNodePosition(nodeType) {
            const anchor = this.guideAnchorTriggerNode();
            const baseX = anchor?.position?.x ?? 120;
            const baseY = anchor?.position?.y ?? 120;
            const sameTypeCount = this.nodes.filter((node) => node.type === nodeType).length;

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
                y: baseY + ((this.nodes.filter((node) => node.type === nodeType).length % 5) * 92),
            };
        },

        scrollNodeIntoView(node) {
            const viewport = this.getCanvasViewport();
            if (!viewport || !node?.position) {
                return;
            }

            viewport.scrollTo({
                left: Math.max(0, node.position.x - 24),
                top: Math.max(0, node.position.y - 24),
                behavior: 'smooth',
            });
        },

        addNodeFromPalette(catalogNode) {
            if (!catalogNode) {
                return;
            }

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
            if (!catalogNode) {
                return;
            }

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

            if (!data) {
                return;
            }

            const catalogNode = JSON.parse(data);
            const point = this.clientToCanvasPosition(event.clientX, event.clientY);
            const newNode = this.createNodeFromCatalog(catalogNode, {
                x: Math.max(10, point.x - (this.nodeCardWidth() / 2)),
                y: Math.max(10, point.y - 36),
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
            const matched = presets.find((preset) => this.isEqualConfig(preset.config || {}, this.selectedNode.config || {}));
            this.selectedPresetKey = matched ? matched.key : '';
        },

        onTemplateChanged(node) {
            if (!node) {
                return;
            }

            const template = this.getCatalogNode(node.type, node.action_type);
            if (!template) {
                return;
            }

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
            if (!this.selectedNode) {
                return;
            }

            const preset = this.getNodePresets(this.selectedNode).find((item) => item.key === presetKey);
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
            return this.catalog[`${type || ''}s`] || [];
        },

        getCatalogNode(type, actionType) {
            const group = this.getNodeTypeOptions(type);
            return group.find((item) => item.action_type === actionType) || null;
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
            const explicitUiValues = Array.isArray(template?.ui?.[field]) ? template.ui[field] : [];

            return this.mergeOptionSets(
                this.optionObjectsFromValues(explicitUiValues),
                this.fieldSpecificOptions(node, field),
                this.optionObjectsFromValues(this.presetFieldValues(node, field)),
                this.optionObjectsFromValues(this.currentFieldValues(node, field)),
            );
        },

        optionObjectsFromValues(values) {
            if (!Array.isArray(values)) {
                return [];
            }

            return values
                .filter((value) => value !== null && typeof value !== 'undefined' && String(value).trim() !== '')
                .map((value) => ({
                    value,
                    label: this.formatOptionLabel(value),
                }));
        },

        mergeOptionSets(...sets) {
            const merged = [];
            const seen = new Set();

            sets.forEach((set) => {
                (set || []).forEach((option) => {
                    if (!option || typeof option !== 'object') {
                        return;
                    }

                    const value = Object.prototype.hasOwnProperty.call(option, 'value') ? option.value : option.label;
                    const label = option.label ?? this.formatOptionLabel(value);
                    const key = `${typeof value}:${String(value)}`;

                    if (seen.has(key)) {
                        return;
                    }

                    seen.add(key);
                    merged.push({
                        value,
                        label,
                    });
                });
            });

            return merged;
        },

        formatOptionLabel(value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }

            if (typeof value === 'string') {
                return value
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (char) => char.toUpperCase());
            }

            return String(value);
        },

        presetFieldValues(node, field) {
            const values = [];

            this.getNodePresets(node).forEach((preset) => {
                const presetValue = preset?.config?.[field];
                if (Array.isArray(presetValue)) {
                    presetValue.forEach((value) => values.push(value));
                    return;
                }

                if (presetValue !== null && typeof presetValue !== 'undefined' && String(presetValue).trim() !== '') {
                    values.push(presetValue);
                }
            });

            return values;
        },

        currentFieldValues(node, field) {
            const currentValue = this.getConfigValue(node, field);
            if (Array.isArray(currentValue)) {
                return currentValue;
            }

            if (currentValue === null || typeof currentValue === 'undefined' || String(currentValue).trim() === '') {
                return [];
            }

            return [currentValue];
        },

        fieldSpecificOptions(node, field) {
            if (this.isBranchField(field) || this.isSingleBranchField(field)) {
                return this.branchSelectOptions();
            }

            if (['product_id'].includes(field)) {
                return (this.inspectorOptions.products || []).map((product) => ({
                    value: product.id,
                    label: product.label,
                }));
            }

            if (['employee_id', 'auditor_user_id'].includes(field)) {
                return this.userSelectOptions();
            }

            if (['recipient_user_ids'].includes(field)) {
                return this.userSelectOptions();
            }

            if (['recipient_level_ids'].includes(field)) {
                return (this.inspectorOptions.userLevels || []).map((level) => ({
                    value: level.id,
                    label: level.label,
                }));
            }

            if (['permission', 'recipient_permissions'].includes(field)) {
                return (this.inspectorOptions.permissions || []).map((permission) => ({
                    value: permission.value,
                    label: permission.label,
                }));
            }

            if (['recipient_emails', 'share_emails'].includes(field)) {
                return this.userEmailOptions();
            }

            if (['user_id_field', 'recipient_context_user_field'].includes(field)) {
                return this.optionObjectsFromValues(this.contextUserFieldOptions());
            }

            if (field === 'reference_time_field') {
                return this.optionObjectsFromValues(this.timestampFieldOptions());
            }

            if (field === 'field') {
                return this.optionObjectsFromValues(this.contextFieldOptions());
            }

            if (field === 'value' && node.action_type === 'data_field_matches') {
                return this.optionObjectsFromValues(this.dataFieldMatchValueOptions(node));
            }

            if (field === 'department') {
                return this.optionObjectsFromValues(['Administration', 'Compliance', 'Finance', 'HR', 'IT', 'Inventory', 'Pharmacy', 'Procurement']);
            }

            if (field === 'document_type') {
                return this.optionObjectsFromValues(['policy', 'memo', 'report', 'request_form', 'contract']);
            }

            if (field === 'source_system') {
                return this.optionObjectsFromValues(['crm', 'erp', 'gtims', 'external_api']);
            }

            if (field === 'entity_type') {
                return this.optionObjectsFromValues(['customer', 'document', 'employee', 'inventory', 'order']);
            }

            if (field === 'ticket_priority') {
                return this.optionObjectsFromValues(['low', 'normal', 'high', 'critical']);
            }

            if (field === 'window_name') {
                return this.optionObjectsFromValues(['monthly', 'quarterly', 'annual']);
            }

            if (field === 'field_mappings') {
                return this.optionObjectsFromValues([
                    'employee_name:crm_contact_name',
                    'department:erp_department',
                    'email:crm_primary_email',
                    'order_id:erp_order_id',
                    'product_id:erp_product_id',
                    'branch_id:erp_branch_id',
                ]);
            }

            if (field === 'folder_id') {
                return this.optionObjectsFromValues(['workflow_archive', 'hr_documents', 'it_documents', 'compliance_archive']);
            }

            if (field === 'crm_endpoint') {
                return this.optionObjectsFromValues([
                    'https://crm.example.com/api/customers',
                    'https://crm.example.com/api/employees',
                ]);
            }

            if (field === 'erp_endpoint') {
                return this.optionObjectsFromValues([
                    'https://erp.example.com/api/inventory',
                    'https://erp.example.com/api/orders',
                ]);
            }

            if (field === 'event_type') {
                return this.optionObjectsFromValues(['workflow_automation', 'compliance', 'audit', 'notification']);
            }

            if (this.isBooleanToggleField(field)) {
                return [
                    { value: 1, label: 'Yes' },
                    { value: 0, label: 'No' },
                ];
            }

            if (this.isIntegerField(node, field)) {
                return this.optionObjectsFromValues(this.numericFieldOptions(node, field));
            }

            return [];
        },

        branchSelectOptions() {
            return (this.branches || []).map((branch) => ({
                value: branch.id,
                label: `${branch.name}${branch.is_main ? ' (Main)' : ''}`,
            }));
        },

        userSelectOptions() {
            return (this.inspectorOptions.users || []).map((user) => ({
                value: user.id,
                label: user.label,
            }));
        },

        userEmailOptions() {
            return (this.inspectorOptions.users || [])
                .filter((user) => user.email)
                .map((user) => ({
                    value: user.email,
                    label: `${user.email}${user.name ? ` (${user.name})` : ''}`,
                }));
        },

        contextFieldOptions() {
            return [
                'available_qty',
                'branch_id',
                'category',
                'department',
                'document_type',
                'entity_type',
                'expiry_date',
                'order_id',
                'product_id',
                'quantity',
                'source_system',
                'status',
                'sync_status',
                'ticket_priority',
                'user_id',
                'window_name',
            ];
        },

        contextUserFieldOptions() {
            return ['user_id', 'auditor_user_id', 'employee_id', 'requester_user_id', 'triggered_by'];
        },

        timestampFieldOptions() {
            return ['created_at', 'requested_at', 'scheduled_at', 'updated_at'];
        },

        dataFieldMatchValueOptions(node) {
            const selectedField = String(this.getConfigValue(node, 'field') || '').trim();

            switch (selectedField) {
                case 'branch_id':
                    return this.branches.map((branch) => branch.id);
                case 'category':
                    return ['vaccine', 'antibiotic', 'analgesic', 'consumable', 'pharmaceuticals', 'office_supplies'];
                case 'status':
                    return ['pending', 'approved', 'cancelled', 'completed'];
                case 'sync_status':
                    return ['queued', 'synced', 'failed'];
                case 'department':
                    return ['Administration', 'Compliance', 'Finance', 'HR', 'IT', 'Inventory', 'Pharmacy', 'Procurement'];
                case 'document_type':
                    return ['policy', 'memo', 'report', 'request_form', 'contract'];
                case 'entity_type':
                    return ['customer', 'document', 'employee', 'inventory', 'order'];
                case 'source_system':
                    return ['crm', 'erp', 'gtims', 'external_api'];
                case 'ticket_priority':
                    return ['low', 'normal', 'high', 'critical'];
                case 'user_id':
                    return (this.inspectorOptions.users || []).map((user) => user.id);
                case 'window_name':
                    return ['monthly', 'quarterly', 'annual'];
                default:
                    return [];
            }
        },

        numericFieldOptions(node, field) {
            const presetValues = this.presetFieldValues(node, field)
                .filter((value) => /^-?\d+$/.test(String(value)))
                .map((value) => Number(value));

            const commonValuesByField = {
                threshold: [3, 5, 10, 15, 25, 50],
                days: [1, 3, 7, 15, 30, 60, 90],
                value: [0, 1, 3, 5, 10, 25, 50, 100],
                quantity: [10, 25, 50, 100, 250, 500],
                minutes: [15, 30, 60, 120, 240],
                approval_tier: [1, 2, 3, 4, 5],
                require_notifications: [1, 0],
                require_error_resolution: [1, 0],
                fail_on_error: [1, 0],
                recipient_match_context_branch: [1, 0],
                include_trigger_user: [1, 0],
            };

            const values = [...(commonValuesByField[field] || []), ...presetValues];
            return Array.from(new Set(values)).sort((left, right) => left - right);
        },

        isBooleanToggleField(field) {
            return [
                'fail_on_error',
                'include_trigger_user',
                'recipient_match_context_branch',
                'require_error_resolution',
                'require_notifications',
            ].includes(field);
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

        selectedOptionValue(node, field) {
            const value = this.getConfigValue(node, field);
            if (value === null || typeof value === 'undefined' || String(value).trim() === '') {
                return '';
            }

            return String(value);
        },

        selectedOptionValues(node, field) {
            const value = this.getConfigValue(node, field);
            if (!Array.isArray(value)) {
                return [];
            }

            return value.map((item) => String(item));
        },

        setSelectConfigValue(node, field, rawValue) {
            const normalized = String(rawValue || '').trim();

            if (normalized === '') {
                this.setConfigValue(node, field, '');
                return;
            }

            this.setConfigValue(node, field, this.normalizeOptionValue(node, field, normalized));
        },

        setMultiSelectConfigValue(node, field, rawValues) {
            if (!node.config) {
                node.config = {};
            }

            const normalizedValues = (Array.isArray(rawValues) ? rawValues : [])
                .map((value) => String(value || '').trim())
                .filter((value) => value !== '')
                .map((value) => this.normalizeOptionValue(node, field, value));

            node.config[field] = normalizedValues;
            this.markDirty();
            this.syncSelectedPresetKey();
        },

        normalizeOptionValue(node, field, rawValue) {
            if (this.fieldStoresNumericValues(node, field) && /^-?\d+$/.test(String(rawValue))) {
                return Number(rawValue);
            }

            return rawValue;
        },

        fieldStoresNumericValues(node, field) {
            if (this.isIntegerField(node, field)) {
                return true;
            }

            return [
                'branch_id',
                'branch_ids',
                'employee_id',
                'product_id',
                'recipient_branch_ids',
                'recipient_level_ids',
                'recipient_user_ids',
                'target_branch_id',
                'auditor_user_id',
            ].includes(field);
        },

        emptyOptionLabel(node, field) {
            return this.isRequiredField(node, field)
                ? `Select ${this.formatFieldLabel(field)}`
                : `Default ${this.formatFieldLabel(field)}`;
        },

        multiSelectSize(node, field) {
            const optionCount = this.fieldOptions(node, field).length;
            const minSize = this.isRequiredField(node, field) ? 4 : 3;
            return Math.max(minSize, Math.min(optionCount || minSize, 6));
        },

        fieldControlClass(node, field) {
            const invalid = this.isFieldInvalid(node, field);

            return [
                'w-full px-3 py-2 text-sm rounded-lg border transition',
                'dark:bg-gray-700 text-gray-900 dark:text-white',
                invalid
                    ? 'border-red-500 bg-red-50 dark:bg-red-900/20 dark:border-red-500 focus:ring-2 focus:ring-red-300'
                    : 'border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-red-500',
            ].join(' ');
        },

        isRequiredField(node, field) {
            const rules = String(this.ruleForField(node, field)).split('|');
            return rules.includes('required') && !rules.includes('optional');
        },

        isFieldInvalid(node, field) {
            return this.fieldValidationMessage(node, field) !== '';
        },

        fieldValidationMessage(node, field) {
            const label = this.formatFieldLabel(field);
            const options = this.fieldOptions(node, field);

            if (this.isArrayField(node, field)) {
                const selectedValues = Array.isArray(this.getConfigValue(node, field)) ? this.getConfigValue(node, field) : [];
                if (this.isRequiredField(node, field) && selectedValues.length === 0) {
                    return `${label} is required.`;
                }

                if (selectedValues.length === 0) {
                    return '';
                }

                if (options.length === 0) {
                    return `No dropdown choices are available for ${label}.`;
                }

                const allowedValues = new Set(options.map((option) => String(option.value)));
                if (selectedValues.some((value) => !allowedValues.has(String(value)))) {
                    return `${label} has an invalid selection.`;
                }

                return '';
            }

            const selectedValue = this.selectedOptionValue(node, field);
            if (this.isRequiredField(node, field) && selectedValue === '') {
                return `${label} is required.`;
            }

            if (selectedValue === '') {
                return '';
            }

            if (options.length === 0) {
                return `No dropdown choices are available for ${label}.`;
            }

            if (!options.some((option) => String(option.value) === selectedValue)) {
                return `${label} has an invalid selection.`;
            }

            return '';
        },

        collectInspectorValidationErrors() {
            const errors = [];

            this.nodes.forEach((node) => {
                Object.keys(this.getConfigSchema(node)).forEach((field) => {
                    const message = this.fieldValidationMessage(node, field);
                    if (message) {
                        errors.push(`${node.label}: ${message}`);
                    }
                });
            });

            return Array.from(new Set(errors));
        },

        findFirstInvalidInspectorField() {
            for (const node of this.nodes) {
                for (const field of Object.keys(this.getConfigSchema(node))) {
                    const message = this.fieldValidationMessage(node, field);
                    if (message) {
                        return { node, field, message };
                    }
                }
            }

            return null;
        },

        ensureInspectorValidation(actionLabel = 'saving') {
            const errors = this.collectInspectorValidationErrors();
            this.validationErrors = errors;

            if (errors.length === 0) {
                return true;
            }

            const firstInvalid = this.findFirstInvalidInspectorField();
            if (firstInvalid?.node) {
                this.selectNode(firstInvalid.node);
            }

            this.statusMessage = `Inspector validation failed. Fix the red dropdown fields before ${actionLabel}.`;
            this.statusType = 'error';

            return false;
        },

        setArrayConfigValue(node, field, inputValue) {
            if (!node.config) {
                node.config = {};
            }

            const values = String(inputValue || '')
                .split(',')
                .map((item) => item.trim())
                .filter((item) => item.length > 0);

            node.config[field] = values.every((item) => /^-?\d+$/.test(item))
                ? values.map((item) => Number(item))
                : values;
            this.markDirty();
            this.syncSelectedPresetKey();
        },

        arrayConfigToInput(value) {
            if (!Array.isArray(value)) {
                return '';
            }

            return value.join(',');
        },

        arrayPlaceholder(node, field) {
            if (field === 'branch_ids') {
                return 'Use checkboxes to select branches';
            }

            if (field === 'categories') {
                return 'vaccine,antibiotic';
            }

            return 'a,b,c';
        },

        isBranchField(field) {
            return ['branch_ids', 'recipient_branch_ids'].includes(field);
        },

        isSingleBranchField(field) {
            return ['branch_id', 'target_branch_id'].includes(field);
        },

        toggleBranchSelection(node, field, branchId) {
            if (!node.config) {
                node.config = {};
            }

            const current = Array.isArray(node.config[field]) ? [...node.config[field]] : [];
            const index = current.map(Number).indexOf(Number(branchId));

            if (index >= 0) {
                current.splice(index, 1);
            } else {
                current.push(Number(branchId));
            }

            node.config[field] = current;
            this.markDirty();
            this.syncSelectedPresetKey();
        },

        getBranchName(branchId) {
            const branch = this.branches.find((b) => b.id === Number(branchId));
            return branch ? branch.name : `Branch #${branchId}`;
        },

        getNodeLabel(nodeId) {
            const node = this.nodes.find((n) => n.node_id === nodeId);
            return node ? node.label : nodeId;
        },

        formatFieldLabel(field) {
            return String(field || '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (char) => char.toUpperCase());
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
            const defaultPreset = (catalogNode?.presets || []).find((item) => item.key === catalogNode.default_preset);

            if (defaultPreset?.config) {
                Object.assign(defaults, defaultPreset.config);
            }

            Object.keys(schema).forEach((field) => {
                if (Object.prototype.hasOwnProperty.call(defaults, field)) {
                    return;
                }

                const rule = String(schema[field] || '');

                if (rule.split('|').includes('array')) {
                    defaults[field] = [];
                } else if (this.isBooleanToggleField(field)) {
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
                this.selectedNode = this.nodes.find((node) => node.node_id === selectedId) || null;
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
            ) {
                return;
            }

            event.preventDefault();
            const point = this.clientToCanvasPosition(event.clientX, event.clientY);
            this.dragOffset = {
                x: point.x - (node.position?.x || 0),
                y: point.y - (node.position?.y || 0),
            };
            this.lastDragMoved = false;
            this.draggingNode = node;
        },

        startConnect(event, node) {
            event.preventDefault();
            this.connecting = true;
            this.connectSourceNode = node;
            this.connectPointerType = event.pointerType || 'mouse';
            this.connectStart = this.getNodeCenter(node.node_id);
            this.connectEnd = this.clientToCanvasPosition(event.clientX, event.clientY);
            this.renderEdges();
        },

        endConnect(targetNode) {
            if (this.connecting && this.connectSourceNode && this.connectSourceNode.node_id !== targetNode.node_id) {
                const exists = this.edges.find((edge) =>
                    edge.source_node_id === this.connectSourceNode.node_id
                    && edge.target_node_id === targetNode.node_id,
                );

                if (!exists) {
                    const compatibility = this.checkConnectionCompatibility(this.connectSourceNode, targetNode);
                    if (!compatibility.allowed) {
                        this.showToast(compatibility.reason, 'error');
                        this.cancelConnect();
                        return;
                    }

                    this.edges.push({
                        source_node_id: this.connectSourceNode.node_id,
                        target_node_id: targetNode.node_id,
                        label: null,
                        condition_branch: null,
                    });
                    this.markDirty();
                    this.$nextTick(() => this.renderEdges());
                }
            }

            this.cancelConnect();
        },

        checkConnectionCompatibility(sourceNode, targetNode) {
            if (sourceNode.type === 'trigger' && targetNode.type === 'trigger') {
                return { allowed: false, reason: `Cannot connect trigger "${sourceNode.label}" to trigger "${targetNode.label}".` };
            }

            if (sourceNode.type === 'action' && targetNode.type === 'trigger') {
                return { allowed: false, reason: `Action "${sourceNode.label}" cannot connect back to trigger "${targetNode.label}".` };
            }

            if (sourceNode.type === 'condition' && targetNode.type === 'trigger') {
                return { allowed: false, reason: `Condition "${sourceNode.label}" cannot connect back to trigger "${targetNode.label}".` };
            }

            if (sourceNode.type === 'trigger' && this.activeGuideTriggerType) {
                const mapEntry = this.compatibilityMap[sourceNode.action_type];
                if (mapEntry) {
                    if (targetNode.type === 'condition' && !(mapEntry.conditions || []).includes(targetNode.action_type)) {
                        return { allowed: false, reason: `Condition "${targetNode.label}" is not compatible with trigger "${sourceNode.label}".` };
                    }
                    if (targetNode.type === 'action' && !(mapEntry.actions || []).includes(targetNode.action_type)) {
                        return { allowed: false, reason: `Action "${targetNode.label}" is not compatible with trigger "${sourceNode.label}".` };
                    }
                }
            }

            return { allowed: true, reason: '' };
        },

        showToast(message, type = 'info') {
            if (typeof window.Swal !== 'undefined') {
                window.Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: type === 'error' ? 'error' : 'info',
                    title: message,
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
            } else {
                this.statusMessage = message;
                this.statusType = type;
                setTimeout(() => { if (this.statusMessage === message) { this.statusMessage = ''; } }, 4000);
            }
        },

        cancelConnect() {
            this.connecting = false;
            this.connectSourceNode = null;
            this.connectPointerType = null;
            this.renderEdges();
        },

        getNodeCenter(nodeId) {
            const node = this.nodes.find((item) => item.node_id === nodeId);
            if (!node || !node.position) {
                return { x: 0, y: 0 };
            }

            return {
                x: (node.position.x || 0) + (this.nodeCardWidth() / 2),
                y: (node.position.y || 0) + (this.nodeCardHeight() / 2),
            };
        },

        removeNode(nodeId) {
            this.nodes = this.nodes.filter((node) => node.node_id !== nodeId);
            this.edges = this.edges.filter((edge) => edge.source_node_id !== nodeId && edge.target_node_id !== nodeId);

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
            this.edges = this.edges.filter((item) =>
                !(item.source_node_id === edge.source_node_id && item.target_node_id === edge.target_node_id),
            );
            this.markDirty();
            this.$nextTick(() => this.renderEdges());
        },

        renderEdges() {
            if (this.edgeRenderFrame !== null) {
                return;
            }

            this.edgeRenderFrame = window.requestAnimationFrame(() => {
                this.edgeRenderFrame = null;

                if (!this.$refs?.edgesLayer) {
                    return;
                }

                const lines = this.edges.map((edge) => {
                    const source = this.getNodeCenter(edge.source_node_id);
                    const target = this.getNodeCenter(edge.target_node_id);
                    const valid = Number.isFinite(source.x)
                        && Number.isFinite(source.y)
                        && Number.isFinite(target.x)
                        && Number.isFinite(target.y);

                    if (!valid) {
                        return '';
                    }

                    return `<line x1="${source.x}" y1="${source.y}" x2="${target.x}" y2="${target.y}" stroke="#6b7280" stroke-width="2" marker-end="url(#arrowhead)"></line>`;
                }).join('');

                const suggestedLines = this.buildSuggestedConnectionLines();

                const previewLine = this.connecting
                    ? `<line x1="${this.connectStart.x}" y1="${this.connectStart.y}" x2="${this.connectEnd.x}" y2="${this.connectEnd.y}" stroke="#ef4444" stroke-width="2" stroke-dasharray="5,5"></line>`
                    : '';

                this.$refs.edgesLayer.innerHTML = `${suggestedLines}${lines}${previewLine}`;
            });
        },

        buildSuggestedConnectionLines() {
            if (!this.activeGuideTriggerType || this.nodes.length < 2) {
                return '';
            }

            const connectedPairs = new Set(
                this.edges.map((edge) => `${edge.source_node_id}|${edge.target_node_id}`),
            );

            const triggerNodes = this.nodes.filter((n) => n.type === 'trigger' && n.action_type === this.activeGuideTriggerType);
            const mapEntry = this.compatibilityMap[this.activeGuideTriggerType];
            if (!mapEntry || triggerNodes.length === 0) {
                return '';
            }

            const suggestions = [];

            triggerNodes.forEach((trigger) => {
                this.nodes.forEach((target) => {
                    if (target.node_id === trigger.node_id || target.type === 'trigger') {
                        return;
                    }

                    const key = `${trigger.node_id}|${target.node_id}`;
                    if (connectedPairs.has(key)) {
                        return;
                    }

                    const isCompatible = target.type === 'condition'
                        ? (mapEntry.conditions || []).includes(target.action_type)
                        : target.type === 'action'
                            ? (mapEntry.actions || []).includes(target.action_type)
                            : false;

                    if (isCompatible) {
                        suggestions.push({ source: trigger.node_id, target: target.node_id });
                    }
                });
            });

            return suggestions.map((s) => {
                const source = this.getNodeCenter(s.source);
                const target = this.getNodeCenter(s.target);
                if (!Number.isFinite(source.x) || !Number.isFinite(target.x)) {
                    return '';
                }
                return `<line x1="${source.x}" y1="${source.y}" x2="${target.x}" y2="${target.y}" stroke="#d1d5db" stroke-width="1" stroke-dasharray="6,4" opacity="0.45" marker-end="url(#arrowhead-suggestion)"></line>`;
            }).join('');
        },

        buildPayload() {
            return {
                nodes: this.nodes.map((node) => ({
                    node_id: node.node_id,
                    type: node.type,
                    action_type: node.action_type,
                    label: node.label,
                    config: node.config || {},
                    position: node.position || { x: 100, y: 100 },
                })),
                edges: this.edges.map((edge) => ({
                    source_node_id: edge.source_node_id,
                    target_node_id: edge.target_node_id,
                    label: edge.label,
                    condition_branch: edge.condition_branch,
                })),
            };
        },

        buildTriggerPayload() {
            const payload = {};
            const triggerNodes = this.nodes.filter((node) => node.type === 'trigger');

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
            if (!url) {
                throw new Error('Workflow editor endpoint is not configured.');
            }

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

            if (!this.ensureInspectorValidation('saving')) {
                return Promise.resolve({ success: false, localValidationFailed: true });
            }

            this.saving = true;

            if (!silent) {
                this.statusMessage = '';
            }

            const idempotencyKey = `save-${Date.now()}-${Math.random().toString(36).slice(2)}`;
            this.savePromise = this.requestJson(this.urls.saveGraph, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Idempotency-Key': idempotencyKey,
                    Accept: 'application/json',
                },
                body: JSON.stringify(this.buildPayload()),
            }).then((data) => {
                this.graphHash = data.graph_hash || this.graphHash;
                this.syncToken = data.sync_token || this.syncToken;

                if (data.version) {
                    this.nodes = (data.version.nodes || []).map((node) => this.normalizeNode(node));
                    this.edges = (data.version.edges || []).map((edge) => this.normalizeEdge(edge));
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
            if (!this.ensureInspectorValidation('validating')) {
                return;
            }

            try {
                await this.saveGraph({ silent: true });
                const data = await this.requestJson(this.urls.validate, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                    },
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
            if (!confirm('Publish this workflow? It will become active.')) {
                return;
            }

            if (!this.ensureInspectorValidation('publishing')) {
                return;
            }

            try {
                await this.saveGraph({ silent: true });
                await this.requestJson(this.urls.publish, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                    },
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
            if (!this.ensureInspectorValidation(dryRun ? 'running the dry run' : 'running the workflow')) {
                return;
            }

            try {
                const idempotencyKey = `run-${Date.now()}-${Math.random().toString(36).slice(2)}`;
                const data = await this.requestJson(this.urls.run, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Idempotency-Key': idempotencyKey,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ dry_run: dryRun, trigger_payload: this.buildTriggerPayload() }),
                });

                this.statusMessage = `${dryRun ? 'Dry run' : 'Run'} completed: ${data.run.status}`;
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
                const params = this.syncToken ? `?since=${encodeURIComponent(this.syncToken)}` : '';
                const data = await this.requestJson(`${this.urls.graphState}${params}`, {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!data.changed) {
                    this.syncToken = data.sync_token || this.syncToken;
                    return;
                }

                if (data.version) {
                    this.nodes = (data.version.nodes || []).map((node) => this.normalizeNode(node));
                    this.edges = (data.version.edges || []).map((edge) => this.normalizeEdge(edge));
                    this.rebindSelectedNode();
                }

                this.graphHash = data.graph_hash || this.graphHash;
                this.syncToken = data.sync_token || this.syncToken;
            } catch (error) {
                // Keep background sync silent for transient failures.
            } finally {
                this.syncing = false;
            }
        },

        async loadVersionHistory() {
            this.loadingVersions = true;

            try {
                const data = await this.requestJson(this.urls.versions, {
                    method: 'GET',
                    headers: { Accept: 'application/json' },
                });

                this.versionHistory = data.versions || [];
            } catch (error) {
                console.error('Failed to load version history', error);
            } finally {
                this.loadingVersions = false;
            }
        },

        async rollbackToVersion(versionId, versionNumber) {
            const confirmed = typeof window.Swal !== 'undefined'
                ? (await window.Swal.fire({
                    title: `Rollback to v${versionNumber}?`,
                    text: 'A new version will be created. Current published version will be archived.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Rollback',
                    confirmButtonColor: '#ea580c',
                })).isConfirmed
                : confirm(`Rollback to v${versionNumber}?`);

            if (!confirmed) {
                return;
            }

            try {
                const data = await this.requestJson(`${this.urls.rollbackBase}/${versionId}/rollback`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                if (data.success) {
                    this.setStatus('Rolled back successfully. Reloading...', 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    this.setStatus(data.error || 'Rollback failed.', 'error');
                }
            } catch (error) {
                this.setStatus(`Rollback failed: ${error.message}`, 'error');
            }
        },
    };
}

export default function registerWorkflowEditor(Alpine) {
    Alpine.data('workflowEditor', createWorkflowEditor);
}
