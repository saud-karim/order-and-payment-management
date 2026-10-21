<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\IndexOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(IndexOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $orders = $this->orderService->paginateForUser(
            $user,
            $request->statusFilter(),
            $request->perPage()
        );

        return ApiResponse::success([
            'orders' => OrderResource::collection($orders->items()),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $order = $this->orderService->create($user, $request->orderPayload());

        return ApiResponse::success(
            ['order' => new OrderResource($order)],
            'Order created successfully.',
            201
        );
    }

    public function show(int $order): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $orderModel = $this->orderService->findForUserOrFail($user, $order);
        $this->authorize('view', $orderModel);

        return ApiResponse::success(['order' => new OrderResource($orderModel)]);
    }

    public function update(UpdateOrderRequest $request, int $order): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $orderModel = $this->orderService->findForUserOrFail($user, $order);
        $this->authorize('update', $orderModel);

        $updatedOrder = $this->orderService->update($orderModel, $request->validated());

        return ApiResponse::success(
            ['order' => new OrderResource($updatedOrder)],
            'Order updated successfully.'
        );
    }

    public function destroy(int $order): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $orderModel = $this->orderService->findForUserOrFail($user, $order);
        $this->authorize('delete', $orderModel);

        $this->orderService->delete($orderModel);

        return ApiResponse::success(null, 'Order deleted successfully.');
    }
}
