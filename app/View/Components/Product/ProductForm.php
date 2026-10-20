<?php

namespace App\View\Components\Product;

use App\Models\Product;
use Illuminate\View\Component;

class ProductForm extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public ?Product $product,public $providers,public $categories)
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.product.product-form');
    }
}
