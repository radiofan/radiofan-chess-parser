<?php
namespace Radiofan\ChessParser;

class ChessParser{
	
	use InstallUninstall;
	use Parser;
	use AdminPage;

	/**
	 * @var bool будет ли проводиться проверка etag перед скачиванием файлов
	 * @see https://developer.mozilla.org/ru/docs/Web/HTTP/Headers/ETag
	 */
	const CHECK_ETAG = true;

	/** @var string $plugin_path - путь к главному файлу */
	protected $plugin_path;
	/** @var string $plugin_dir - путь к папке плагина со слешем на конце */
	protected $plugin_dir;
	/** @var string $plugin_dir_name - название папки плагина */
	protected $plugin_dir_name;
	/** @var string $plugin_url - url к папке плагина со слешем на конце */
	protected $plugin_url;
	
	const GAME_TYPE = [0 => 'standard', 1 => 'rapid', 2 => 'blitz'];

	public function __construct(string $plugin_path = __FILE__){

		$this->plugin_path = $plugin_path;
		$this->plugin_dir = plugin_dir_path($this->plugin_path);
		$this->plugin_dir_name = basename($this->plugin_dir);
		$this->plugin_url = plugin_dir_url($this->plugin_path);


		register_activation_hook($this->plugin_path, [$this, 'activate']);
		register_deactivation_hook($this->plugin_path, [$this, 'deactivate']);
		
		add_action('radiofan_chess_parser_parse', [$this, 'parse_data'], 10, 2);
		
		//add_action('current_screen', [$this, 'init_screen_admin_side']);
		add_action('admin_menu', [$this, 'add_admin_menu_item']);
		//add_action('admin_notices', [$this, 'view_notices']);
	}
	
	
	public function parse_data($type, $type_id){
		//скачивание файла
		$etag = (self::CHECK_ETAG) ? get_option('radiofan_chess_parser__etag_'.$type_id, '') : null;
		$wp_error = $this->download_file('https://ratings.ruchess.ru/api/smanager_'.$type.'.csv.zip', $this->plugin_dir.'files/download/'.$type.'.zip', $etag);
		if(!$wp_error->get_error_messages('download_success')){
			rad_log::log_wp_error($wp_error);
			return;
		}
		if($wp_error->get_error_messages('new_etag')){
			$etag = $wp_error->get_error_data('new_etag');
			$wp_error->remove('new_etag');
			if(self::CHECK_ETAG){
				update_option('radiofan_chess_parser__etag_'.$type_id, $etag, false);
			}
		}
		rad_log::log_wp_error($wp_error);
		
		//распаковка файла
		$wp_error = $this->unzip_file($this->plugin_dir.'files/download/'.$type.'.zip', $this->plugin_dir.'files/unzip/'.$type.'/');
		rad_log::log_wp_error($wp_error);
		if(!$wp_error->get_error_messages('unzip_file_success')){
			return;
		}
		$csv_path = list_files($this->plugin_dir.'files/unzip/'.$type.'/', 1);
		if(!$csv_path){
			rad_log::log('Не найдены распакованные файлы', 'error', $this->plugin_dir.'files/download/'.$type.'.zip -> '.$this->plugin_dir.'files/unzip/'.$type.'/');
			return;
		}
		$csv_path = $csv_path[0];
		wp_delete_file($this->plugin_dir.'files/download/'.$type.'.zip');
		 
		
		//парсинг файла
		$wp_error = new \WP_Error();
		$data = $this->parse_csv($csv_path, $wp_error);
		rad_log::log_wp_error($wp_error);
		if(!$wp_error->get_error_messages('csv_parsing_success')){
			return;
		}
		
		//импорт игроков
		$wp_error = new \WP_Error();
		$data_players_hash = sha1(serialize($data['player']));
		$data_players_hash_old = get_option('radiofan_chess_parser__players_hash_'.$type_id, false);
		if($data_players_hash_old !== $data_players_hash){
			$this->db_import_players($data['player'], $wp_error, get_option('radiofan_chess_parser__players_update', false));
			rad_log::log_wp_error($wp_error);
			if(!$wp_error->get_error_messages('db_import_players_error')){
				update_option('radiofan_chess_parser__players_hash_'.$type_id, $data_players_hash, false);
			}
		}else{
			rad_log::log('Игроки '.$type.' не требуют обновления', 'info', 'radiofan_chess_parser__players_hash_'.$type_id.' = '.$data_players_hash);
		}
		unset($data['player']);
		
		//импорт рейтингов
		$wp_error = new \WP_Error();
		$data_ratings_hash = sha1(serialize($data['rating']));
		$data_ratings_hash_old = get_option('radiofan_chess_parser__ratings_hash_'.$type_id, false);
		if($data_ratings_hash_old !== $data_ratings_hash){
			$this->db_import_ratings($data['rating'], $wp_error, $type_id);
			rad_log::log_wp_error($wp_error);
			if(!$wp_error->get_error_messages('db_import_ratings_error')){
				update_option('radiofan_chess_parser__ratings_hash_'.$type_id, $data_ratings_hash, false);
			}
		}else{
			rad_log::log('Рейтинги '.$type.' не требуют обновления', 'info', 'radiofan_chess_parser__ratings_hash_'.$type_id.' = '.$data_ratings_hash);
		}
		unset($data['rating']);
		
	}
}