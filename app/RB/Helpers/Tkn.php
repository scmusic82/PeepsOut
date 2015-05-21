<?php namespace RB\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class Tkn {

    // The database table containing all the tokens
    protected static $_token_table = '_tokens';

    // The Time-To-Live of the tokens, expressed in hours.
    protected static $_token_ttl = 1;

    // The token size in characters
    protected static $_token_size = 64;

    // The token available characters for use
    protected static $_token_seed = [
                                '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 
                                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
                                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
                            ];

    /**
     * Create a new token
     *
     *
     * @return  string
     */
    public static function createToken($opts = [])
    {
        $now = date('Y-m-d H:i:s');
        $defaults = [
            'device_id' => Request::ip(),
            'role' => 'enduser'
        ];
        $opts = array_merge($defaults, $opts);

        // Check if any valid tokens exist for this device_id
        //   - Return it if it does
        //   - Generate new one if it doesn't
        $existing = DB::table(self::$_token_table)
            ->where('device_id', '=', $opts['device_id'])
            ->where('expires_at', '>', $now)
            ->where('role', '=', $opts['role']);

        if ($existing->count() == 0) {
            // No token found - Generate a new one
            $tkn = self::generateToken();
            self::saveToken($tkn, $opts['device_id'], $opts['role']);
        } else {
            // Return the existing token
            $token_data = $existing->first();
            $tkn = $token_data->token;
        }
        return $tkn;
    }

    /**
     * Check a token against database and expiry date
     *
     *
     * @param   string
     * @return  bool
     */
    public static function checkToken($tkn = '', $role = 'enduser')
    {
        $now = date('Y-m-d H:i:s');
        $existing_token_count = DB::table(self::$_token_table)
            ->where('auth_token', $tkn)
            ->where('expires_at', '>', $now)
            ->count();
        if ($existing_token_count == 1) {
            return true;
        }
        return false;
    }

    /**
     * Generate the actual token string
     *
     *
     * @return  string
     */
     
    public static function generateToken()
    {
        $new_token = '';
        $seed = self::$_token_seed;
        $max_len = self::$_token_size;
        srand(time());
        shuffle($seed);
        for($a = 1; $a <= $max_len; $a++) {
            $char = array_shift($seed);
            $new_token .= $char;
            $seed[] = $char;
            shuffle($seed);
        }

        $duplicates = DB::table(self::$_token_table)
            ->where('auth_token', $new_token)
            ->count();
        while($duplicates > 0) {
            $new_token = self::generateToken();
            $duplicates = DB::table(self::$_token_table)
                ->where('auth_token', $new_token)
                ->count();
        }
        return $new_token;
    }

    /**
     * Save a token in the database
     *
     *
     * @return  void
     */
    public static function saveToken($tkn = '', $device_id = '', $role = 'enduser')
    {
        if ($tkn != '' && $device_id != '') {
            DB::table(self::$_token_table)
                ->insert([[
                    'auth_token' => $tkn,
                    'device_id' => $device_id,
                    'role' => $role,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'expires_at' => date('Y-m-d H:i:s', strtotime("+" . self::$_token_ttl . " hours")),
                ]]);
        }
    }

    /**
     * Renew an expired token
     *
     *
     * @return  string
     */
    public static function renewToken($old_tkn = '', $device_id = '', $role = 'enduser')
    {
        $new_token = $old_tkn;
        if ($old_tkn != '' && $device_id != '') {
            $now = date('Y-m-d H:i:s');
            $existing_token = DB::table(self::$_token_table)
                ->where('auth_token', '=', $old_tkn)
                ->where('expires_at', '<', $now)
                ->where('device_id', '=', $device_id)
                ->where('role', '=', $role);
            if ($existing_token->count() == 1) {
                $token_data = $existing_token->first();
                $new_token = self::generateToken();
                DB::table(self::$_token_table)
                    ->where('id', $token_data->id)
                    ->update([
                        'auth_token' => $new_token,
                        'expires_at' => date('Y-m-d H:i:s', strtotime("+" . self::$_token_ttl . " hours"))
                    ]);
                return $new_token;
            }
        }
        return $new_token;
    }

    /**
     * Check expired token existance by device_id and role
     *
     *
     * @return  bool
     */
    public static function expiredToken($device_id = '', $role = 'enduser')
    {
        $now = date('Y-m-d H:i:s');
        $existing_token = DB::table(self::$_token_table)
            ->where('expires_at', '<', $now)
            ->where('device_id', $device_id)
            ->where('role', $role);
        if ($existing_token->count() == 1) {
            return true;
        }
        return false;
    }
}