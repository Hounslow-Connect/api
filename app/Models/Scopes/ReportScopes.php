<?php

namespace App\Models\Scopes;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait ReportScopes
{
    /**
     * User Export Report query
     *
     * @return \Illuminate\Support\Collection
     **/
    public function getUserExportResults()
    {
        $sql = <<<'EOT'
CASE `id`
    WHEN ? THEN 1
    WHEN ? THEN 2
    WHEN ? THEN 3
    WHEN ? THEN 4
    WHEN ? THEN 5
    ELSE 6
END
EOT;

        $bindings = [
            Role::superAdmin()->id,
            Role::globalAdmin()->id,
            Role::organisationAdmin()->id,
            Role::serviceAdmin()->id,
            Role::serviceWorker()->id,
        ];

        $rolesQuery = DB::table('roles')
        ->select([
            'id',
            'name'
        ])
        ->selectRaw("$sql as value", $bindings);

        $query = DB::table('users')
        ->select([
            'users.id as id',
            'users.first_name as first_name',
            'users.last_name as last_name',
            'users.email as email'
        ])
        ->selectRaw('substring_index(group_concat(distinct all_roles.name ORDER BY all_roles.value), ",", 1) max_role')
        ->selectRaw('trim(trailing "," from replace(replace(replace(replace(group_concat(distinct all_roles.name ORDER BY all_roles.value),?,""),?,""),?,""),",,",",")) all_permissions', [Role::NAME_SUPER_ADMIN, Role::NAME_GLOBAL_ADMIN, Role::NAME_SERVICE_WORKER])
        ->selectRaw('concat_ws(",",group_concat(distinct user_roles.organisation_id), group_concat(distinct user_roles.service_id)) org_service_ids')
        ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
        ->joinSub($rolesQuery, 'all_roles', function ($join) {
            $join->on('all_roles.id', '=', 'user_roles.role_id');
        })
        ->groupBy('users.id');

        return $query->get();
    }

    /**
     * Service Export Report query
     *
     * @return \Illuminate\Support\Collection
     **/
    public function getServiceExportResults()
    {
        $query = DB::table('services')
        ->select([
            'organisations.name as organisation_name',
            'organisations.id as organisation_id',
            'organisations.email as organisation_email',
            'organisations.phone as organisation_phone',
            'services.id as service_id',
            'services.name as service_name',
            'services.url as service_url',
            'services.contact_name as service_contact_name',
            'services.updated_at as service_updated_at',
            'services.referral_method as service_referral_method',
            'services.referral_email as service_referral_email',
            'services.status as service_status',
        ])
        ->selectRaw('group_concat(distinct trim(trailing ", " from replace(concat_ws(", ", locations.address_line_1, locations.address_line_2, locations.address_line_3, locations.city, locations.county, locations.postcode, locations.country), ", , ", ", ")) separator "|") as service_locations')
        ->join('organisations', 'services.organisation_id', '=', 'organisations.id')
        ->leftJoin('service_locations', 'service_locations.service_id', '=', 'services.id')
        ->leftJoin('locations', 'service_locations.location_id', '=', 'locations.id')
        ->groupBy('services.id');

        return $query->get();
    }

    /**
     * Organisation Export Report query
     *
     * @return \Illuminate\Support\Collection
     **/
    public function getOrganisationExportResults()
    {
        $serviceCountQuery = DB::table('services')
        ->selectRaw('organisation_id, count(*) as count')
        ->groupBy('organisation_id');

        $nonAdminUsersCountQuery = DB::table('user_roles')
        ->selectRaw('user_roles.organisation_id as organisation_id, count(user_roles.user_id) as count')
        ->join('roles', 'roles.id', '=', 'user_roles.role_id')
        ->whereIn('roles.name', [
            Role::NAME_SERVICE_WORKER,
            Role::NAME_ORGANISATION_ADMIN,
            Role::NAME_SERVICE_ADMIN
        ])
        ->groupBy('user_roles.organisation_id', 'user_roles.user_id');

        $query = DB::table('organisations')
        ->select([
            'organisations.id as organisation_id',
            'organisations.name as organisation_name',
            'organisations.email as organisation_email',
            'organisations.phone as organisation_phone',
            'organisations.url as organisation_url',
        ])
        ->selectRaw('ifnull(service_counts.count, 0) as organisation_services_count')
        ->selectRaw('ifnull(non_admin_user_counts.count, 0) as non_admin_users_count')
        ->distinct()
        ->leftJoinSub($serviceCountQuery, 'service_counts', function ($join) {
            $join->on('service_counts.organisation_id', '=', 'organisations.id');
        })
        ->leftJoinSub($nonAdminUsersCountQuery, 'non_admin_user_counts', function ($join) {
            $join->on('non_admin_user_counts.organisation_id', '=', 'organisations.id');
        });

        return $query->get();
    }

    /**
     * Location Export Report query
     *
     * @return \Illuminate\Support\Collection
     **/
    public function getLocationExportResults()
    {
        $serviceCountQuery = DB::table('service_locations')
        ->selectRaw('location_id, count(*) as count')
        ->groupBy('location_id');

        $query = DB::table('locations')
        ->select([
            'locations.address_line_1 as location_address_line_1',
            'locations.address_line_2 as location_address_line_2',
            'locations.address_line_3 as location_address_line_3',
            'locations.city as location_city',
            'locations.county as location_county',
            'locations.postcode as location_postcode',
            'locations.country as location_country'
        ])
        ->selectRaw('ifnull(service_counts.count, 0) as location_services_count')
        ->leftJoinSub($serviceCountQuery, 'service_counts', function ($join) {
            $join->on('service_counts.location_id', '=', 'locations.id');
        });

        return $query->get();
    }
}
