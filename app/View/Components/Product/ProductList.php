<?php

namespace App\View\Components\Product;

use App\Models\Category;
use App\Models\Provider;
use Illuminate\View\Component;

class ProductList extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public $products,public $providers,public $categories)
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
        return view('components.product.product-list');
    }
}
