<?php

namespace App\View\Components\Classification;

use Illuminate\View\Component;

class ClassificationList extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public $region,public $classifications)
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
        return view('components.classification.classification-list');
    }
}
