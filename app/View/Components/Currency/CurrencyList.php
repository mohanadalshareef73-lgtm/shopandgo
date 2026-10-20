<?php

namespace App\View\Components\Currency;

use Illuminate\View\Component;

class CurrencyList extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public $currencies)
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
        return view('components.currency.currency-list');
    }
}
