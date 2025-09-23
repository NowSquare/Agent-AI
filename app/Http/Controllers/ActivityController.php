<?php

namespace App\Http\Controllers;

use App\Models\AgentStep;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = AgentStep::query()->visibleTo($user);

        if ($request->filled('thread')) {
            $query->where('thread_id', $request->string('thread'));
        }
        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }
        if ($request->filled('provider')) {
            $query->where('provider', $request->string('provider'));
        }
        if ($request->filled('model')) {
            $query->where('model', $request->string('model'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        $steps = $query->latest('created_at')->limit(200)->get();

        return view('activity.index', compact('steps'));
    }

    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $step = AgentStep::query()->visibleTo($user)->findOrFail($id);
        return view('activity.show', compact('step'));
    }
}


