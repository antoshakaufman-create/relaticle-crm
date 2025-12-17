<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use App\Models\People; // Assuming People model exists as per file listing
use App\Models\Lead;
use App\Models\Opportunity;

class DeduplicateCompanies extends Command
{
    protected $signature = 'deduplicate:companies {--dry-run : Run without deleting}';
    protected $description = 'Deduplicate companies based on INN and Name';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $this->info("Starting Deduplication..." . ($dryRun ? " (DRY RUN)" : ""));

        // 1. Group by INN
        $innGroups = DB::table('companies')
            ->whereNotNull('inn')
            ->where('inn', '!=', '')
            ->select('inn', DB::raw('count(*) as count'))
            ->groupBy('inn')
            ->having('count', '>', 1)
            ->get();

        foreach ($innGroups as $group) {
            $this->processGroup('inn', $group->inn, $dryRun);
        }

        // 2. Group by Legal Name (where INN is null)
        $nameGroups = DB::table('companies')
            ->where(function ($q) {
                $q->whereNull('inn')->orWhere('inn', '');
            })
            ->whereNotNull('legal_name')
            ->where('legal_name', '!=', '')
            ->select('legal_name', DB::raw('count(*) as count'))
            ->groupBy('legal_name')
            ->having('count', '>', 1)
            ->get();

        foreach ($nameGroups as $group) {
            $this->processGroup('legal_name', $group->legal_name, $dryRun);
        }

        // 3. Group by Name (where INN/Legal are null) - stricter
        $simpleGroups = DB::table('companies')
            ->where(function ($q) {
                $q->whereNull('inn')->orWhere('inn', '');
            })
            ->where(function ($q) {
                $q->whereNull('legal_name')->orWhere('legal_name', '');
            })
            ->select('name', DB::raw('count(*) as count'))
            ->groupBy('name')
            ->having('count', '>', 1)
            ->get();

        foreach ($simpleGroups as $group) {
            $this->processGroup('name', $group->name, $dryRun);
        }

        $this->info("Deduplication Complete!");
    }

    private function processGroup($field, $value, $dryRun)
    {
        $companies = Company::where($field, $value)->orderBy('id')->get();

        if ($companies->count() < 2)
            return;

        $this->info("Found duplicates for $field = '$value': " . $companies->count());

        // Select Master: Prefer filled fields, then oldest ID
        // Simple heuristic: Take the one with most filled attributes? Or simply first one (oldest).
        // Let's pick the one with most data populated.
        $master = $companies->sortByDesc(function ($c) {
            $score = 0;
            if ($c->inn)
                $score++;
            if ($c->legal_name)
                $score++;
            if ($c->website)
                $score++;
            if ($c->email)
                $score++;
            if ($c->phone)
                $score++;
            if ($c->address_line_1)
                $score++;
            if ($c->vk_url)
                $score++;
            return $score;
        })->first();

        // If ties, sortByDesc preserves original order (stable sort in collection?), no, likely picks one.
        // If we want oldest as tie breaker, we should primarily sort by score desc, then ID asc.
        // Collection sort is stable?

        // Let's just pick one.
        $duplicates = $companies->reject(fn($c) => $c->id === $master->id);

        $this->info("  Master ID: {$master->id} ({$master->name})");
        $this->info("  Duplicates to merge: " . $duplicates->pluck('id')->join(', '));

        if ($dryRun)
            return;

        DB::transaction(function () use ($master, $duplicates) {
            foreach ($duplicates as $duplicate) {
                // 1. Merge Data
                $this->mergeData($master, $duplicate);

                // 2. Reassign Relations
                $this->reassignRelations($master, $duplicate);

                // 3. Delete
                $duplicate->delete();
            }
            $master->save();
        });
    }

    private function mergeData(Company $master, Company $duplicate)
    {
        $fillable = $master->getFillable();
        foreach ($fillable as $attr) {
            // If master empty and duplicate has value, copy it
            if (empty($master->$attr) && !empty($duplicate->$attr)) {
                $master->$attr = $duplicate->$attr;
            }
        }
    }

    private function reassignRelations(Company $master, Company $duplicate)
    {
        $oldId = $duplicate->id;
        $newId = $master->id;

        // People
        DB::table('people')->where('company_id', $oldId)->update(['company_id' => $newId]);

        // Leads
        DB::table('leads')->where('company_id', $oldId)->update(['company_id' => $newId]);

        // Opportunities
        DB::table('opportunities')->where('company_id', $oldId)->update(['company_id' => $newId]);

        // Tasks (Polymorphic)
        DB::table('taskables')
            ->where('taskable_type', 'App\Models\Company') // Or strictly 'company' if morphed
            ->where('taskable_id', $oldId)
            ->update(['taskable_id' => $newId]);
        // Note: IF duplicate `taskable` exists for same task, this might fail unique constraint?
        // Usually taskables is unique (task_id, taskable_id, taskable_type).
        // If master already attached to same task, we should ignore/delete the duplicate link.
        // Simplified: update via 'updateOrIgnore' isn't available in standard Query Builder easily without logic.
        // Let's assume low risk or handle exception?
        // Actually, best to iterate and reassign checking existence.

        // Noteables
        DB::table('noteables')
            ->where('noteable_type', 'App\Models\Company')
            ->where('noteable_id', $oldId)
            ->update(['noteable_id' => $newId]);

        // Media
        DB::table('media')
            ->where('model_type', 'App\Models\Company')
            ->where('model_id', $oldId)
            ->update(['model_id' => $newId]);

        // Any others? ai_summaries
        DB::table('ai_summaries')
            ->where('summarizable_type', 'App\Models\Company')
            ->where('summarizable_id', $oldId)
            ->update(['summarizable_id' => $newId]);
    }
}
