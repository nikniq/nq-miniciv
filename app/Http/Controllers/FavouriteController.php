<?php

namespace App\Http\Controllers;

use App\Models\Favourite;
use App\Models\Product;
use Illuminate\Http\Request;

class FavouriteController extends Controller
{
    public function toggle(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'id' => 'required|integer',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $map = [
            'product' => Product::class,
        ];

        // If games are disabled, do not allow favoriting products (games)
        if ($request->input('type') === 'product' && ! config('games.enabled')) {
            return response()->json(['message' => 'Games are disabled'], 403);
        }

        $type = $request->input('type');
        $id = (int) $request->input('id');

        if (! isset($map[$type])) {
            return response()->json(['message' => 'Invalid type'], 422);
        }

        $class = $map[$type];
        $item = $class::find($id);
        if (! $item) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $exists = Favourite::where('user_id', $user->id)
            ->where('favoritable_type', $class)
            ->where('favoritable_id', $id)
            ->first();

        if ($exists) {
            $exists->delete();
            return response()->json(['favorited' => false]);
        }

        Favourite::create([
            'user_id' => $user->id,
            'favoritable_type' => $class,
            'favoritable_id' => $id,
        ]);

        return response()->json(['favorited' => true]);
    }
}
