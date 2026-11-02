<?php

namespace App\Repositories\Cart;

use App\Models\Cart;
use App\Models\User;

class CartRepository
{
    public function getOrCreate(User $user, int $storeId): Cart
    {
        return Cart::firstOrCreate(
            [
                'user_id'  => $user->id,
                'store_id' => $storeId,
            ],
            [
                'user_id'  => $user->id,
                'store_id' => $storeId,
            ]
        );
    }

    public function getWithItems(User $user, int $storeId): Cart
    {
        $cart = $this->getOrCreate($user, $storeId);

        return $cart->load([
            'items.productVariant.product.translations',
            'items.productVariant.images',
            'items.productVariant.mediaLinks.media',
            'items.productVariant.product.mediaLinks.media',
            'items.productVariant.optionValues.option',
        ]);
    }

    public function findById(int $id, int $storeId): Cart
    {
        return Cart::where('store_id', $storeId)
            ->findOrFail($id);
    }

    public function findByUser(User $user, int $storeId): ?Cart
    {
        return Cart::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->first();
    }

    public function deleteByCart(Cart $cart): void
    {
        $cart->items()->delete();
    }
}