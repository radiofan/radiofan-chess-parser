<?php

class rad_log{
	private $path;
	const MAX_LOG_SIZE = 5*MB_IN_BYTES;
	
	private $curr_error_f;

	/**
	 * @param string $path - путь до папки с логами (должен оканчиваться на /)
	 * @throws Exception $path не существует
	 */
	public function __construct($path){
		$this->path = $path;
		if(!self::check_dir($this->path))
			throw new Exception('undefined log path');
		$error_files_list = self::file_list($path, '.log', '^parser-[0-9]+');
		$len = sizeof($error_files_list);
		if($len == 0){
			$this->curr_error_f = 'parser-'.str_pad('1', 5, '0', STR_PAD_LEFT).'.log';
		}else{
			$this->curr_error_f = $error_files_list[$len-1];
			$this->while_test_error_f();
		}
	}


	/**
	 * обновляет имя файла на следующее, если текущий переполнен
	 * @return bool - true если обновление произошло
	 */
	private function test_error_f(){
		if(self::get_filesize($this->path.$this->curr_error_f) >= self::MAX_LOG_SIZE){
			$number = self::int_clear($this->curr_error_f);
			$number++;
			$this->curr_error_f = 'parser-'.str_pad($number, 5, '0', STR_PAD_LEFT).'.log';
			return true;
		}
		return false;
	}

	/**
	 * обновляет имя файла на последний не полный файл
	 */
	private function while_test_error_f(){
		$number = (int)self::int_clear($this->curr_error_f);
		while(self::get_filesize($this->path.$this->curr_error_f) >= self::MAX_LOG_SIZE){
			$number++;
			$this->curr_error_f = 'parser-'.str_pad($number, 5, '0', STR_PAD_LEFT).'.log';
		}
	}
	
	/*
	public function log_error($errno, $errstr, $errfile, $errline){
		$type = isset($this->error_type[$errno]) ? $this->error_type[$errno] : 'UDENFINED('.$errno.')';
		$out = '['.date('Y-M-d H:i:s').'] ['.$type.'] '.$errstr.'; File: '.$errfile.', line: '.$errline.PHP_EOL;
		$this->log_write($out);
		if($errno === E_USER_ERROR){
			die();
		}
	}
	*/
	
	public function log_write($data){
		$this->while_test_error_f();
		return file_put_contents($this->path.$this->curr_error_f, $data, FILE_APPEND | LOCK_EX);
	}
	
	/**
	 * Проверяет существование папки
	 * @param $path - путь до папки
	 * @return bool
	 */
	public static function check_dir($path){
		return file_exists($path) && is_dir($path);
	}

	/**
	 * возвращает список файлов в папке
	 * @param string $path - путь до директории
	 * @param string $ext - расширение файлов (c точкой)
	 * @param string $name_pattern - шаблон имени (регулярное выражение)
	 * @return string[]
	 */
	public static function file_list($path, $ext='', $name_pattern='^.*?'){
		$files = scandir($path);
		$pattern = '#'.$name_pattern.preg_quote($ext).'$#';
		$len = sizeof($files);
		for($i=0; $i<$len; $i++){
			if(!preg_match($pattern, $files[$i])){
				unset($files[$i]);
			}
		}
		return array_values($files);
	}

	/**
	 * возвращает объем файла в байтах, обновляет кэш
	 * @param string $path - путь до файла
	 * @return false|int - false если файл не найден
	 */
	public static function get_filesize($path){
		if(!file_exists($path) || !is_file($path))
			return false;
		clearstatcache(1, $path);
		return filesize($path);
	}

	/**
	 * удаляет все символы кроме цифр
	 * @param string $text
	 * @return string
	 */
	public static function int_clear($text){
		return preg_replace('/[^0-9]/iu', '', $text);
	}
}
?>