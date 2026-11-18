<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * One-click sign-in as the VATSIM Connect sandbox's test members, for local development only:
 * CIDs 10000001 to 10000010, the same Accounts the seeder makes, so each works through a dev link
 * and through a real sandbox sign-in alike.
 *
 * Available only with APP_ENV=local (or testing) *and* a request for a local host, so neither a
 * misconfigured server nor a public URL can ever offer it. There's no switch to turn it on.
 */
final class DevSignIn
{
    /** @var list<string> Hosts a developer's own machine answers on. */
    private const array HOSTS = ['localhost', '127.0.0.1', '*.test'];

    private const int FIRST_CID = 10000001;

    private const int LAST_CID = 10000010;

    public static function isAvailable(Request $request): bool
    {
        return app()->environment('local', 'testing') && self::isLocalHost($request->getHost());
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

    private static function isLocalHost(string $host): bool
    {
        return collect(self::HOSTS)->contains(fn (string $pattern) => str_starts_with($pattern, '*.')
            ? str_ends_with($host, substr($pattern, 1))
            : $host === $pattern);
    }
}
