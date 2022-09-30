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
     * @param type name
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
}
