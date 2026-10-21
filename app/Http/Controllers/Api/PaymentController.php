<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\IndexPaymentRequest;
use App\Http\Requests\Payment\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(IndexPaymentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payments = $this->paymentService->paginateForUser(
            $user,
            $request->orderFilter(),
            $request->perPage()
        );

        return ApiResponse::success([
            'payments' => PaymentResource::collection($payments->items()),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    public function indexForOrder(IndexPaymentRequest $request, int $order): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payments = $this->paymentService->paginateForOrder(
            $user,
            $order,
            $request->perPage()
        );

        return ApiResponse::success([
            'payments' => PaymentResource::collection($payments->items()),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    public function store(ProcessPaymentRequest $request, int $order): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $outcome = $this->paymentService->process(
            $user,
            $order,
            $request->paymentMethod(),
            $request->idempotencyKey()
        );

        return ApiResponse::success(
            ['payment' => new PaymentResource($outcome->payment)],
            $outcome->message,
            $outcome->httpStatus
        );
    }
}
