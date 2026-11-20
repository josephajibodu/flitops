<?php

namespace App\Filament\Widgets;

use App\Enums\DeploymentStatus;
use App\Filament\Resources\Deployments\DeploymentResource;
use App\Models\Deployment;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Deployments that failed in the last day.
 */
class RecentFailedDeployments extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Failed deployments, last 24 hours';

    public function table(Table $table): Table
    {
        return $table
            ->query(Deployment::query()
                ->with(['site.server.team', 'user'])
                ->where('status', DeploymentStatus::Failed)
                ->where('created_at', '>=', now()->subDay()))
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->paginated([5])
            ->searchable(false)
            ->emptyStateHeading('No failed deployments')
            ->columns(DeploymentResource::columns())
            ->recordUrl(fn (Deployment $record): string => DeploymentResource::getUrl('view', ['record' => $record]));
    }
}
