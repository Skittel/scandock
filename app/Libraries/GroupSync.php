<?php

namespace App\Libraries;

use App\Models\UserModel;

/**
 * Keeps the stored group memberships of a signed-in user current, so that
 * changes in Entra take effect without signing in again.
 */
class GroupSync
{
    /**
     * Minimum seconds between two refreshes per user.
     */
    private const INTERVAL = 300;

    /**
     * Refreshes the user's groups if the last refresh is older than the
     * interval. Uses the app-only directory access if permitted, otherwise the
     * Graph token of the current session. Returns the (possibly) updated user.
     */
    public function refreshIfStale(array $tenant, array $user): array
    {
        $key = 'groupsync_' . (int) $user['id'];
        if (cache($key) !== null) {
            return $user;
        }
        // Mark first, so parallel requests do not query Graph as well.
        cache()->save($key, time(), self::INTERVAL);

        $groups = service('directory')->userGroupIds($tenant, $user['entra_oid']);
        if ($groups === null) {
            $token = session('graph_token');
            if (is_string($token) && (int) session('graph_token_expiry') > time()) {
                $groups = service('entra')->fetchGroupIds($token);
            }
        }
        if ($groups === null || $groups === UserModel::groupIds($user)) {
            return $user;
        }

        model(UserModel::class)->updateGroups((int) $user['id'], $groups);
        log_message('info', 'Group memberships of user {id} refreshed', ['id' => $user['id']]);

        return model(UserModel::class)->find($user['id']);
    }

    /**
     * Forces the next request to refresh the groups.
     */
    public function invalidate(int $userId): void
    {
        cache()->delete('groupsync_' . $userId);
    }
}
