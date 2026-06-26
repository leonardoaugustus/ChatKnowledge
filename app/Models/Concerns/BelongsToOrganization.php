<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\ActiveOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if ($organizationId = app(ActiveOrganization::class)->id()) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $organizationId);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->organization_id) && $organizationId = app(ActiveOrganization::class)->id()) {
                $model->organization_id = $organizationId;
            }
        });
    }

    /**
     * Get the organization that owns the model.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
