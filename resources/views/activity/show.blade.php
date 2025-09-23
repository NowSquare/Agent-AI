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
        <div><dt class="font-medium inline">Provider:Model:</dt> <dd class="inline">{{ $step->provider }}:{{ $step->model }}</dd></div>
        <div><dt class="font-medium inline">Tokens:</dt> <dd class="inline">{{ $step->tokens_total }} (in {{ $step->tokens_input }}, out {{ $step->tokens_output }})</dd></div>
        <div><dt class="font-medium inline">Latency:</dt> <dd class="inline">{{ $step->latency_ms }} ms</dd></div>
        <div><dt class="font-medium inline">Confidence:</dt> <dd class="inline">{{ $step->confidence ?? '—' }}</dd></div>
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
  </div>
</div>
@endsection


