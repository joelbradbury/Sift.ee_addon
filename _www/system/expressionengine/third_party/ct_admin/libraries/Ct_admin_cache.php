<?php

class Ct_admin_cache 
{
	/**
	 * Cache lifetime
	 *
	 * @var int
	 **/
	public $cache_lifetime = 86400;	

	public $cache_path = FALSE;
	
	public function __construct() 
	{
		$this->cache_path = APPPATH.'cache/' . __CLASS__;			
	}
	
	private function get_cache_path($key)
	{
		return $this->cache_path ."/". $key . ".ct";		
	}
	
	public function create_cache_file($data, $key)
	{
		$filepath = $this->get_cache_path($key);
	
		if (! is_dir($this->cache_path))
		{
			mkdir($this->cache_path . "", 0777, TRUE);
		}
		
		if(! is_really_writable($this->cache_path))
		{
			return FALSE;
		}
	
		if ( ! $fp = fopen($filepath, FOPEN_WRITE_CREATE_DESTRUCTIVE))
		{
			return FALSE;
		}
	
		flock($fp, LOCK_EX);
		fwrite($fp, $data);
		flock($fp, LOCK_UN);
		fclose($fp);
		chmod($filepath, DIR_WRITE_MODE);
	}
	
	public function read_cache($key)
	{
		$cache = FALSE;
		$filepath = $this->get_cache_path($key);
	
		if (!file_exists($filepath))
		{
			return FALSE;
		}
		
		if ( ! $fp = fopen($filepath, FOPEN_READ))
		{
			return FALSE;
		}
	
		if( filemtime($filepath) + $this->cache_lifetime < time() )
		{
			@unlink($filepath);
			return FALSE;
		}
	
		flock($fp, LOCK_SH);
		$length = filesize($filepath);
		if($length > 0)
		{
			$cache = fread($fp, $length);
		}
		flock($fp, LOCK_UN);
		fclose($fp);	
		return $cache;
	}	
}

?>