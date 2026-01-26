@extends('layouts.app')

@section('content')
<header class="hero">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Ethereum — Latest transactions</h1>
        <p class="lead">Showing the most recent 10 transactions observed on-chain (scanned from recent blocks).</p>
    </div>
</header>

<section class="card">
    <h2>Recent transactions</h2>

    @if(empty($txs) || !count($txs))
        <p>No transactions found or Ethereum provider not configured.</p>
    @else
        <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid rgba(0,0,0,0.08);">
                    <th>Hash</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Value (ETH)</th>
                    <th>Block</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach($txs as $tx)
                <tr style="border-bottom:1px solid rgba(0,0,0,0.04);">
                    <td><a href="https://etherscan.io/tx/{{ ltrim($tx['hash'] ?? '#', '0x') }}" target="_blank" rel="noopener">{{ $tx['hash'] }}</a></td>
                    <td>{{ $tx['from'] }}</td>
                    <td>{{ $tx['to'] }}</td>
                    <td>{{ $tx['value_eth'] }}</td>
                    <td>{{ $tx['block_number'] }}</td>
                    <td>{{ $tx['timestamp'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif

</section>

@endsection
