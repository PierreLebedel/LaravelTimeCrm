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
                <a href="/" wire:navigate class="block">
                    <div {{ $attributes->class(["hidden-when-collapsed"]) }}>
                        <div class="flex items-baseline-last gap-2 w-full justify-between">
                            <div class="flex items-center justify-start gap-2">
                                <x-svg name="tabler-calendar-dollar" class="w-6 text-primary" />
                                <span class="text-2xl text-primary">
                                    TimeCRM
                                </span>
                            </div>
                            <span class="text-xs font-medium uppercase text-base-content/50">
                                v{{ config('nativephp.version') }}
                            </span>
                        </div>
                    </div>

                    <div class="display-when-collapsed hidden mx-5 mt-5 mb-1 h-[28px]">
                        <x-svg name="tabler-calendar-dollar" class="w-6 text-primary" />
                    </div>
                </a>
            HTML;
    }
}
