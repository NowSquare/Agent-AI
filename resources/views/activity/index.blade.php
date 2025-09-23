@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6">
  <h1 class="text-2xl font-semibold mb-4">Activity</h1>
  <div class="overflow-x-auto bg-white dark:bg-gray-900 shadow rounded">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800">
        <tr>
          <th class="px-4 py-2 text-left">Time</th>
          <th class="px-4 py-2 text-left">Thread</th>
          <th class="px-4 py-2 text-left">Role</th>
          <th class="px-4 py-2 text-left">Provider:Model</th>
          <th class="px-4 py-2 text-left">Tokens</th>
          <th class="px-4 py-2 text-left">Latency</th>
          <th class="px-4 py-2 text-left">Confidence</th>
          <th class="px-4 py-2 text-left"></th>
        </tr>
      </thead>
      <tbody>
      @forelse($steps as $s)
        <tr class="border-t border-gray-100 dark:border-gray-800">
          <td class="px-4 py-2 whitespace-nowrap">{{ $s->created_at->diffForHumans() }}</td>
          <td class="px-4 py-2">{{ optional($s->thread)->subject }}</td>
          <td class="px-4 py-2">{{ $s->role }}</td>
          <td class="px-4 py-2">{{ $s->provider }}:{{ $s->model }}</td>
          <td class="px-4 py-2">{{ $s->tokens_total }}</td>
          <td class="px-4 py-2">{{ $s->latency_ms }} ms</td>
          <td class="px-4 py-2">{{ $s->confidence ?? '—' }}</td>
          <td class="px-4 py-2"><a href="{{ route('activity.show', $s->id) }}" class="text-blue-600 hover:underline">View</a></td>
        </tr>
      @empty
        <tr><td class="px-4 py-6 text-gray-500" colspan="8">No activity found.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection


