<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentSpecializationRequest;
use App\Http\Resources\AgentSpecializationResource;
use App\Models\Agent;
use App\Models\AgentSpecialization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentSpecializationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AgentSpecialization::query();

        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return AgentSpecializationResource::collection(
            $query->with('agent')->latest()->paginate()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AgentSpecializationRequest $request)
    {
        $agent = Agent::findOrFail($request->agent_id);
        
        $specialization = new AgentSpecialization($request->validated());
        $specialization->save();

        return new AgentSpecializationResource($specialization->load('agent'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $specialization = AgentSpecialization::with('agent')->findOrFail($id);
        return new AgentSpecializationResource($specialization);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AgentSpecializationRequest $request, string $id)
    {
        $specialization = AgentSpecialization::findOrFail($id);
        $specialization->update($request->validated());

        return new AgentSpecializationResource($specialization->load('agent'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $specialization = AgentSpecialization::findOrFail($id);
        $specialization->delete();

        return response()->noContent();
    }
}
