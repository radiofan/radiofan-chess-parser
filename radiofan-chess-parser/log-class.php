<?php
namespace Radiofan\ChessParser;

class rad_log{
	/**
	 * Сохраняет лог в таблицу rad_chess_logs
	 * @param string $content
	 * @param string $type
	 * @param mixed $data
	 */
	public static function log($content, $type, $data){
		global $wpdb;
		$data = print_r($data, 1);
		$wpdb->insert('rad_chess_logs', compact('content', 'type', 'data'), ['%s', '%s', '%s']);
	}

	/**
	 * Сохраняет WP_Error в таблицу rad_chess_logs
	 * @param \WP_Error $wp_error
	 */
	public static function log_wp_error($wp_error){
		$codes = $wp_error->get_error_codes();
		$codes_len = sizeof($codes);
		for($i=0; $i<$codes_len; $i++){
			
			$data = $wp_error->get_all_error_data($codes[$i]);
			if($data === []){
				$data = '';
			}
			$matches = [];
			preg_match('#(success)|(error)|(warning)|(info)#iu', $codes[$i], $matches);
			if(!isset($matches[0]))
				$matches[0] = 'info';
			
			self::log(
				$codes[$i].':'.PHP_EOL.implode(PHP_EOL, $wp_error->get_error_messages($codes[$i])),
				mb_strtolower($matches[0]),
				$data
			);
		}
		
	}
}
?>