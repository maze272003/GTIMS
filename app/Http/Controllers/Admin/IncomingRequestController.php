<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncomingRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSubstitute;
use App\Models\RequestAttachment;
use App\Models\RequestComment;
use App\Services\AvailabilityService;
use App\Services\BranchAccessService;
use App\Services\RequestWorkflowService;
use App\Services\SubstitutionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $branches = $this->branchAccessService->visibleBranches($request->user());

        return view('admin.requests.index', compact('requests', 'branches'));
    }

    public function create()
    {
        $products = Product::where('is_archived', false)
            ->select(['id', 'generic_name', 'brand_name', 'form', 'strength'])
            ->orderBy('generic_name')
            ->get();
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
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $this->branchAccessService->authorizeBranchAccess($user, $incomingRequest->branch_id, 'view requests from another branch');

        if (! $user) {
            abort(403, 'Authentication required.');
        }

        $rateLimitKey = "view-request:{$user->id}:{$incomingRequest->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            Log::warning('incoming-requests.show.rate_limited', [
                'incoming_request_id' => $incomingRequest->id,
                'user_id' => $user->id,
            ]);

            return back()->with('error', 'Too many requests');
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            $incomingRequest->load([
                'branch', 'requester', 'items.product', 'items.substitutedProduct',
                'comments.user', 'attachments.user', 'statusHistory.changer',
            ]);

            $itemProductIds = $incomingRequest->items
                ->pluck('product_id')
                ->filter(fn ($productId) => is_numeric($productId) && (int) $productId > 0)
                ->map(fn ($productId) => (int) $productId)
                ->unique()
                ->values();

            $substitutionProductIds = $incomingRequest->items
                ->where('allow_substitution', true)
                ->pluck('product_id')
                ->filter(fn ($productId) => is_numeric($productId) && (int) $productId > 0)
                ->map(fn ($productId) => (int) $productId)
                ->unique()
                ->take(500)
                ->values();

            if ($incomingRequest->items->where('allow_substitution', true)->pluck('product_id')->unique()->count() > $substitutionProductIds->count()) {
                Log::warning('incoming-requests.show.product_limit_applied', [
                    'incoming_request_id' => $incomingRequest->id,
                    'user_id' => $user->id,
                    'limit' => 500,
                ]);
            }

            $allSubstitutes = collect();
            if ($substitutionProductIds->isNotEmpty()) {
                $substitutionProductIds->chunk(100)->each(function (Collection $chunk) use (&$allSubstitutes): void {
                    $chunkSubstitutes = ProductSubstitute::query()
                        ->select(['id', 'product_id', 'substitute_product_id', 'priority'])
                        ->with([
                            'substituteProduct' => fn ($query) => $query
                                ->active()
                                ->select(['products.id', 'generic_name', 'brand_name', 'form', 'strength']),
                        ])
                        ->whereIn('product_id', $chunk->all())
                        ->get()
                        ->groupBy('product_id');

                    foreach ($chunkSubstitutes as $productId => $substitutes) {
                        $allSubstitutes->put(
                            (int) $productId,
                            $allSubstitutes->get((int) $productId, collect())->merge($substitutes)
                        );
                    }
                });
            }

            $allEquivalents = collect();
            if ($substitutionProductIds->isNotEmpty()) {
                $substitutionProductIds->chunk(100)->each(function (Collection $chunk) use (&$allEquivalents): void {
                    $sourceProducts = Product::query()
                        ->active()
                        ->whereIn('id', $chunk->all())
                        ->select(['id', 'generic_name', 'brand_name', 'form', 'strength'])
                        ->get();

                    if ($sourceProducts->isEmpty()) {
                        return;
                    }

                    $characteristics = $sourceProducts
                        ->map(fn (Product $product): array => [
                            'generic_name' => $product->generic_name,
                            'form' => $product->form,
                            'strength' => $product->strength,
                        ])
                        ->unique(fn (array $characteristic): string => $this->equivalentKey(
                            $characteristic['generic_name'],
                            $characteristic['form'],
                            $characteristic['strength']
                        ))
                        ->values();

                    $equivalentCandidates = Product::query()
                        ->active()
                        ->where(function (Builder $query) use ($characteristics): void {
                            foreach ($characteristics as $characteristic) {
                                $query->orWhere(function (Builder $characteristicQuery) use ($characteristic): void {
                                    $characteristicQuery
                                        ->where('generic_name', $characteristic['generic_name'])
                                        ->where('form', $characteristic['form'])
                                        ->where('strength', $characteristic['strength']);
                                });
                            }
                        })
                        ->select(['id', 'generic_name', 'brand_name', 'form', 'strength'])
                        ->get()
                        ->groupBy(fn (Product $product): string => $this->equivalentKey(
                            $product->generic_name,
                            $product->form,
                            $product->strength
                        ));

                    foreach ($sourceProducts as $sourceProduct) {
                        $allEquivalents->put(
                            $sourceProduct->id,
                            $equivalentCandidates
                                ->get($this->equivalentKey(
                                    $sourceProduct->generic_name,
                                    $sourceProduct->form,
                                    $sourceProduct->strength
                                ), collect())
                                ->filter(fn (Product $product): bool => $product->id !== $sourceProduct->id)
                                ->values()
                        );
                    }
                });
            }

            $inventoryProductIds = $itemProductIds
                ->merge($allSubstitutes->flatten(1)->pluck('substitute_product_id'))
                ->merge($allEquivalents->flatten(1)->pluck('id'))
                ->filter(fn ($productId) => is_numeric($productId) && (int) $productId > 0)
                ->map(fn ($productId) => (int) $productId)
                ->unique()
                ->values();

            $allInventories = collect();
            if ($inventoryProductIds->isNotEmpty()) {
                $inventoryProductIds->chunk(200)->each(function (Collection $chunk) use (&$allInventories, $incomingRequest): void {
                    $chunkInventories = Inventory::query()
                        ->active()
                        ->where('branch_id', $incomingRequest->branch_id)
                        ->whereIn('product_id', $chunk->all())
                        ->select(['id', 'product_id', 'onhand_qty', 'quantity', 'hold_qty', 'batch_number'])
                        ->get()
                        ->groupBy('product_id');

                    foreach ($chunkInventories as $productId => $inventories) {
                        $allInventories->put(
                            (int) $productId,
                            $allInventories->get((int) $productId, collect())->merge($inventories)
                        );
                    }
                });
            }

            $availability = $incomingRequest->items->map(function ($item) use ($allInventories): array {
                $available = $this->calculateCacheInventory(
                    $allInventories->get((int) $item->product_id, collect())
                );

                return [
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'requested' => $item->quantity_requested,
                    'available' => $available,
                    'sufficient' => $available >= $item->quantity_requested,
                ];
            })->values()->all();

            $substitutions = [];
            foreach ($incomingRequest->items as $item) {
                if ($item->allow_substitution && $substitutionProductIds->contains((int) $item->product_id)) {
                    $substitutions[$item->id] = $this->buildSubstitutionsFromCache(
                        (int) $item->product_id,
                        $allSubstitutes,
                        $allEquivalents,
                        $allInventories
                    );
                } else {
                    $substitutions[$item->id] = [];
                }
            }

            Log::debug('incoming-requests.show.loaded', [
                'incoming_request_id' => $incomingRequest->id,
                'user_id' => $user->id,
                'item_count' => $incomingRequest->items->count(),
                'substitution_count' => array_sum(array_map('count', $substitutions)),
            ]);

            return view('admin.requests.show', compact('incomingRequest', 'availability', 'substitutions'));
        } catch (\Throwable $exception) {
            Log::error('incoming-requests.show.failed', [
                'incoming_request_id' => $incomingRequest->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return back()->with('error', 'Error loading request details. Please try again.');
        }
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
            return back()->with('error', 'Fulfillment failed: '.$e->getMessage());
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
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
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

    /**
     * Build substitute suggestions from pre-loaded substitute, equivalent, and inventory caches.
     *
     * @return array<int, array{product:\App\Models\Product, available:int, type:string, priority:int}>
     */
    private function buildSubstitutionsFromCache(
        int $productId,
        Collection $substitutes,
        Collection $equivalents,
        Collection $inventories
    ): array {
        $suggestions = [];

        foreach ($substitutes->get($productId, collect()) as $substitute) {
            if (! $substitute || ! $substitute->substituteProduct) {
                Log::warning('incoming-requests.show.corrupted_substitute', [
                    'product_id' => $productId,
                ]);

                continue;
            }

            $available = $this->calculateCacheInventory(
                $inventories->get((int) $substitute->substitute_product_id, collect())
            );

            if ($available <= 0) {
                continue;
            }

            $suggestions[] = [
                'product' => $substitute->substituteProduct,
                'available' => $available,
                'type' => 'explicit',
                'priority' => is_numeric($substitute->priority) ? (int) $substitute->priority : 0,
            ];
        }

        foreach ($equivalents->get($productId, collect()) as $equivalent) {
            if (! $equivalent || ! isset($equivalent->id)) {
                continue;
            }

            $alreadySuggested = collect($suggestions)->contains(
                fn (array $suggestion): bool => (($suggestion['product']->id ?? null) === $equivalent->id)
            );

            if ($alreadySuggested) {
                continue;
            }

            $available = $this->calculateCacheInventory(
                $inventories->get((int) $equivalent->id, collect())
            );

            if ($available <= 0) {
                continue;
            }

            $suggestions[] = [
                'product' => $equivalent,
                'available' => $available,
                'type' => 'equivalent',
                'priority' => 100,
            ];
        }

        usort(
            $suggestions,
            fn (array $left, array $right): int => [$left['priority'], $left['product']->generic_name] <=> [$right['priority'], $right['product']->generic_name]
        );

        return array_values($suggestions);
    }

    /**
     * Calculate available stock from an in-memory batch collection.
     */
    private function calculateCacheInventory(Collection $batches): int
    {
        $available = 0;

        foreach ($batches as $inventory) {
            $onHand = is_numeric($inventory->onhand_qty ?? $inventory->quantity ?? 0)
                ? (int) ($inventory->onhand_qty ?? $inventory->quantity ?? 0)
                : 0;
            $hold = is_numeric($inventory->hold_qty ?? 0)
                ? max(0, (int) ($inventory->hold_qty ?? 0))
                : 0;

            $available += max(0, $onHand - $hold);
        }

        return $available;
    }

    /**
     * Normalize a product-equivalence signature for cache grouping.
     */
    private function equivalentKey(mixed $genericName, mixed $form, mixed $strength): string
    {
        return mb_strtolower(trim((string) $genericName)).'|'
            .mb_strtolower(trim((string) $form)).'|'
            .mb_strtolower(trim((string) $strength));
    }
}
