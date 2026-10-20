<?php

namespace App\View\Components\Category;

use Illuminate\View\Component;

class CategoryList extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public $categories)
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
        return view('components.category.category-list');
    }
}
