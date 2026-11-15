@extends('layouts.admin.app')
@section('title', 'Stranded — Coverage')

@section('content')
<div class="container-fluid">
  <div class="page-header">
    <h1 class="page-header-title">Stranded &mdash; Coverage</h1>
    <p class="text-muted mb-0">Where requests came from, how many went unanswered, and the helpers that exist there now. Recruit where the gaps are.</p>
  </div>

  <form method="GET" class="mb-3 d-flex align-items-center" style="gap:.5rem">
    <label class="mb-0">Last</label>
    <select name="days" class="form-control" style="width:auto" onchange="this.form.submit()">
      @foreach ([7, 30, 90, 365] as $d)
        <option value="{{ $d }}" @selected($days === $d)>{{ $d }} days</option>
      @endforeach
    </select>
  </form>

  <div class="row mb-3">
    @foreach ([
      ['Requests', $summary['requests']],
      ['Answer rate', $summary['answer_rate'] === null ? '–' : $summary['answer_rate'].'%'],
      ['Median first answer', $summary['median_first_answer_minutes'] === null ? '–' : $summary['median_first_answer_minutes'].' min'],
      ['Gap areas', $summary['gap_cells']],
      ['Helpers online now', $summary['online_helpers_now']],
      ['Opted-in neighbours now', $summary['opted_in_neighbours_now']],
    ] as [$label, $value])
      <div class="col-6 col-md-2"><div class="card"><div class="card-body text-center py-3">
        <div style="font-size:1.5rem;font-weight:700">{{ $value }}</div><div class="text-muted" style="font-size:.75rem">{{ $label }}</div>
      </div></div></div>
    @endforeach
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="thead-light"><tr>
          <th>Area (centre)</th><th>Requests</th><th>Unanswered</th><th>Answer rate</th><th>Median first answer</th><th>Helpers online</th><th>Opted-in neighbours</th>
        </tr></thead>
        <tbody>
        @forelse ($cells as $c)
          <tr class="{{ $c['priority'] > 0 ? 'table-warning' : '' }}">
            <td><a href="{{ $c['map_url'] }}" target="_blank" rel="noopener">{{ $c['lat'] }}, {{ $c['lng'] }}</a></td>
            <td>{{ $c['requests'] }}</td>
            <td>{{ $c['unanswered'] }}</td>
            <td>{{ $c['answer_rate'] === null ? '–' : $c['answer_rate'].'%' }}</td>
            <td>{{ $c['median_first_answer_minutes'] === null ? '–' : $c['median_first_answer_minutes'].' min' }}</td>
            <td>{{ $c['online_helpers'] }}</td>
            <td>{{ $c['opted_in_neighbours'] }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center py-4 text-muted">No Stranded activity in this period yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-muted mt-2" style="font-size:.8rem">Areas are about 7 miles square. Highlighted rows have unanswered requests, or demand with no helper nearby.</p>
</div>
@endsection
