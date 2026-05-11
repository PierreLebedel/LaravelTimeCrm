<?php

namespace App\Livewire;

use App\Enums\CalendarEventFormatStatus;
use App\Models\CalendarEvent;
use Livewire\Attributes\On;
use Livewire\Component;

class ReviewMenuItem extends Component
{
    #[On('review-queue-updated')]
    public function refreshCount(): void
    {
        // Trigger a re-render so the badge count is recalculated from the database.
    }

    public function render()
    {
        return view('livewire.review-menu-item', [
            'reviewCount' => CalendarEvent::query()
                ->where('format_status', CalendarEventFormatStatus::NeedsReview)
                ->count(),
        ]);
    }
}
