<?php

namespace App\View\Components\Region;

use App\Models\Region;
use Illuminate\View\Component;

class RegionForm extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public ?Region $region, public $product )
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
        return view('components.region.region-form');
    }
}
