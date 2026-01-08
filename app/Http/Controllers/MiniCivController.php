<?php

namespace App\Http\Controllers;

use App\Models\MiniCivState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MiniCivController extends Controller
{
    /** Save MiniCiv state for the authenticated user */
    public function save(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $request->input('state');

        MiniCivState::updateOrCreate(
            ['user_id' => $user->id],
            ['state' => $data]
        );

        return response()->json(['status' => 'saved']);
    }

    /** Delete a saved MiniCiv state (only owner) */
    public function delete(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $id = $request->input('id');
        if (! $id) {
            return back()->withErrors(['miniciv' => 'Missing state id']);
        }

        $state = MiniCivState::where('id', $id)->where('user_id', $user->id)->first();
        if ($state) {
            $state->delete();
        }

        return back()->with('status', 'Saved civilisation removed.');
    }
}
