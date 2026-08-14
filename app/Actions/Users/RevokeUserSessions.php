<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevokeUserSessions
{
    public function handle(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') === 'database') {
            $connection = config('session.connection');
            $table = config('session.table', 'sessions');
            $query = DB::connection(is_string($connection) ? $connection : null)
                ->table(is_string($table) ? $table : 'sessions')
                ->where('user_id', $user->getKey());

            if ($exceptSessionId !== null && $exceptSessionId !== '') {
                $query->where('id', '!=', $exceptSessionId);
            }

            $query->delete();
        }

        $user->setRememberToken(Str::random(60));
        $user->saveQuietly();
    }
}
