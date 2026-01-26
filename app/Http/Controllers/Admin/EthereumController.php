<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EthereumService;

class EthereumController extends Controller
{
    public function index(EthereumService $eth)
    {
        if (! config('ethereum.enabled')) {
            abort(404);
        }

        $txs = $eth->getLatestTransactions(10);

        return view('admin.ethereum.index', ['txs' => $txs]);
    }
}
