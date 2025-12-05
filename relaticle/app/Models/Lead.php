<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreationSource;
use App\Enums\LeadValidationStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $creator_id
 * @property int|null $company_id
 * @property int|null $people_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company_name
 * @property string|null $position
 * @property string|null $linkedin_url
 * @property string|null $vk_url
 * @property string|null $telegram_username
 * @property string $source
 * @property string|null $source_details
 * @property string $validation_status
 * @property int|null $validation_score
 * @property array|null $validation_errors
 * @property array|null $enrichment_data
 * @property bool $email_verified
 * @property bool $phone_verified
 * @property bool $company_verified
 * @property bool $linkedin_verified
 * @property bool $vk_verified
 * @property bool $telegram_verified
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $creator
 * @property-read Company|null $company
 * @property-read People|null $people
 */
final class Lead extends Model
{
    use HasCreator;
    use HasFactory;
    use HasTeam;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'creator_id',
        'company_id',
        'people_id',
        'name',
        'email',
        'phone',
        'company_name',
        'position',
        'linkedin_url',
        'vk_url',
        'telegram_username',
        'source',
        'source_details',
        'validation_status',
        'validation_score',
        'validation_errors',
        'enrichment_data',
        'email_verified',
        'phone_verified',
        'company_verified',
        'linkedin_verified',
        'vk_verified',
        'telegram_verified',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'validation_status' => LeadValidationStatus::class,
            'validation_errors' => 'array',
            'enrichment_data' => 'array',
            'email_verified' => 'boolean',
            'phone_verified' => 'boolean',
            'company_verified' => 'boolean',
            'linkedin_verified' => 'boolean',
            'vk_verified' => 'boolean',
            'telegram_verified' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<People, $this>
     */
    public function people(): BelongsTo
    {
        return $this->belongsTo(People::class);
    }

    /**
     * Конвертировать лид в контакт (People)
     */
    public function convertToPeople(): People
    {
        $people = People::create([
            'team_id' => $this->team_id,
            'creator_id' => $this->creator_id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'creation_source' => CreationSource::SYSTEM,
        ]);

        // Обновляем связь
        $this->update(['people_id' => $people->id]);

        return $people;
    }

    /**
     * Конвертировать лид в компанию (Company)
     */
    public function convertToCompany(): Company
    {
        $company = Company::create([
            'team_id' => $this->team_id,
            'creator_id' => $this->creator_id,
            'name' => $this->company_name,
            'creation_source' => CreationSource::SYSTEM,
        ]);

        // Обновляем связь
        $this->update(['company_id' => $company->id]);

        return $company;
    }
}



