{{-- Tabbed history charts: one line per empire. --}}
<div class="chart-tabs" data-chart-group="{{ $chartId }}">
    <div class="mb-1 flex gap-1 text-[11px]">
        @foreach (array_keys($charts) as $i => $label)
            <button type="button" class="rounded px-2 py-0.5 {{ $i === 0 ? 'bg-stone-700 text-white' : 'text-stone-400 hover:text-white' }}"
                    onclick="const g = this.closest('.chart-tabs'); g.querySelectorAll('[data-chart]').forEach(c => c.hidden = c.dataset.chart !== '{{ $label }}'); g.querySelectorAll('button').forEach(b => b.className = 'rounded px-2 py-0.5 ' + (b === this ? 'bg-stone-700 text-white' : 'text-stone-400 hover:text-white'));">{{ $label }}</button>
        @endforeach
    </div>
    @foreach ($charts as $label => $svg)
        <div data-chart="{{ $label }}" @if (! $loop->first) hidden @endif>{!! $svg !!}</div>
    @endforeach
</div>
