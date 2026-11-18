<?php

namespace App\Auth;

use App\Models\User;

/**
 * One-click sign-in as the VATSIM Connect sandbox's test members, for local development only:
 * CIDs 10000001 to 10000010, the same Accounts the seeder makes, so each works through a dev link
 * and through a real sandbox sign-in alike.
 *
 * Available only with APP_ENV=local, and there's no switch to turn it on. That is the whole guard:
 * a server wrongly left at APP_ENV=local offers these links to anyone who can reach it. Nothing in
 * the request can be trusted to tell (Laravel stops checking the Host header in local, so a client
 * picks its own), which is why .env.example defaults to production and only `composer run setup`
 * makes a local .env.
 */
final class DevSignIn
{
    private const int FIRST_CID = 10000001;

    private const int LAST_CID = 10000010;

    public static function isAvailable(): bool
    {
        return app()->environment('local');
    }

    /**
     * One link per sandbox CID, with what that Account holds, or that it doesn't exist yet.
     *
     * @return list<array{cid: int, name: string|null, roles: list<string>}>
     */
    public static function links(): array
    {
        $users = User::query()->whereBetween('vatsim_cid', [self::FIRST_CID, self::LAST_CID])->get()->keyBy('vatsim_cid');

        return array_map(fn (int $cid) => [
            'cid' => $cid,
            'name' => $users->get($cid)?->name,
            'roles' => $users->has($cid) ? Roles::labelsFor($users->get($cid))->all() : [],
        ], range(self::FIRST_CID, self::LAST_CID));
    }

    public static function isSandboxCid(int $cid): bool
    {
        return $cid >= self::FIRST_CID && $cid <= self::LAST_CID;
    }

    /**
     * The sandbox member's Account, created with a placeholder name if it doesn't exist yet.
     */
    public static function account(int $cid): User
    {
        return User::query()->firstOrCreate(['vatsim_cid' => $cid], ['name' => "VATSIM {$cid}"]);
    }
}
