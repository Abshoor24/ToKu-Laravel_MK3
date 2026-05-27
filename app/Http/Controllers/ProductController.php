<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Http\Resources\ProductResource;
use App\Traits\ApiResponseTrait;

class ProductController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $products = Product::latest()->get();

        return $this->successResponse(
            ProductResource::collection($products),
            'Products fetched'
        );
    }

    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        return $this->successResponse(
            new ProductResource($product),
            'Product detail fetched'
        );
    }

    public function categories()
    {
        $categories =  Product::select('category')
        ->distinct()
        ->whereNotNull('category')
        ->pluck('category');

        return $this->successResponse($categories, 'Categories fetched');
    }

    public function brand()
    {
        $brand = Product::select('brand')
        ->distinct()
        ->whereNotNull('brand')
        ->pluck('brand');

        
        return $this->successResponse($brand, 'Brands fetched');
    }

}