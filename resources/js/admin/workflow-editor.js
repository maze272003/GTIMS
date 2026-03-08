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
                return '1,2,3';
            }

            if (field === 'categories') {
                return 'vaccine,antibiotic';
            }

            return 'a,b,c';
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

                const previewLine = this.connecting
                    ? `<line x1="${this.connectStart.x}" y1="${this.connectStart.y}" x2="${this.connectEnd.x}" y2="${this.connectEnd.y}" stroke="#ef4444" stroke-width="2" stroke-dasharray="5,5"></line>`
                    : '';

                this.$refs.edgesLayer.innerHTML = `${lines}${previewLine}`;
            });
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

            this.saving = true;

            if (!silent) {
                this.statusMessage = '';
                this.validationErrors = [];
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
            this.validationErrors = [];

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
