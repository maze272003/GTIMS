<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncomingRequest;
use App\Models\Product;
use App\Models\Branch;
use App\Models\RequestComment;
use App\Models\RequestAttachment;
use App\Services\TenantStorageService;
use App\Services\RequestWorkflowService;
use App\Services\SubstitutionService;
use App\Services\AvailabilityService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IncomingRequestController extends Controller
{
    protected RequestWorkflowService $workflowService;
    protected SubstitutionService $substitutionService;
    protected AvailabilityService $availabilityService;
    protected TenantStorageService $tenantStorageService;

    public function __construct(
        RequestWorkflowService $workflowService,
        SubstitutionService $substitutionService,
        AvailabilityService $availabilityService,
        TenantStorageService $tenantStorageService
    ) {
        $this->workflowService = $workflowService;
        $this->substitutionService = $substitutionService;
        $this->availabilityService = $availabilityService;
        $this->tenantStorageService = $tenantStorageService;
    }

    public function index(Request $request)
    {
        $requests = IncomingRequest::with(['branch', 'requester', 'items.product'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn($q, $p) => $q->where('priority', $p))
            ->when($request->branch_id, fn($q, $b) => $q->where('branch_id', $b))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $branches = Branch::all();

        return view('admin.requests.index', compact('requests', 'branches'));
    }

    public function create()
    {
        $products = Product::where('is_archived', false)->get();
        $branches = Branch::all();
        return view('admin.requests.create', compact('products', 'branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'department' => 'nullable|string|max:255',
            'priority' => 'required|in:low,normal,high,urgent',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_requested' => 'required|integer|min:1',
            'items.*.allow_substitution' => 'sometimes|boolean',
        ]);

        $incomingRequest = $this->workflowService->createRequest(
            collect($validated)->only(['branch_id', 'department', 'priority', 'remarks'])->toArray(),
            $validated['items'],
            Auth::id()
        );

        return redirect()->route('admin.requests.index')
            ->with('success', 'Request created successfully.');
    }

    public function show(IncomingRequest $incomingRequest)
    {
        $incomingRequest->load([
            'branch', 'requester', 'items.product', 'items.substitutedProduct',
            'comments.user', 'attachments.user', 'statusHistory.changer',
        ]);

        $availability = $this->workflowService->checkAvailability($incomingRequest);

        $substitutions = [];
        foreach ($incomingRequest->items as $item) {
            if ($item->allow_substitution) {
                $substitutions[$item->id] = $this->substitutionService->suggestSubstitutes(
                    $item->product_id,
                    $incomingRequest->branch_id
                );
            }
        }

        return view('admin.requests.show', compact('incomingRequest', 'availability', 'substitutions'));
    }

    public function transition(Request $request, IncomingRequest $incomingRequest)
    {
        $validated = $request->validate([
            'status' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        try {
            $this->workflowService->transitionStatus(
                $incomingRequest,
                $validated['status'],
                Auth::id(),
                $validated['reason'] ?? null,
                $request->header('X-Idempotency-Key')
            );
            return back()->with('success', "Request status updated to {$validated['status']}.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function fulfill(IncomingRequest $incomingRequest)
    {
        try {
            $this->workflowService->fulfillRequest(
                $incomingRequest,
                Auth::id(),
                request()->header('X-Idempotency-Key')
            );
            return back()->with('success', 'Request fulfillment processed.');
        } catch (\Exception $e) {
            return back()->with('error', 'Fulfillment failed: ' . $e->getMessage());
        }
    }

    public function addComment(Request $request, IncomingRequest $incomingRequest)
    {
        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        RequestComment::create([
            'incoming_request_id' => $incomingRequest->id,
            'user_id' => Auth::id(),
            'comment' => $validated['comment'],
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function addAttachment(Request $request, IncomingRequest $incomingRequest)
    {
        $validated = $request->validate([
            'attachment' => 'required|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,xlsx,xls,csv',
        ]);

        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');
        if (!$tenantContext) {
            return back()->with('error', 'Tenant context is missing for file upload.');
        }

        $file = $validated['attachment'];
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $relativePath = 'request-attachments/' . $filename;
        $scopedPath = $this->tenantStorageService->tenantPath($relativePath, $tenantContext);
        $file->storeAs(dirname($scopedPath), basename($scopedPath), config('tenancy.storage.disk', 'local'));

        if (!$this->tenantStorageService->belongsToTenant($scopedPath, $tenantContext)) {
            return back()->with('error', 'Attachment path failed tenant boundary validation.');
        }

        RequestAttachment::create([
            'province_id' => $tenantContext->provinceId,
            'barangay_id' => $tenantContext->barangayId,
            'incoming_request_id' => $incomingRequest->id,
            'user_id' => Auth::id(),
            'filename' => $scopedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }
}
