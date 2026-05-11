<x-menu-item
    title="Revue"
    icon="tabler.folder-exclamation"
    link="{{ route('review') }}"
    :badge="$reviewCount > 0 ? (string) $reviewCount : null"
    badge-classes="badge-warning"
/>
