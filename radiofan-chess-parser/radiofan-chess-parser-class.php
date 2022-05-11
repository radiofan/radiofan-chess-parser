<?php

class RadiofanChessParser{
	
	/** @var string $plugin_path - путь к главному файлу */
	protected $plugin_path;
	/** @var string $plugin_dir - путь к папке плагина */
	protected $plugin_dir;
	/** @var string $plugin_dir_name - название папки плагина */
	protected $plugin_dir_name;
	/** @var string $plugin_url - url к папке плагина */
	protected $plugin_url;

	public function __construct(string $plugin_path = __FILE__){
		
		$this->plugin_path = $plugin_path;
		$this->plugin_dir = plugin_dir_path($this->plugin_path);
		$this->plugin_dir_name = basename($this->plugin_dir);
		$this->plugin_url = plugin_dir_url($this->plugin_path);
		
		
		register_activation_hook($this->plugin_path, [$this, 'activate']);
		register_deactivation_hook($this->plugin_path, [$this, 'deactivate']);

		add_action('current_screen', [$this, 'test_download']);
	}

	/**
	 * @param WP_Screen $screen
	 */
	public function test_download($screen){
		error_log('test_download');//todo delme
		
		/*
		 * empty_memory_peak(1): 2 МБ (2097152), empty_memory_peak(0): 40 МБ (41225784)
		 * unzip_memory_peak(1): 34 МБ (35651584), unzip_memory_peak(0): 71 МБ (74926456)
		 */

		//$this->unzip_file($this->plugin_dir.'smanager_standard.zip', 'files/unzip/');
		//error_log('unzip_memory_peak(1): '.size_format(memory_get_peak_usage(1)).' ('.memory_get_peak_usage(1).'), unzip_memory_peak(0): '.size_format(memory_get_peak_usage()).' ('.memory_get_peak_usage().')');
	}

	/**
	 * @param string $zip - абсолютный путь к архиву
	 * @param string $dir_to - папка внутри плагина куда распакуются файлы без начального слэша
	 * @return true|WP_Error
	 */
	protected function unzip_file($zip, $dir_to){
		if(!file_exists($zip) || !is_file($zip))
			return new WP_Error('archive_not_exist', 'Архив не найден!');
		
		//инициализация функции распаковки
		if(!function_exists('unzip_file')){
			require_once(ABSPATH.'wp-admin/includes/file.php');
		}
		global $wp_filesystem;
		if(empty($wp_filesystem)){
			WP_Filesystem();
		}

		$plugin_path = str_replace(ABSPATH, $wp_filesystem->abspath(), $this->plugin_dir);
		$plugin_path .= $dir_to;
		
		return unzip_file($zip, $plugin_path);
	}

	/**
	 * @param string $csv - абсолютный путь к csv файлу
	 * @return WP_Error
	 */
	protected function parse_csv($csv){
		if(!file_exists($csv) || !is_file($csv))
			return new WP_Error('csv_not_exist', 'CSV файл не найден!');
		
		$csv_stream = fopen($csv, 'r');
		if($csv_stream === false)
			return new WP_Error('csv_not_open', 'CSV файл не открыт!');
	}
	
	public function activate(){
		if(!current_user_can('activate_plugins'))
			return;
		$plugin = $_REQUEST['plugin'] ?? '';
		check_admin_referer('activate-plugin_'.$plugin);
		
		//создаем таблицы
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		
		//rad_chess_players
		$sql_rad_chess_players =
			'CREATE TABLE '.$wpdb->prefix.'rad_chess_players (
				id_ruchess    bigint(11) unsigned NOT NULL,
				id_fide       bigint(11) unsigned NOT NULL,
				name          text                NOT NULL,
				sex           bool                NOT NULL COMMENT \'0 - м, 1 - ж\',
				country       tinytext            NOT NULL,
				birth_year    year,
				region_number smallint   unsigned NOT NULL default \'0\',
				region_name   text                NOT NULL default \'\',
				PRIMARY KEY  (id_ruchess)
			) DEFAULT CHARACTER SET '.$wpdb->charset.' COLLATE '.$wpdb->collate.';';
		
		//rad_chess_players_ratings
		$sql_rad_chess_players_ratings =
			'CREATE TABLE '.$wpdb->prefix.'rad_chess_players_ratings (
				id_ruchess    bigint(11) unsigned NOT NULL,
				rating_type   tinyint    unsigned NOT NULL default \'0\' COMMENT \'1 - ruchess standard, 2 - fide standard, 3 - ruchess rapid, 4 - fide rapid, 5 - ruchess blitz, 6 - fide blitz\',
				rating        int                          default NULL,
				update_date   date                NOT NULL,
				KEY key_ratings_id_ruchess (id_ruchess)
			) DEFAULT CHARACTER SET '.$wpdb->charset.' COLLATE '.$wpdb->collate.';';

		dbDelta([$sql_rad_chess_players, $sql_rad_chess_players_ratings]);
		
		error_log('activate');//todo delme
	}

	public function deactivate(){
		if(!current_user_can('activate_plugins'))
			return;
		$plugin = $_REQUEST['plugin'] ?? '';
		check_admin_referer('deactivate-plugin_'.$plugin);

		error_log('deactivate');//todo
	}

	public static function uninstall(){
		if(!current_user_can('activate_plugins'))
			return;
		
		if(!defined('WP_UNINSTALL_PLUGIN'))
			return;

		global $wpdb;
		$wpdb->query('DROP TABLE '.$wpdb->prefix.'rad_chess_players_ratings, '.$wpdb->prefix.'rad_chess_players');
	}
}