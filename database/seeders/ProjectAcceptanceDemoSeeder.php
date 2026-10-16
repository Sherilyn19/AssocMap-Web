<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ProjectAcceptanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            DB::table('projects')->where('remarks', 'Demo record para sa system testing at capstone presentation.')
                ->whereNull('proposal_accepted_on')->whereNotNull('implementation_date')
                ->where('is_archived', false)
                ->whereIn('association_id', DB::table('associations')->select('id')->where('is_archived', false)->where(fn ($query) => $query->whereNull('assistance_status')->orWhere('assistance_status', '<>', 'terminated')))
                ->update(['proposal_accepted_on' => DB::raw("LEAST(implementation_date - 14, CURRENT_DATE)"), 'remarks' => 'Demo record para sa system testing at capstone presentation. Proposal acceptance date is sample data derived from implementation date.']);
        });
    }
}
