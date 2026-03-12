<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncomingRequest;
use App\Models\Product;
use App\Models\RequestComment;
use App\Models\RequestAttachment;
use App\Services\BranchAccessService;
use App\Services\RequestWorkflowService;
use App\Services\SubstitutionService;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncomingRequestController extends Controller
{
    protected BranchAccessService $branchAccessService;
    protected RequestWorkflowService $workflowService;
    protected SubstitutionService $substitutionService;
    protected AvailabilityService $availabilityService;

    public function __construct(
        BranchAccessService $branchAccessService,
        RequestWorkflowService $workflowService,
        SubstitutionService $substitutionService,
        AvailabilityService $availabilityService
    ) {
        $this->branchAccessService = $branchAccessService;
        $this->workflowService = $workflowService;
        $this->substitutionService = $substitutionService;
        $this->availabilityService = $availabilityService;
    }

    public function index(Request $request)
    {
        $branchId = $this->branchAccessService->resolveBranchFilter($request->user(), $request->branch_id, defaultToUserBranch: true);

        $requests = IncomingRequest::with(['branch', 'requester', 'items.product'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn($q, $p) => $q->where('priority', $p))
            ->when($branchId, fn($q, $b) => $q->where('branch_id', $b))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $branches = $this->branchAccessService->visibleBranches($request->user());

        return view('admin.requests.index', compact('requests', 'branches'));
    }

    public function create()
    {
        $products = Product::where('is_archived', false)->get();
        $branches = $this->branchAccessService->visibleBranches(Auth::user());
        return view('admin.requests.create', compact('products', 'branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'department' => 'nullable|string|max:255',
            'priority' => 'required|in:low,normal,high,urgent',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            // Accept both names for backward compatibility with older forms.
            'items.*.quantity_requested' => 'nullable|integer|min:1',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.allow_substitution' => 'sometimes|boolean',
        ]);

        $validated['branch_id'] = $this->branchAccessService->resolveBranchFilter($request->user(), $validated['branch_id']);

        $normalizedItems = collect($validated['items'])->values()->map(function (array $item, int $index): array {
            $quantityRequested = $item['quantity_requested'] ?? $item['quantity'] ?? null;

            if ($quantityRequested === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity_requested" => 'The quantity requested field is required.',
                ]);
            }

            return [
                'product_id' => (int) $item['product_id'],
                'quantity_requested' => (int) $quantityRequested,
                'allow_substitution' => (bool) ($item['allow_substitution'] ?? false),
            ];
        })->all();

        $incomingRequest = $this->workflowService->createRequest(
            collect($validated)->only(['branch_id', 'department', 'priority', 'remarks'])->toArray(),
            $normalizedItems,
            Auth::id()
        );

        return redirect()->route('admin.requests.index')
            ->with('success', 'Request created successfully.');
    }

    public function show(IncomingRequest $incomingRequest)
    {
        $this->branchAccessService->authorizeBranchAccess(Auth::user(), $incomingRequest->branch_id, 'view requests from another branch');
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
        $this->branchAccessService->authorizeBranchAccess($request->user(), $incomingRequest->branch_id, 'update requests from another branch');

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
        $this->branchAccessService->authorizeBranchAccess(Auth::user(), $incomingRequest->branch_id, 'fulfill requests from another branch');

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
        $this->branchAccessService->authorizeBranchAccess($request->user(), $incomingRequest->branch_id, 'comment on requests from another branch');

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
        $this->branchAccessService->authorizeBranchAccess($request->user(), $incomingRequest->branch_id, 'attach files to requests from another branch');

        $validated = $request->validate([
            'attachment' => 'required|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,xlsx,xls,csv',
        ]);

        $file = $validated['attachment'];
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('request-attachments', $filename, 'local');

        RequestAttachment::create([
            'incoming_request_id' => $incomingRequest->id,
            'user_id' => Auth::id(),
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }
}
