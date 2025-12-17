<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreationSource;
use App\Models\Concerns\HasAiSummary;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\HasTeam;
use App\Observers\CompanyObserver;
use App\Services\AvatarService;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $name
 * @property string $address
 * @property string $country
 * @property string $phone
 * @property Carbon|null $deleted_at
 * @property CreationSource $creation_source
 * @property-read string $created_by
 */
#[ObservedBy(CompanyObserver::class)]
final class Company extends Model implements HasCustomFields, HasMedia
{
    use HasAiSummary;
    use HasCreator;

    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    use HasNotes;
    use HasTeam;
    use InteractsWithMedia;
    use SoftDeletes;
    use UsesCustomFields;

    /**
     * @var list<string>
     */
    protected $casts = [
        'lead_score' => 'decimal:2',
        'er_score' => 'decimal:2',
        'posts_per_month' => 'integer',
        'smm_analysis' => 'array',
        'raw_response' => 'array',
        'smm_analysis_date' => 'datetime',
        'last_enrichment_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'annual_revenue' => 'decimal:2',
        'engagement_score' => 'decimal:2',
        'last_contacted_at' => 'datetime',
        'creation_source' => CreationSource::class,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'creator_id',
        'name',
        'address',
        'country',
        'phone',
        'website',
        'industry',
        'website',
        'vk_url',
        'vk_status',
        'lead_score',
        'er_score',
        'posts_per_month',
        'lead_category',
        'smm_analysis',
        'raw_response',

        // Address & Location
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country_code',
        'latitude',
        'longitude',
        'timezone',

        // Financial & Engagement
        'annual_revenue',
        'number_of_employees',
        'founded_year',
        'currency_code',
        'last_contacted_at',
        'engagement_score',

        // Legal Details
        'legal_name',
        'inn',
        'ogrn',
        'kpp',
        'management_name',
        'management_post',
        'okved',
        'status',

        // Marketing
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'marketing_campaign_id',
        'smm_analysis_date',
        'last_enrichment_at',
        'linkedin_url',
        'creation_source',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'creation_source' => CreationSource::WEB,
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'creation_source' => CreationSource::class,
        ];
    }

    public function getLogoAttribute(): string
    {
        $logo = $this->getFirstMediaUrl('logo');

        return $logo === '' || $logo === '0' ? app(AvatarService::class)->generateAuto(name: $this->name) : $logo;
    }

    /**
     * Team member responsible for managing the company account
     *
     * @return BelongsTo<User, $this>
     */
    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
    }

    /**
     * @return HasMany<People, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(People::class);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * @return MorphToMany<Task, $this>
     */
    public function tasks(): MorphToMany
    {
        return $this->morphToMany(Task::class, 'taskable');
    }
}
