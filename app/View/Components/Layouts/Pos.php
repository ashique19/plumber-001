<?php

namespace App\View\Components\Layouts;

use App\Models\Branch;
use Illuminate\View\Component;
use Illuminate\View\View;

class Pos extends Component
{
    public function __construct(
        public ?Branch $branch = null,
    ) {}

    public function render(): View
    {
        return view('layouts.pos', [
            'branch' => $this->branch,
        ]);
    }
}
