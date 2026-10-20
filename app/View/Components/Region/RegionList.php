<?php

namespace App\View\Components\Region;

use App\Models\Product;
use Illuminate\View\Component;

class RegionList extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public $regions ,public $product)
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
        return view('components.region.region-list');
    }
}
