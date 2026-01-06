<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        $perPage = (int) $request->query('per_page', 12);
        if ($perPage <= 0) {
            $perPage = 12;
        }
        $perPage = min(max($perPage, 5), 200);

        return view('admin.products.index', [
            'products' => Product::orderBy('name')->paginate($perPage),
        ]);
    }

    public function create(): View
    {
        $media = \App\Models\Media::orderBy('created_at', 'desc')->limit(50)->get();
        return view('admin.products.create', compact('media'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (! Schema::hasColumn('products', 'media_id')) {
            unset($data['media_id']);
        }
        Product::create($data);

        $qs = http_build_query($request->only(['page', 'per_page']));
        $url = route('admin.products.index') . ($qs ? "?{$qs}" : '');

        return redirect($url)->with('status', 'Product added to the catalog.');
    }

    public function edit(Product $product): View
    {
        $media = \App\Models\Media::orderBy('created_at', 'desc')->limit(50)->get();
        return view('admin.products.edit', compact('product', 'media'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        if (! Schema::hasColumn('products', 'media_id')) {
            unset($data['media_id']);
        }
        $product->update($data);

        $qs = http_build_query($request->only(['page', 'per_page']));
        $url = route('admin.products.index') . ($qs ? "?{$qs}" : '');

        return redirect($url)->with('status', 'Product updated.');
    }

    public function destroy(\Illuminate\Http\Request $request, Product $product): RedirectResponse
    {
        if ($product->licenses()->exists()) {
            $qs = http_build_query($request->only(['page', 'per_page']));
            $url = route('admin.products.index') . ($qs ? "?{$qs}" : '');
            return redirect($url)->with('status', 'Cannot delete a product with attached licenses.');
        }

        $product->delete();

        $qs = http_build_query($request->only(['page', 'per_page']));
        $url = route('admin.products.index') . ($qs ? "?{$qs}" : '');

        return redirect($url)->with('status', 'Product removed.');
    }
}
