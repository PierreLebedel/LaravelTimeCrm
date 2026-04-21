<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AppBrand extends Component
{
    public function render(): View|Closure|string
    {
        return <<<'HTML'
                <a href="/" wire:navigate>
                    <div {{ $attributes->class(["hidden-when-collapsed"]) }}>
                        <div class="flex items-center gap-2 w-fit">
                            <x-svg name="tabler-calendar-week" class="w-6 text-primary" />
                            <span class="font-bold text-2xl me-3">
                                TimeCRM
                            </span>
                        </div>
                    </div>

                    <div class="display-when-collapsed hidden mx-5 mt-5 mb-1 h-[28px]">
                        <x-svg name="tabler-calendar-week" class="w-6 text-primary" />
                    </div>
                </a>
            HTML;
    }
}
