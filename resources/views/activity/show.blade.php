@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto p-6 space-y-4">
  <a href="{{ route('activity.index') }}" class="text-blue-600 hover:underline">← Back to Activity</a>
  <h1 class="text-2xl font-semibold">Step {{ $step->id }}</h1>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="bg-white dark:bg-gray-900 shadow rounded p-4">
      <h2 class="font-semibold mb-2">Meta</h2>
      <dl class="text-sm space-y-1">
        <div><dt class="font-medium inline">Time:</dt> <dd class="inline">{{ $step->created_at }}</dd></div>
        <div><dt class="font-medium inline">Thread:</dt> <dd class="inline">{{ optional($step->thread)->subject }}</dd></div>
        <div><dt class="font-medium inline">Role:</dt> <dd class="inline">{{ $step->role }}</dd></div>
        <div><dt class="font-medium inline">Agent Role:</dt> <dd class="inline">{{ $step->agent_role ?? '—' }}</dd></div>
        <div><dt class="font-medium inline">Round:</dt> <dd class="inline">{{ $step->round_no ?? '—' }}</dd></div>
        <div><dt class="font-medium inline">Provider:Model:</dt> <dd class="inline">{{ $step->provider }}:{{ $step->model }}</dd></div>
        <div><dt class="font-medium inline">Tokens:</dt> <dd class="inline">{{ $step->tokens_total }} (in {{ $step->tokens_input }}, out {{ $step->tokens_output }})</dd></div>
        <div><dt class="font-medium inline">Latency:</dt> <dd class="inline">{{ $step->latency_ms }} ms</dd></div>
        <div><dt class="font-medium inline">Confidence:</dt> <dd class="inline">{{ $step->confidence ?? '—' }}</dd></div>
        <div><dt class="font-medium inline">Vote Score:</dt> <dd class="inline">{{ $step->vote_score !== null ? number_format($step->vote_score,2) : '—' }}</dd></div>
        <div><dt class="font-medium inline">Decision Reason:</dt> <dd class="inline">{{ $step->decision_reason ?? '—' }}</dd></div>
      </dl>
    </div>
    <div class="bg-white dark:bg-gray-900 shadow rounded p-4">
      <h2 class="font-semibold mb-2">Input</h2>
      <pre class="text-xs whitespace-pre-wrap">{{ json_encode($step->input_json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
    <div class="bg-white dark:bg-gray-900 shadow rounded p-4 md:col-span-2">
      <h2 class="font-semibold mb-2">Output</h2>
      <pre class="text-xs whitespace-pre-wrap">{{ json_encode($step->output_json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
    @php $planReport = $step->output_json['report'] ?? null; $inputPlan = $step->input_json['plan'] ?? null; @endphp
    @if($planReport)
    <div class="bg-white dark:bg-gray-900 shadow rounded p-4 md:col-span-2">
      <h2 class="font-semibold mb-2">Plan</h2>
      <div class="text-sm mb-2">
        @if(($planReport['valid'] ?? false))
          <span class="text-green-600">Valid ✓</span>
        @else
          <span class="text-red-600">Invalid ✗</span>
          <span class="ml-2">Step {{ $planReport['failing_step'] ?? '—' }} failed: {{ $planReport['error'] ?? '—' }}</span>
          @if(!empty($planReport['hint']))<div class="mt-1 text-gray-600">Hint: {{ $planReport['hint'] }}</div>@endif
        @endif
      </div>
      @if(!empty($inputPlan['steps']))
      <ol class="text-sm list-decimal pl-5 space-y-1">
        @foreach($inputPlan['steps'] as $k => $st)
          <li>
            <code>S{{ $k }}</code>
            → <code>{{ $st['action']['name'] ?? 'Action' }}</code>
            → <code>S{{ $k+1 }}</code>
          </li>
        @endforeach
      </ol>
      @endif
    </div>
    @endif
    @if(($step->agent_role === 'Critic' || $step->agent_role === 'Arbiter') && is_array($step->output_json))
    <div class="bg-white dark:bg-gray-900 shadow rounded p-4 md:col-span-2">
      <h2 class="font-semibold mb-2">Votes</h2>
      @php $votes = $step->output_json['votes'] ?? []; @endphp
      @if(!empty($votes))
      <table class="min-w-full text-sm">
        <thead><tr><th class="px-2 py-1 text-left">Round</th><th class="px-2 py-1 text-left">Candidate</th><th class="px-2 py-1 text-left">Score</th><th class="px-2 py-1 text-left">Reason</th></tr></thead>
        <tbody>
        @foreach($votes as $v)
          <tr class="border-t border-gray-100 dark:border-gray-800">
            <td class="px-2 py-1">{{ $v['round'] ?? '—' }}</td>
            <td class="px-2 py-1">{{ $v['winner_id'] ?? '—' }}</td>
            <td class="px-2 py-1">{{ isset($v['vote_score']) ? number_format((float)$v['vote_score'],3) : '—' }}</td>
            <td class="px-2 py-1">{{ $v['reason'] ?? '' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
      @else
        <div class="text-sm text-gray-500">No votes recorded.</div>
      @endif
    </div>
    @endif
  </div>
</div>
@endsection


