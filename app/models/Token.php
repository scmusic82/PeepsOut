<?php
class Token extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = '_tokens';
	
	/**
	 * One-to-One relation to the user's table
	 *
	 * @var string
	 */
	public function user()
    {
        return $this->belongsTo('User', 'device_id', 'device_id');
    }

	/**
	 * Generate a new randomized token
	 *
	 * @param 	string 		device_id	default: [empty]
	 * @param 	string 		role 		default: enduser
	 * @return 	string
	 */
	public static function generateToken($device_id = '', $role = 'enduser')
	{
		$now = date('Y-m-d H:i:s');
		$existing = Token::where('device_id', '=', $device_id)->where('expires_at', '>', $now)->where('role', '=', $role);
		if ($existing->count() == 0) {
			// No token found - Generate a new one
			
			$token = '';
			$seed = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
			$max_len = 64;
			srand(time());
			shuffle($seed);
			for($a = 1; $a <= $max_len; $a++) {
				$char = array_shift($seed);
				$token .= $char;
				$seed[] = $char;
				shuffle($seed);
			}

			$duplicates = Token::where('auth_token', '=', $token);
			while($duplicates->count() > 0) {
				$token = self::generateToken($device_id, $role);
				$duplicates = Token::where('auth_token', '=', $token);
			}

		} else {
			// Return the existing token
			$token_data = $existing->first();
			$token = $token_data->auth_token;
		}
		return $token;
	}

	/**
	 * Save a token to the table
	 *
	 * @param 	string 		token		default: [empty]
	 * @param 	string 		device_id	default: [empty]
	 * @param 	string 		role 		default: enduser
	 * @return 	string
	 */
	public static function saveToken($token = '', $device_id = '', $role = 'enduser') {
		$new_token = new Token();
		$new_token->auth_token = $token;
		$new_token->device_id = $device_id;
		$new_token->role = $role;
		$new_token->expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
		$new_token->save();
	}

	/**
	 * Check token validity and existance
	 *
	 * @param 	string 		token		default: [empty]
	 * @param 	string 		role 		default: enduser
	 * @return 	bool
	 */
	public static function checkToken($token = '', $role = 'enduser')
	{
		$now = date('Y-m-d H:i:s');
		$existing = Token::where('auth_token', '=', $token)->where('expires_at', '>', $now)->where('role', '=', $role);
		if ($existing->count() == 0) {
			return false;
		}
		return true;
	}

	/**
	 * Renew an expired token
	 *
	 * @param 	string 		token		default: [empty]
	 * @param 	string 		device_id	default: [empty]
	 * @param 	string 		role 		default: enduser
	 * @return 	string
	 */
	public static function renewToken($token = '', $device_id = '', $role = 'enduser')
	{
		$existing = Token::where('auth_token', '=', $token)->where('device_id', '=', $device_id)->where('role', '=', $role);
		if ($existing->count() == 0) {
			// Token doesn't exist
			$new_token = '';
		} else {
			$new_token = self::generateToken($device_id);
			$token_data = $existing->first();
			$token_data->auth_token = $new_token;
			$token_data->expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
			$token_data->update();
		}
		return $new_token;
	}

	/**
	 * Invalidate specified token
	 *
	 * @param 	string 		token		default: [empty]
	 * @param 	string 		device_id	default: [empty]
	 * @param 	string 		role 		default: enduser
	 * @return 	bool
	 */
	public static function invalidateToken($token = '', $device_id = '', $role = 'enduser')
	{
		$existing = Token::where('auth_token', '=', $token)->where('device_id', '=', $device_id)->where('role', '=', $role);
		if ($existing->count() == 0) {
			return false;
		} else {
			$token_data = $existing->first();
			$token_data->expires_at = date('Y-m-d H:i:s', strtotime('-1 hour'));
			$token_data->update();
		}
		return true;
	}
}