<?php namespace RB\Helpers;

use Illuminate\Support\Facades\DB,
    Illuminate\Support\Facades\Request;

class Token {

    // The database table containing all the tokens
    protected $_token_table = 'tokens';

    // The Time-To-Live of the tokens, expressed in minutes.
    protected $_token_ttl = 60;

    // The token size in characters
    protected $_token_size = 64;

    // The token available characters for use
    protected $_token_seed = [
                                '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 
                                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
                                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
                                '.', ',', '#', '$', '!', '@', '^', '*', '(', ')'
                            ];

    /**
     * Create a new token
     *
     *
     * @return  string
     */
    public function createToken($opts = [])
    {
        $now = date('Y-m-d H:i:s');
        $defaults = [
            'client' => Request::ip(),
            'role' => 'enduser'
        ];
        $opts = array_merge($defaults, $opts);

        // Check if any valid tokens exist for this client
        //   - Return it if it does
        //   - Generate new one if it doesn't
        $existing = DB::table($this->_token_table)
            ->where('client', '=', $opts['client'])
            ->where('expires_at', '>', $now)
            ->where('role', '=', $opts['role']);

        if ($existing->count() == 0) {
            // No token found - Generate a new one
            $tkn = $this->generateToken();
            $this->saveToken($tkn, $opts['client'], $opts['role']);
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
    public function checkToken($tkn = '', $role = 'enduser')
    {
        $now = date('Y-m-d H:i:s');
        $existing_token_count = DB::table($this->_token_table)
            ->where('token', $tkn)
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
    public function generateToken()
    {
        $new_token = '';
        $seed = $this->_token_seed;
        $max_len = $this->_token_size;
        srand(time());
        shuffle($seed);
        for($a = 1; $a <= $max_len; $a++) {
            $char = array_shift($seed);
            $new_token .= $char;
            $seed[] = $char;
            shuffle($seed);
        }

        $duplicates = DB::table($this->_token_table)
            ->where('token', $new_token)
            ->count();
        while($duplicates > 0) {
            $new_token = $this->generateToken();
            $duplicates = DB::table($this->_token_table)
                ->where('token', $new_token)
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
    public function saveToken($tkn = '', $client = '', $role = 'enduser')
    {
        if ($tkn != '' && $client != '') {
            DB::table($this->_token_table)
                ->insert([[
                    'token' => $tkn,
                    'client' => $client,
                    'role' => $role,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'expires_at' => date('Y-m-d H:i:s', strtotime("+" . $this->_token_ttl . " minutes")),
                ]]);
        }
    }

    /**
     * Renew an expired token
     *
     *
     * @return  string
     */
    public function renewToken($old_tkn = '', $client = '', $role = 'enduser')
    {
        $new_token = $old_tkn;
        if ($old_tkn != '' && $client != '') {
            $now = date('Y-m-d H:i:s');
            $existing_token = DB::table($this->_token_table)
                ->where('token', $old_tkn)
                ->where('expires_at', '<', $now)
                ->where('client', $client)
                ->where('role', $role);
            if ($existing_token->count() == 1) {
                $token_data = $existing_token->first();
                $new_token = $this->generateToken();
                $token_data->token = $new_token;
                $token_data->expires_at = date('Y-m-d H:i:s', strtotime("+" . $this->_token_ttl . " minutes"));
                $token_data->update();
                return $new_token;
            }
        }
        return $new_token;
    }
}