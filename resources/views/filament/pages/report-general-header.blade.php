<div class="space-y-6">
    <x-filament-panels::header
        :actions="$this->getCachedHeaderActions()"
        :breadcrumbs="filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : []"
        :heading="$this->getHeading()"
        :subheading="$this->getSubheading()"
    />

    {{ $this->filtersForm }}
</div>
