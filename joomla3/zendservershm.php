<?php
/**
 * @package	    Joomla.Platform
 * @subpackage	Cache
 *
 * @author      Enrico Zimuel (enrico@zimuel.it)
 * @license	    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('JPATH_PLATFORM') or die;

/**
 * ZendServer cache storage handler
 *
 * @package		Joomla.Platform
 * @subpackage	Cache
 * @since		11.1
 */
class JCacheStorageZendservershm extends JCacheStorage
{
    const CACHE_ZS_SYSTEM = 'zend_server_sys';
    const CACHE_ZS_GROUPS = 'joomla_groups';
    /**
	 * Get cached data from ZendServer by id and group
	 *
	 * @access	public
	 * @param	string	$id			The cache data id
	 * @param	string	$group		The cache data group
	 * @param	boolean	$checkTime	True to verify cache time expiration threshold
	 * @return	mixed	Boolean false on failure or a cached data string
	 * @since	11.1
	 */
	public function get($id, $group, $checkTime = true)
	{
		$cache_id = $this->_getCacheId($id, $group);
		return zend_shm_cache_fetch($group . '::' . $cache_id);
	}
	/**
	 * Store the data to ZendeServer chache by id and group
	 *
	 * @param	string	$id		The cache data id
	 * @param	string	$group	The cache data group
	 * @param	string	$data	The data to store in cache
	 * @return	boolean	True on success, false otherwise
	 * @since	11.1
	 */
	public function store($id, $group, $data)
	{
		$cache_id = $this->_getCacheId($id, $group);
        $this->updateGroup($group, strlen(serialize($data)));
		return zend_shm_cache_store($group . '::' . $cache_id, $data, $this->_lifetime);	
	}
	/**
	 * Remove a cached data entry by id and group
	 *
	 * @param	string	$id		The cache data id
	 * @param	string	$group	The cache data group
	 * @return	boolean	True on success, false otherwise
	 * @since	11.1
	 */
	public function remove($id, $group)
	{
		$cache_id = $this->_getCacheId($id, $group);
        $this->updateGroup($group, -1 * strlen(serialize(zend_shm_cache_fetch($group . '::' . $cache_id))));		
		return zend_shm_cache_delete($group . '::' . $cache_id);
	}
    /**
     * Get all cached data by groups
     *
     * @return array
     */
    public function getAll()
    {
        parent::getAll();
        $data   = array();
        $groups = $this->getGroups();
        foreach ($groups as $group => $value) {
            $item = new JCacheStorageHelper($group);
            $item->updateSize($value['size'] / 1024);
            $item->count = $value['count'];
            $data[$group] = $item;
        }
        return $data;
    }
    /**
     * Get all the groups in cache
     * 
     * @return array|boolean
     */
    protected function getGroups()
    {
        return zend_shm_cache_fetch(self::CACHE_ZS_SYSTEM . '::' . self::CACHE_ZS_GROUPS);
    }
    /**
     * Add a group in the cache
     *
     * @params string $group
     * @params integer $size size of the cache data
     * @return boolean
     */
    protected function updateGroup($group, $size = 0)
    {
        $groups = $this->getGroups();
        if (!isset($groups[$group])) {
            $groups[$group] = array(
                'size'  => $size,
                'count' => 1
            );
         } else {
            $groups[$group]['size'] += $size;
            $groups[$group]['count']++;
        }
        return zend_shm_cache_store(self::CACHE_ZS_SYSTEM . '::' . self::CACHE_ZS_GROUPS, $groups); 
    }
    /**
     * Remove a group from the cache
     *
     * @params string $group
     * @return boolean
     */
    protected function removeGroup($group)
    {
        $groups = $this->getGroups();
        if (!isset($groups[$group])) {
            return false;
        }
        unset($groups[$group]);
        return zend_shm_cache_store(self::CACHE_ZS_SYSTEM . '::' . self::CACHE_ZS_GROUPS, $groups);
    }
	/**
	 * Clean cache for a group given a mode.
	 *
	 * group mode		: cleans all cache in the group
	 * notgroup mode	: cleans all cache not in the group
	 *
	 * @param	string	$group	The cache data group
	 * @param	string	$mode	The mode for cleaning cache [group|notgroup]
	 * @return	boolean	True on success, false otherwise
	 * @since	11.1
	 */
	public function clean($group, $mode = null)
	{
        if ($mode == 'group') {
            $this->removeGroup($group);
            return zend_shm_cache_clear($group);
        } else {
            $groups = $this->getGroups();
            foreach ($groups as $key => $value) {
                if ($key != $group) {
                    $this->removeGroup($key);
                    $result = zend_shm_cache_clear($key);
                    if (false === $result) {
                        return false;
                    }
                }
            }
            return true;
        } 
	}
	/**
	 * Garbage collect expired cache data
	 *
	 * @return boolean  True on success, false otherwise.
	 * @since	11.1
	 */
	public function gc() {
		// dummy, Zend Server has builtin garbage collector
		return true;
		
	}
	/**
	 * Test to see if the cache storage is available.
	 *
	 * @static
	 * @access public
	 * @return boolean  True on success, false otherwise.
	 */
	public static function isSupported()
	{
		return (extension_loaded('Zend Data Cache'));
	}
	/**
	 * Lock cached item - override parent as this is more efficient
	 *
	 * @param	string	$id		The cache data id
	 * @param	string	$group	The cache data group
	 * @param	integer	$locktime Cached item max lock time
	 * @return	boolean	True on success, false otherwise.
	 * @since	11.1
	 */
	public function lock($id, $group, $locktime)
	{
		$returning = new stdClass();
		$returning->locklooped = false;

		$looptime = $locktime * 10;

		$cache_id = $this->_getCacheId($id, $group).'_lock';

		$data_lock = zend_shm_cache_store($group . '::' . $cache_id, 1, $locktime);

		if ( $data_lock === FALSE ) {

			$lock_counter = 0;

			// loop until you find that the lock has been released.  that implies that data get from other thread has finished
			while ( $data_lock === FALSE ) {

				if ( $lock_counter > $looptime ) {
					$returning->locked 		= false;
					$returning->locklooped 	= true;
					break;
				}

				usleep(100);
				$data_lock = zend_shm_cache_store($group . '::' . $cache_id, 1, $locktime);
				$lock_counter++;
			}

		}
		$returning->locked = $data_lock;

		return $returning;
	}
	/**
	 * Unlock cached item - override parent for cacheid compatibility with lock
	 *
	 * @param	string	$id		The cache data id
	 * @param	string	$group	The cache data group
	 * @return	boolean	True on success, false otherwise.
	 * @since	1.1.1
	 */
	public function unlock($id, $group = null)
	{
		$cache_id = $this->_getCacheId($id, $group).'_lock';
		return zend_shm_cache_delete($group . '::' . $cache_id);
	}
}
