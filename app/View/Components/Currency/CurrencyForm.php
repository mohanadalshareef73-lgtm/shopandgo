<?php

namespace App\View\Components\Currency;

use App\Models\Currency;
use Illuminate\View\Component;

class CurrencyForm extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public ?Currency $currency)
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
        return view('components.currency.currency-form');
    }
}
