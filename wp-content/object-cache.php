<?php
if(
	// (isset($_POST['action']) && $_POST['action'] == 'query-attachments') ||
	(isset($_GET['debug']) && $_GET['debug'] == 'sql')
){
	return;
}

if(!defined('WP_CACHE_KEY_SALT')){
	define('WP_CACHE_KEY_SALT', '');
}

if(class_exists('Memcached')){
	function wp_cache_add($key, $data, $group='', $expire=0){
		global $wp_object_cache;
		return $wp_object_cache->add($key, $data, $group, (int)$expire);
	}

	function wp_cache_cas($cas_token, $key, $data, $group='', $expire=0){
		global $wp_object_cache;
		return $wp_object_cache->cas($cas_token, $key, $data, $group, (int)$expire);
	}

	function wp_cache_close(){
		global $wp_object_cache;
		return $wp_object_cache->close();
	}

	function wp_cache_decr($key, $offset=1, $group='', $initial_value=0, $expire=0){
		global $wp_object_cache;
		return $wp_object_cache->decr($key, $offset, $group, $initial_value, $expire);
	}

	function wp_cache_delete($key, $group=''){
		global $wp_object_cache;
		return $wp_object_cache->delete($key, $group);
	}

	function wp_cache_flush(){
		global $wp_object_cache;
		return $wp_object_cache->flush();
	}

	function wp_cache_get($key, $group='', $force=false, &$found=null){
		global $wp_object_cache;
		return $wp_object_cache->get($key, $group, $force, $found);
	}

	function wp_cache_get_multiple($keys, $group='', $force=false){
		global $wp_object_cache;
		return $wp_object_cache->get_multiple($keys, $group, $force);
	}

	function wp_cache_set_multiple($data, $group='', $expire=0){
		global $wp_object_cache;
		return $wp_object_cache->set_multiple($data, $group, $expire);
	}

	function wp_cache_delete_multiple($keys, $group=''){
		global $wp_object_cache;
		return $wp_object_cache->delete_multiple($keys, $group);
	}

	function wp_cache_get_with_cas($key, $group='', &$token=null){
		global $wp_object_cache;

		$result	= $wp_object_cache->get_with_cas($key, $group);

		if(is_array($result)){
			$token	= $result['cas'];
			$result	= $result['value'];
		}

		return $result;
	}

	function wp_cache_incr($key, $offset=1, $group='', $initial_value=0, $expire=0){
		global $wp_object_cache;
		return $wp_object_cache->incr($key, $offset, $group, $initial_value, $expire);
	}

	if(!isset($_GET['debug']) || $_GET['debug'] != 'sql'){
		function wp_cache_init(){
			global $wp_object_cache;
			$wp_object_cache	= new WP_Object_Cache();
		}
	}

	function wp_cache_replace($key, $data, $group='', $expire=0){
		global $wp_object_cache;
		return $wp_object_cache->replace($key, $data, $group, (int)$expire);
	}

	function wp_cache_set($key, $data, $group='', $expire=0){
		global $wp_object_cache;
		return $wp_object_cache->set($key, $data, $group, (int)$expire);
	}

	function wp_cache_switch_to_blog($blog_id){
		global $wp_object_cache;
		return $wp_object_cache->switch_to_blog($blog_id);
	}

	function wp_cache_add_global_groups($groups){
		global $wp_object_cache;
		$wp_object_cache->add_global_groups($groups);
	}

	function wp_cache_add_non_persistent_groups($groups){
		global $wp_object_cache;
		$wp_object_cache->add_non_persistent_groups($groups);
	}

	function wp_cache_get_stats(){
		global $wp_object_cache;
		return $wp_object_cache->get_stats();
	}

	class WP_Object_Cache{
		private $cache 	= [];
		private $mc		= null;

		private $blog_prefix;
		private $global_prefix;

		protected $global_groups	= [];
		protected $non_persistent_groups	= [];

		protected function action($action, $id, $group, $data, $expire=0){
			if($this->is_non_persistent_group($group)){
				$internal	= $this->internal('get', $id, $group);

				if($action == 'add'){
					if($internal !== false){
						return false;
					}
				}elseif($action == 'replace'){
					if($internal === false){
						return false;
					}
				}elseif($action == 'increment' || $action == 'decrement'){
					$data	= $action == 'increment' ? $data : (0-$data);
					$data	= (int)$internal+$data;
					$data	= $data < 0 ? 0 : $data;
				}

				return $this->internal('add', $id, $group, $data);
			}else{
				$key	= $this->build_key($id, $group);
				$expire	= (!$expire && strlen($id) > 50) ? DAY_IN_SECONDS : $expire;

				if($action == 'set'){
					$result	= $this->mc->set($key, $data, $expire);
				}elseif($action == 'add'){
					$result	= $this->mc->add($key, $data, $expire);
				}elseif($action == 'replace'){
					$result	= $this->mc->replace($key, $data, $expire);
				}elseif($action == 'increment'){
					$result	= $data = $this->mc->increment($key, $data);
				}elseif($action == 'decrement'){
					$result	= $data = $this->mc->decrement($key, $data);
				}

				$code	= $this->mc->getResultCode();

				if($code === Memcached::RES_SUCCESS){
					$this->internal('add', $id, $group, $data);
				}else{
					$this->internal('del', $id, $group);

					if($code != Memcached::RES_NOTSTORED){
						// trigger_error($code.' '.var_export($result, true).' '.var_export($key, true));
					}
				}

				return $result;
			}
		}

		protected function internal($action, $id, $group, $data=null){
			$group	= $this->parse_group($group);

			if($action == 'get'){
				$data	= $this->cache[$group][$id] ?? false;

				return is_object($data) ? clone $data : $data;
			}elseif($action == 'add'){
				$this->cache[$group][$id]	= is_object($data) ? clone $data : $data;

				return true;
			}elseif($action == 'del'){
				unset($this->cache[$group][$id]);
			}
		}

		public function add($id, $data, $group='default', $expire=0){
			if(wp_suspend_cache_addition()){
				return false;
			}

			return $this->action('add', $id, $group, $data, $expire);
		}

		public function replace($id, $data, $group='default', $expire=0){
			return $this->action('replace', $id, $group, $data, $expire);
		}

		public function set($id, $data, $group='default', $expire=0){
			return $this->action('set', $id, $group, $data, $expire);
		}

		public function incr($id, $offset=1, $group='default', $initial_value=0, $expire=0){
			$this->action('add', $id, $group, $initial_value, $expire);
			return $this->action('increment', $id, $group, $offset);
		}

		public function decr($id, $offset=1, $group='default', $initial_value=0, $expire=0){
			$this->action('add', $id, $group, $initial_value, $expire);
			return $this->action('decrement', $id, $group, $offset);
		}

		public function cas($cas_token, $id, $data, $group='default', $expire=0){
			$this->internal('del', $id, $group);

			return $this->mc->cas($cas_token, $this->build_key($id, $group), $data, $expire);
		}

		public function delete($id, $group='default'){
			$this->internal('del', $id, $group);

			return $this->is_non_persistent_group($group) ? true : $this->mc->delete($this->build_key($id, $group));
		}

		public function flush(){
			$this->cache	= [];

			return $this->mc->flush();
		}

		public function get($id, $group='default', $force=false, &$found=null){
			$value	= $force ? false : $this->internal('get', $id, $group);
			$found	= $value !== false;

			if(!$found && !$this->is_non_persistent_group($group)){
				$value	= $this->mc->get($this->build_key($id, $group));
				$code	= $this->mc->getResultCode();
				$found	= $code !== Memcached::RES_NOTFOUND;

				if($found){
					if($code !== Memcached::RES_SUCCESS){
						trigger_error($code.' '.var_export([$id, $group, $value], true));
					}

					$this->internal('add', $id, $group, $value);
				}
			}

			return $value;
		}

		public function get_with_cas($id, $group='default'){
			$key	= $this->build_key($id, $group);

			if(defined('Memcached::GET_EXTENDED')){
				$result	= $this->mc->get($key, null, Memcached::GET_EXTENDED);
			}else{
				$value	= $this->mc->get($key, null, $cas);
				$result	= ['value'=>$value, 'cas'=>$cas];
			}

			return $this->mc->getResultCode() === Memcached::RES_NOTFOUND ? false : $result;
		}

		public function get_multiple($ids, $group='default', $force=false){
			$caches	= [];
			$keys	= [];

			$non_persistent	= $this->is_non_persistent_group($group);

			if($non_persistent || !$force){
				foreach($ids as $id){
					$caches[$id]	= $this->internal('get', $id, $group);
					$keys[$id]		= $this->build_key($id, $group);

					if(!$non_persistent && $caches[$id] === false){
						$force	= true;
					}
				}

				if($non_persistent || !$force){
					return $caches;
				}
			}

			$results	= $this->mc->getMulti(array_values($keys)) ?: [];

			foreach($keys as $id => $key){
				$caches[$id]	= $results[$key] ?? false;

				$this->internal('add', $id, $group, $caches[$id]);
			}

			return $caches;
		}

		public function set_multiple($data, $group='default', $expire=0){
			$items	= [];

			foreach($data as $id => $value){
				$this->internal('add', $id, $group, $value);

				$key = $this->build_key($id, $group);

				$items[$key]	= $value;
			}

			if($this->is_non_persistent_group($group)){
				$result	= true;
			}else{
				$result	= $this->mc->setMulti($items, $expire);
				$code	= $this->mc->getResultCode();

				if($code !== Memcached::RES_SUCCESS){
					if($code != Memcached::RES_NOTSTORED){
						// trigger_error($code.' '.var_export($result,true));
					}

					foreach($data as $id => $value){
						$this->internal('del', $id, $group);
					}

					return $result;
				}
			}

			return $result;
		}

		public function delete_multiple($ids, $group='default'){
			foreach($ids as $id){
				$this->internal('del', $id, $group);

				$keys[]	= $this->build_key($id, $group);
			}

			return (empty($keys) || $this->is_non_persistent_group($group)) ? true : $this->mc->deleteMulti($keys);
		}

		public function add_global_groups($groups){
			$this->global_groups	= array_merge($this->global_groups, array_fill_keys((array)$groups, true));
		}

		public function add_non_persistent_groups($groups){
			$this->non_persistent_groups	= array_merge($this->non_persistent_groups, array_fill_keys((array)$groups, true));
		}

		public function switch_to_blog($blog_id){
			if(is_multisite()){
				$this->blog_prefix	= ((int)$blog_id).':';
			}
		}

		private function is_non_persistent_group($group){
			return $group ? isset($this->non_persistent_groups[$group]) : false;
		}

		private function parse_group($group){
			$group	= $group ?: 'default';
			$prefix	= isset($this->global_groups[$group]) ? $this->global_prefix : $this->blog_prefix;

			return WP_CACHE_KEY_SALT.$prefix.$group;
		}

		public function build_key($id, $group='default'){
			return preg_replace('/\s+/', '', $this->parse_group($group).':'.$id);
		}

		public function get_stats(){
			return $this->mc->getStats();
		}

		public function get_mc(){
			return $this->mc;
		}

		public function failure_callback($host, $port){}

		public function close(){
			$this->mc->quit();
		}

		public function __construct(){
			$this->mc	= new Memcached();
			// $this->mc->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);	// 用于启用与 libketama 一致性哈希算法兼容的服务器分布策略

			if(!$this->mc->getServerList()){
				global $memcached_servers;

				if(isset($memcached_servers)){
					foreach($memcached_servers as $memcached){
						$this->mc->addServer(...$memcached);
					}
				}else{
					$this->mc->addServer('memcached', 11211);
				}
			}

			if(is_multisite()){
				$this->blog_prefix		= get_current_blog_id().':';
				$this->global_prefix	= '';
			}else{
				$this->blog_prefix		= $GLOBALS['table_prefix'].':';
				$this->global_prefix	= defined('CUSTOM_USER_TABLE') ? '' : $this->blog_prefix;
			}
		}
	}
}
