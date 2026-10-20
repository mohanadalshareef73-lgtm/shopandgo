<?php

namespace App\View\Components\Classification;

use App\Models\Classification;
use Illuminate\View\Component;

class ClassificationForm extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public ?Classification $classification,public $region,public $externalClassifications,public $currencies,public $paymentTypes)
    {
        // dd($this->externalClassifications);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.classification.classification-form');
    }
}
