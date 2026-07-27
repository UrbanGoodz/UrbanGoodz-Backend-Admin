<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorBusinessDirectoryService
{
    public const TABS = [
        'accounts' => 'All Vendor Accounts',
        'active-stores' => 'Active Stores',
        'pending-onboarding' => 'Pending Onboarding',
        'missing-store' => 'Missing Store',
        'business-clients' => 'Business Clients',
        'service-providers' => 'Service Providers',
        'rental-providers' => 'Rental Providers',
        'creators' => 'Creators',
        'imported-demo' => 'Imported/Demo',
        'data-issues' => 'Data Issues',
    ];

    public function summary(): array
    {
        if (!Schema::hasTable('vendors') || !Schema::hasTable('stores')) {
            return array_fill_keys([
                'vendor_accounts', 'active_vendors', 'stores', 'active_stores',
                'pending_vendors', 'orphaned_vendors', 'business_clients',
                'service_providers', 'rental_providers', 'creators', 'suspended',
                'imported_demo', 'data_issues', 'eligible_without_offerings',
                'unverified_lifecycle',
            ], 0);
        }

        $activeStores = $this->activeStoreQuery();

        return [
            'vendor_accounts' => DB::table('vendors')->count(),
            'active_vendors' => (clone $activeStores)->distinct()->count('s.vendor_id'),
            'stores' => DB::table('stores')->count(),
            'active_stores' => (clone $activeStores)->count('s.id'),
            'pending_vendors' => $this->pendingVendorQuery()->distinct()->count('v.id'),
            'orphaned_vendors' => DB::table('vendors as v')
                ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                    ->from('stores as os')->whereColumn('os.vendor_id', 'v.id'))
                ->count(),
            'business_clients' => $this->tableCount('urban_goodz_business_clients', true),
            'service_providers' => $this->tableCount('urban_goodz_service_providers'),
            'rental_providers' => DB::table('stores as s')
                ->join('modules as m', 'm.id', '=', 's.module_id')
                ->where('m.module_type', 'rental')->distinct()->count('s.vendor_id'),
            'creators' => $this->tableCount('urban_goodz_creator_applications'),
            'suspended' => DB::table('vendors as v')
                ->where(function (Builder $query) {
                    $query->where('v.status', '<>', 1)->orWhereNull('v.status')
                        ->orWhereExists(fn (Builder $stores) => $stores->selectRaw('1')->from('stores as ss')
                            ->whereColumn('ss.vendor_id', 'v.id')->where('ss.status', '<>', 1));
                })->distinct()->count('v.id'),
            'imported_demo' => $this->importedDemoVendorQuery()->distinct()->count('v.id'),
            'data_issues' => $this->dataIssueVendorQuery()->distinct()->count('v.id'),
            'eligible_without_offerings' => $this->structurallyEligibleStoreQuery()
                ->whereNotExists(fn (Builder $items) => $items->selectRaw('1')->from('items as ei')
                    ->whereColumn('ei.store_id', 's.id')->where('ei.status', 1)->where('ei.is_approved', 1))
                ->count('s.id'),
            'unverified_lifecycle' => $this->unverifiedLifecycleStoreQuery()->count('s.id'),
        ];
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $tab = array_key_exists($filters['tab'] ?? '', self::TABS) ? $filters['tab'] : 'accounts';
        $requestedPerPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($requestedPerPage, [25, 50, 100], true) ? $requestedPerPage : 25;

        $query = match ($tab) {
            'business-clients' => $this->businessClientQuery(),
            'service-providers' => $this->serviceProviderQuery(),
            'creators' => $this->creatorQuery(),
            default => $this->vendorAccountQuery(),
        };

        if (!in_array($tab, ['business-clients', 'service-providers', 'creators'], true)) {
            $this->applyVendorTab($query, $tab);
            $this->applyVendorFilters($query, $filters);
        } else {
            $this->applyRoleSearch($query, (string) ($filters['search'] ?? ''));
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumn = match ($sort) {
            'name' => 'business_name',
            'status' => 'active_status',
            'store_count' => 'store_count',
            default => 'created_at',
        };

        return $query->orderBy($sortColumn, $direction)->paginate($perPage)->withQueryString();
    }

    private function vendorAccountQuery(): Builder
    {
        $storeStats = DB::table('stores as sx')
            ->select('sx.vendor_id')
            ->selectRaw('COUNT(*) AS store_count')
            ->selectRaw('MIN(sx.id) AS primary_store_id')
            ->selectRaw('SUM(CASE WHEN EXISTS (
                SELECT 1 FROM items ix
                WHERE ix.store_id = sx.id AND ix.status = 1 AND ix.is_approved = 1
            ) THEN 1 ELSE 0 END) AS offering_count')
            ->selectRaw('SUM(CASE WHEN sx.status = 1 THEN 1 ELSE 0 END) AS active_store_count')
            ->groupBy('sx.vendor_id');

        $orderStats = DB::table('stores as so')
            ->leftJoin('orders as ox', 'ox.store_id', '=', 'so.id')
            ->select('so.vendor_id')->selectRaw('COUNT(ox.id) AS orders_count')
            ->groupBy('so.vendor_id');

        return DB::table('vendors as v')
            ->leftJoinSub($storeStats, 'vs', 'vs.vendor_id', '=', 'v.id')
            ->leftJoin('stores as s', 's.id', '=', 'vs.primary_store_id')
            ->leftJoin('modules as m', 'm.id', '=', 's.module_id')
            ->leftJoin('zones as z', 'z.id', '=', 's.zone_id')
            ->leftJoinSub($orderStats, 'vo', 'vo.vendor_id', '=', 'v.id')
            ->selectRaw("'vendor_account' AS record_type")
            ->selectRaw('v.id AS record_id, v.id AS vendor_id, s.id AS primary_store_id, s.module_id')
            ->selectRaw("TRIM(CONCAT(COALESCE(v.f_name, ''), ' ', COALESCE(v.l_name, ''))) AS account_owner")
            ->selectRaw("COALESCE(s.name, '[No store]') AS business_name")
            ->selectRaw("'Vendor Account' AS role_type")
            ->selectRaw('v.email, v.phone, NULL AS city, z.name AS zone_name, m.module_name')
            ->selectRaw("COALESCE(s.admin_approval_status, 'not_started') AS approval_status")
            ->selectRaw("CASE WHEN v.status = 1 THEN 'active' ELSE 'inactive' END AS active_status")
            ->selectRaw('COALESCE(vs.store_count, 0) AS store_count')
            ->selectRaw('COALESCE(vs.offering_count, 0) AS offering_count')
            ->selectRaw('COALESCE(vo.orders_count, 0) AS orders_count')
            ->selectRaw('v.created_at')
            ->selectRaw($this->classificationSql().' AS classification')
            ->selectRaw($this->dataIssueSql().' AS data_issue');
    }

    private function businessClientQuery(): Builder
    {
        if (!Schema::hasTable('urban_goodz_business_clients')) {
            return $this->emptyNormalizedQuery('business_client');
        }

        return DB::table('urban_goodz_business_clients as b')
            ->whereNull('b.deleted_at')
            ->selectRaw("'business_client' AS record_type, b.id AS record_id, NULL AS vendor_id")
            ->selectRaw('NULL AS primary_store_id, NULL AS module_id')
            ->selectRaw("COALESCE(b.company_name, '') AS account_owner, b.company_name AS business_name")
            ->selectRaw("'Business Client' AS role_type, b.email, b.phone, b.city")
            ->selectRaw('NULL AS zone_name, NULL AS module_name')
            ->selectRaw("COALESCE(b.status, 'pending') AS approval_status, COALESCE(b.status, 'pending') AS active_status")
            ->selectRaw('0 AS store_count, 0 AS offering_count, 0 AS orders_count, b.created_at')
            ->selectRaw("'business_client' AS classification, NULL AS data_issue");
    }

    private function serviceProviderQuery(): Builder
    {
        if (!Schema::hasTable('urban_goodz_service_providers')) {
            return $this->emptyNormalizedQuery('service_provider');
        }

        $hasApproval = Schema::hasColumn('urban_goodz_service_providers', 'approval_status');
        $hasVendor = Schema::hasColumn('urban_goodz_service_providers', 'vendor_id');

        return DB::table('urban_goodz_service_providers as p')
            ->selectRaw("'service_provider' AS record_type, p.id AS record_id, ".($hasVendor ? 'p.vendor_id' : 'NULL').' AS vendor_id')
            ->selectRaw('NULL AS primary_store_id, NULL AS module_id')
            ->selectRaw("COALESCE(p.contact_name, p.business_name) AS account_owner, p.business_name")
            ->selectRaw("'Service Provider' AS role_type, p.email, p.phone, NULL AS city")
            ->selectRaw('NULL AS zone_name, p.service_category AS module_name')
            ->selectRaw(($hasApproval ? "COALESCE(p.approval_status, 'pending')" : "CASE WHEN p.is_verified = 1 THEN 'approved' ELSE 'pending' END").' AS approval_status')
            ->selectRaw("CASE WHEN p.is_active = 1 THEN 'active' ELSE 'inactive' END AS active_status")
            ->selectRaw('0 AS store_count, 0 AS offering_count, 0 AS orders_count, p.created_at')
            ->selectRaw("'service_provider' AS classification, NULL AS data_issue");
    }

    private function creatorQuery(): Builder
    {
        if (!Schema::hasTable('urban_goodz_creator_applications')) {
            return $this->emptyNormalizedQuery('creator');
        }

        return DB::table('urban_goodz_creator_applications as c')
            ->selectRaw("'creator' AS record_type, c.id AS record_id, NULL AS vendor_id")
            ->selectRaw('NULL AS primary_store_id, NULL AS module_id')
            ->selectRaw('c.creator_name AS account_owner, c.creator_name AS business_name')
            ->selectRaw("'Creator' AS role_type, c.email, c.phone, c.city")
            ->selectRaw('NULL AS zone_name, c.platform AS module_name')
            ->selectRaw("COALESCE(c.status, 'pending') AS approval_status, COALESCE(c.status, 'pending') AS active_status")
            ->selectRaw('0 AS store_count, 0 AS offering_count, 0 AS orders_count, c.created_at')
            ->selectRaw("'creator' AS classification, NULL AS data_issue");
    }

    private function applyVendorTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'active-stores' => $query->whereExists(fn (Builder $active) => $this->qualifyingStoreExists($active, 'v.id')),
            'pending-onboarding' => $this->applyPendingVendorConditions($query),
            'missing-store' => $query->whereNotExists(fn (Builder $stores) => $stores->selectRaw('1')
                ->from('stores as ms')->whereColumn('ms.vendor_id', 'v.id')),
            'rental-providers' => $query->whereExists(fn (Builder $rental) => $rental->selectRaw('1')
                ->from('stores as rs')->join('modules as rm', 'rm.id', '=', 'rs.module_id')
                ->whereColumn('rs.vendor_id', 'v.id')->where('rm.module_type', 'rental')),
            'imported-demo' => $this->applyImportedDemoConditions($query),
            'data-issues' => $this->applyDataIssueConditions($query),
            default => null,
        };
    }

    private function applyVendorFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $where) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $where->where('v.f_name', 'like', $like)->orWhere('v.l_name', 'like', $like)
                    ->orWhere('v.email', 'like', $like)->orWhere('v.phone', 'like', $like)
                    ->orWhere('s.name', 'like', $like);
            });
        }

        if (is_numeric($filters['module_id'] ?? null)) {
            $query->where('s.module_id', (int) $filters['module_id']);
        }
        if (is_numeric($filters['zone_id'] ?? null)) {
            $query->where('s.zone_id', (int) $filters['zone_id']);
        }
        if (($filters['status'] ?? '') === 'active') {
            $query->where('v.status', 1);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where(function (Builder $status) {
                $status->where('v.status', '<>', 1)->orWhereNull('v.status');
            });
        } elseif (($filters['status'] ?? '') === 'suspended') {
            $query->where(function (Builder $status) {
                $status->where('v.status', '<>', 1)->orWhereNull('v.status')
                    ->orWhereExists(fn (Builder $stores) => $stores->selectRaw('1')->from('stores as fs')
                        ->whereColumn('fs.vendor_id', 'v.id')->where('fs.status', '<>', 1));
            });
        }
    }

    private function applyRoleSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $like = '%'.addcslashes($search, '%_\\').'%';
        $query->having(function (Builder $where) use ($like) {
            $where->where('business_name', 'like', $like)
                ->orWhere('account_owner', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }

    private function activeStoreQuery(): Builder
    {
        return $this->structurallyEligibleStoreQuery()
            ->whereExists(fn (Builder $items) => $items->selectRaw('1')->from('items as ai')
                ->whereColumn('ai.store_id', 's.id')->where('ai.status', 1)->where('ai.is_approved', 1));
    }

    private function structurallyEligibleStoreQuery(): Builder
    {
        return DB::table('stores as s')
            ->join('vendors as v', 'v.id', '=', 's.vendor_id')
            ->join('modules as m', 'm.id', '=', 's.module_id')
            ->join('zones as z', 'z.id', '=', 's.zone_id')
            ->where('v.status', 1)->where('s.status', 1)->where('m.status', 1)->where('z.status', 1)
            ->where(function (Builder $businessModel) {
                $businessModel->where('s.store_business_model', 'commission')
                    ->orWhereExists(fn (Builder $subscription) => $subscription->selectRaw('1')
                        ->from('store_subscriptions as sub')->whereColumn('sub.store_id', 's.id')
                        ->where(function (Builder $limit) {
                            $limit->where('sub.max_order', 'unlimited')->orWhere('sub.max_order', '>', 0);
                        }));
            })
            ->whereExists(fn (Builder $moduleZone) => $moduleZone->selectRaw('1')->from('module_zone as mz')
                ->whereColumn('mz.module_id', 's.module_id')->whereColumn('mz.zone_id', 's.zone_id'));
    }

    private function pendingVendorQuery(): Builder
    {
        $query = DB::table('vendors as v');
        $this->applyPendingVendorConditions($query);
        return $query;
    }

    private function applyPendingVendorConditions(Builder $query): void
    {
        $query->where(function (Builder $pending) {
            $pending->where('v.status', '<>', 1)->orWhereNull('v.status')
                ->orWhereNotExists(fn (Builder $stores) => $stores->selectRaw('1')->from('stores as ps')
                    ->whereColumn('ps.vendor_id', 'v.id'))
                ->orWhereExists(fn (Builder $stores) => $stores->selectRaw('1')->from('stores as ps')
                    ->whereColumn('ps.vendor_id', 'v.id')
                    ->where('ps.admin_approval_status', '<>', 'approved'));
        });
    }

    private function importedDemoVendorQuery(): Builder
    {
        $query = DB::table('vendors as v');
        $this->applyImportedDemoConditions($query);
        return $query;
    }

    private function applyImportedDemoConditions(Builder $query): void
    {
        $query->whereExists(function (Builder $stores) {
            $stores->selectRaw('1')->from('stores as ds')
                ->leftJoin('modules as dm', 'dm.id', '=', 'ds.module_id')
                ->whereColumn('ds.vendor_id', 'v.id')
                ->where(function (Builder $flag) {
                    $flag->where('ds.is_public_sourced', 1)->orWhere('dm.status', '<>', 1)
                        ->orWhereNull('dm.id');
                });
        });
    }

    private function dataIssueVendorQuery(): Builder
    {
        $query = DB::table('vendors as v');
        $this->applyDataIssueConditions($query);
        return $query;
    }

    private function applyDataIssueConditions(Builder $query): void
    {
        $query->where(function (Builder $issues) {
            $issues->whereNotExists(fn (Builder $stores) => $stores->selectRaw('1')->from('stores as di')
                ->whereColumn('di.vendor_id', 'v.id'))
                ->orWhereExists(function (Builder $stores) {
                    $stores->selectRaw('1')->from('stores as di')
                        ->leftJoin('modules as dim', 'dim.id', '=', 'di.module_id')
                        ->leftJoin('zones as diz', 'diz.id', '=', 'di.zone_id')
                        ->whereColumn('di.vendor_id', 'v.id')
                        ->where(function (Builder $invalid) {
                            $invalid->whereNull('dim.id')->orWhereNull('diz.id')
                                ->orWhere(function (Builder $lifecycle) {
                                    $lifecycle->where('di.is_claimed', 1)->whereNull('di.claimed_at');
                                })
                                ->orWhere(function (Builder $offering) {
                                    $offering->where('di.status', 1)
                                        ->whereNotExists(fn (Builder $items) => $items->selectRaw('1')->from('items as dix')
                                            ->whereColumn('dix.store_id', 'di.id')
                                            ->where('dix.status', 1)->where('dix.is_approved', 1));
                                });
                        });
                });
        });
    }

    private function unverifiedLifecycleStoreQuery(): Builder
    {
        return DB::table('stores as s')->where(function (Builder $query) {
            $query->where(fn (Builder $claimed) => $claimed->where('s.is_claimed', 1)->whereNull('s.claimed_at'))
                ->orWhere(fn (Builder $approved) => $approved->where('s.admin_approval_status', 'approved')->whereNull('s.admin_approved_at'))
                ->orWhere(fn (Builder $contracted) => $contracted->where('s.contract_status', 'contracted')->whereNull('s.contracted_at'));
        });
    }

    private function qualifyingStoreExists(Builder $query, string $vendorColumn): Builder
    {
        return $query->selectRaw('1')->from('stores as qs')
            ->join('modules as qm', 'qm.id', '=', 'qs.module_id')
            ->join('zones as qz', 'qz.id', '=', 'qs.zone_id')
            ->whereColumn('qs.vendor_id', $vendorColumn)
            ->where('qs.status', 1)->where('qm.status', 1)->where('qz.status', 1)
            ->whereExists(fn (Builder $items) => $items->selectRaw('1')->from('items as qi')
                ->whereColumn('qi.store_id', 'qs.id')->where('qi.status', 1)->where('qi.is_approved', 1))
            ->whereExists(fn (Builder $moduleZone) => $moduleZone->selectRaw('1')->from('module_zone as qmz')
                ->whereColumn('qmz.module_id', 'qs.module_id')->whereColumn('qmz.zone_id', 'qs.zone_id'));
    }

    private function classificationSql(): string
    {
        return "CASE
            WHEN COALESCE(vs.store_count, 0) = 0 THEN 'missing_store'
            WHEN v.status <> 1 OR v.status IS NULL THEN 'inactive_vendor'
            WHEN s.status <> 1 THEN 'inactive_store'
            WHEN m.id IS NULL THEN 'missing_module'
            WHEN z.id IS NULL THEN 'missing_zone'
            WHEN m.status <> 1 THEN 'inactive_module'
            WHEN COALESCE(vs.offering_count, 0) = 0 THEN 'no_active_offering'
            ELSE 'active_store_with_offering'
        END";
    }

    private function dataIssueSql(): string
    {
        return "CASE
            WHEN COALESCE(vs.store_count, 0) = 0 THEN 'Missing customer-facing store'
            WHEN m.id IS NULL THEN 'Missing module relationship'
            WHEN z.id IS NULL THEN 'Missing zone relationship'
            WHEN m.status <> 1 THEN 'Store belongs to an inactive module'
            WHEN COALESCE(vs.offering_count, 0) = 0 THEN 'No active approved offering'
            WHEN s.is_claimed = 1 AND s.claimed_at IS NULL THEN 'Claimed flag lacks lifecycle evidence'
            WHEN s.admin_approval_status = 'approved' AND s.admin_approved_at IS NULL THEN 'Approval flag lacks lifecycle evidence'
            ELSE NULL
        END";
    }

    private function emptyNormalizedQuery(string $recordType): Builder
    {
        return DB::table('vendors as empty_source')->whereRaw('1 = 0')
            ->selectRaw("'{$recordType}' AS record_type, NULL AS record_id, NULL AS vendor_id")
            ->selectRaw('NULL AS primary_store_id, NULL AS module_id, NULL AS account_owner')
            ->selectRaw('NULL AS business_name, NULL AS role_type, NULL AS email, NULL AS phone')
            ->selectRaw('NULL AS city, NULL AS zone_name, NULL AS module_name, NULL AS approval_status')
            ->selectRaw('NULL AS active_status, 0 AS store_count, 0 AS offering_count, 0 AS orders_count')
            ->selectRaw('NULL AS created_at, NULL AS classification, NULL AS data_issue');
    }

    private function tableCount(string $table, bool $softDeletes = false): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        if ($softDeletes && Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->count();
    }
}
