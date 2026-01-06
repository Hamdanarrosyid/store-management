<?php
// app/Http/Controllers/Api/SaleController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Helper\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleController extends Controller
{

    public function index(Request $request)
    {
        $actor = auth('api')->user();
        if (!$actor) return ApiResponse::error('Unauthenticated', 401);

        $perPage = max(1, (int) $request->query('per_page', 10));
        $q = $request->query('q'); // invoice search
        $status = $request->query('status'); // PAID/CANCELLED
        $from = $request->query('from'); // YYYY-MM-DD
        $to = $request->query('to');     // YYYY-MM-DD
        $storeId = $request->query('store_id'); // super admin only

        $query = Sale::query()
            ->with([
                'cashier:id,name,email',
            ]);

        // Scope store
        $scopedStoreId = $request->attributes->get('scoped_store_id');
        if (!$actor->isSuperAdmin()) {
            if (!$scopedStoreId) return ApiResponse::error('Invalid store scope', 403);
            $query->where('store_id', (int) $scopedStoreId);
        } else {
            if (!is_null($storeId) && $storeId !== '') {
                $query->where('store_id', (int) $storeId);
            }
        }

        if ($q) {
            $query->where('invoice_no', 'ILIKE', "%{$q}%");
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sales = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::pagination($sales);
    }


    public function store(Request $request)
    {
        $actor = auth('api')->user();
        if (!$actor) return ApiResponse::error('Unauthenticated', 401);

        
        if (!($actor->isAdmin() || $actor->isCashier() || $actor->isSuperAdmin())) {
            return ApiResponse::error('Forbidden', 403);
        }

        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'numeric', 'min:0'],
        ];
        $data = $request->validate($rules);

        // Store scope
        $storeId = null;
        $scopedStoreId = $request->attributes->get('scoped_store_id');
        if (!$actor->isSuperAdmin()) {
            if (!$scopedStoreId) return ApiResponse::error('Invalid store scope', 403);
            $storeId = (int) $scopedStoreId;
        } else {
            // Here we enforce super admin must specify store_id via query param to avoid ambiguity.
            $storeIdParam = $request->query('store_id');
            if (!$storeIdParam) {
                return ApiResponse::error('store_id is required for super admin to create sales.', 422, [
                    'store_id' => ['Please provide store_id as query parameter.'],
                ]);
            }
            $storeId = (int) $storeIdParam;
        }

        $discount = (float) ($data['discount'] ?? 0);
        $tax = (float) ($data['tax'] ?? 0);

        $sale = DB::transaction(function () use ($actor, $storeId, $data, $discount, $tax) {

            // Load products must belong to the same store
            $productIds = collect($data['items'])->pluck('product_id')->unique()->values()->all();

            $products = Product::query()
                ->where('store_id', $storeId)
                ->whereIn('id', $productIds)
                ->get(['id', 'name', 'price']);

            if ($products->count() !== count($productIds)) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Some products do not belong to your store.',
                    'errors' => ['items' => ['Invalid product scope.']],
                ], 422));
            }

            $productMap = $products->keyBy('id');

            // Compute totals
            $subtotal = 0.0;
            $lineItems = [];

            foreach ($data['items'] as $item) {
                $product = $productMap[(int) $item['product_id']];
                $qty = (int) $item['quantity'];
                $unitPrice = (float) $product->price;
                $lineTotal = $unitPrice * $qty;

                $subtotal += $lineTotal;

                $lineItems[] = [
                    'product_id' => (int) $product->id,
                    'product_name_snapshot' => (string) $product->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $qty,
                    'line_total' => $lineTotal,
                ];
            }

            $total = max(0, ($subtotal - $discount + $tax));
            $paidAmount = (float) $data['paid_amount'];

            if ($paidAmount < $total) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Insufficient payment amount.',
                    'errors' => ['paid_amount' => ['Paid amount must be greater than or equal to total.']],
                ], 422));
            }

            $change = $paidAmount - $total;

            $sale = Sale::create([
                'store_id' => $storeId,
                'invoice_no' => $this->generateInvoiceNo($storeId),
                'cashier_user_id' => $actor->id,
                'status' => 'PAID',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'change_amount' => $change,
                'payment_method' => $data['payment_method'] ?? null,
                'paid_at' => now(),
            ]);

            foreach ($lineItems as $li) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $li['product_id'],
                    'product_name_snapshot' => $li['product_name_snapshot'],
                    'unit_price' => $li['unit_price'],
                    'quantity' => $li['quantity'],
                    'line_total' => $li['line_total'],
                ]);
            }

            return $sale;
        });

        $sale->load([
            'cashier:id,name,email',
            'items:id,sale_id,product_id,product_name_snapshot,unit_price,quantity,line_total',
        ]);

        return ApiResponse::success($sale, 'Sale created', 201);
    }


    public function show(Request $request, int $id)
    {
        $actor = auth('api')->user();
        if (!$actor) return ApiResponse::error('Unauthenticated', 401);

        $query = Sale::query()
            ->with([
                'cashier:id,name,email',
                'items:id,sale_id,product_id,product_name_snapshot,unit_price,quantity,line_total',
            ]);

        // Scope store
        $scopedStoreId = $request->attributes->get('scoped_store_id');
        if (!$actor->isSuperAdmin()) {
            if (!$scopedStoreId) return ApiResponse::error('Invalid store scope', 403);
            $query->where('store_id', (int) $scopedStoreId);
        } else {
            // optional store_id filter not needed for show
            
        }

        $sale = $query->findOrFail($id);

        return ApiResponse::success($sale, 'Sale detail');
    }

    private function generateInvoiceNo(int $storeId): string
    {
        return 'INV-' . $storeId . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }
}
