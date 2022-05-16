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
		
		add_action('radiofan_chess_parser_parse', [$this, 'parse_data']);
		
		add_action('admin_menu', [$this, 'add_admin_menu_item']);
		add_action('admin_notices', [$this, 'view_notices']);

		add_action('current_screen', [$this, 'test_download']);
	}
	
	
	public function parse_data(){
		
	}

	/**
	 * @param \WP_Screen $screen
	 */
	public function test_download($screen){
		error_log('test_download');//todo delme

		/*
		 * empty_memory_peak(1): 2 МБ (2097152), empty_memory_peak(0): 40 МБ (41225784)
		 * unzip_memory_peak(1): 34 МБ (35651584), unzip_memory_peak(0): 71 МБ (74926456)
		 * wordpress_download_memory_peak(1): 8 МБ (8388608), wordpress_download_memory_peak(0): 40 МБ (41714808)
		 * download_memory_peak(1): 6 МБ (6291456), download_memory_peak(0): 39 МБ (41265256)
		 */

		//$ret = download_url('https://ratings.ruchess.ru/api/smanager_standard.csv.zip');
		//$this->unzip_file($this->plugin_dir.'smanager_standard.zip', 'files/unzip/');
		//$ret = $this->download_file('https://ratings.ruchess.ru/api/smanager_standard.csv.zip', $this->plugin_dir.'files/file.zip', '"627f78fc-5c310c"');
		//$data = $this->parse_csv($this->plugin_dir.'files/unzip/smanager_standard.csv', $error);
		
		//error_log('wordpress_download_memory_peak(1): '.size_format(memory_get_peak_usage(1)).' ('.memory_get_peak_usage(1).'), wordpress_download_memory_peak(0): '.size_format(memory_get_peak_usage()).' ('.memory_get_peak_usage().')');
	}
}