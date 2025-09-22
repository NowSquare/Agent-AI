<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use App\Models\Thread;
use Illuminate\View\Component;

class ThreadMetadata extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public Thread $thread,
        public bool $showHistory = false,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.thread-metadata');
    }
}
