<?php

namespace App\View\Components\Provider;

use App\Models\Provider;
use Illuminate\View\Component;

class ProviderForm extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public ?Provider $provider)
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
        return view('components.provider.provider-form');
    }
}
