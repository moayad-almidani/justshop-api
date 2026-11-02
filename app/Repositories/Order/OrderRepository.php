<?php

declare(strict_types=1);

namespace App\Repositories\Order;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Models\Order;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Order::class;
    }

    public function getUserOrders(int $userId, int $storeId): LengthAwarePaginator
    {
        return $this->scopedQuery()
            ->where('user_id', $userId)
            ->with([
                'items.productVariant.product.translations',
                'items.productVariant.images',
                'items.productVariant.mediaLinks.media',
                'shippingAddress',
                'billingAddress',
                'paymentMethod',
            ])
            ->latest()
            ->paginate(10);
    }

    public function findById(int $id, int $storeId): ?Order
    {
        return $this->scopedQuery()
            ->with([
                'items.productVariant.product.images',
                'items.productVariant.product.mediaLinks.media',
                'shippingAddress',
                'billingAddress',
                'paymentMethod'
            ])
            ->find($id);
    }

    public function findByOrderNumber(string $orderNumber, int $storeId): ?Order
    {
        return $this->scopedQuery()
            ->with([
                'items.productVariant.product.images',
                'items.productVariant.product.mediaLinks.media',
                'shippingAddress',
                'billingAddress',
                'paymentMethod'
            ])
            ->where('order_number', $orderNumber)
            ->first();
    }

    public function create(array $data, int $storeId): Order
    {
        $data['store_id'] = $this->getCurrentStoreId() ?? $storeId;
        return Order::create($data);
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);
        return $order->fresh();
    }

    public function cancel(Order $order): Order
    {
        $order->update([
            'status' => OrderStatusEnum::CANCELLED,
            'payment_status' => PaymentStatusEnum::REFUNDED
        ]);
        return $order->fresh();
    }

    public function restoreProductVariants(Order $order): void
    {
        foreach ($order->items as $item) {
            $item->productVariant->increment('quantity', $item->quantity);
        }
    }
}
