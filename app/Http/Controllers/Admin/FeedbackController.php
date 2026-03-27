<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    public function store(Request $request, Order $order)
    {
        // Ensure order belongs to user's brand
        abort_unless($order->store->brand_id === Auth::user()->brand_id, 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
            'source'  => ['nullable', 'string', 'max:50'],
        ]);

        $order->feedback()->updateOrCreate([], array_merge($data, [
            'source' => $data['source'] ?? 'cashier_app'
        ]));

        return response()->json(['success' => true]);
    }
}
