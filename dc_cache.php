<?php

/* Possibly Related Classroom Projects Wordpress Plugin
 * Copyright 2008  Social Actions  (email : peter@socialactions.com)
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * 
 * 
 * @author      Social Actions <peter[at]socialactions[dot]com>
 * @author      E. Cooper <smirkingsisyphus[at]gmail[dot]com>
 * @copyright   2008 Social Actions
 * @license     http://www.gnu.org/licenses/gpl-3.0.html
 * @link        http://www.socialactions.com/labs/wordpress-donorschoose-plugin
 * 
 */
 

class RelatedActionsCache {

    var $db = NULL;
    var $table = '';
    var $post = NULL;
    var $cache = array();
    
	/*
	 * Constructor for the PRCP Wordpress class. Uses a post ID to initalize itself.
	 *	 
	 * @params integer $post post ID of a given post
	 * @returns bool
	 */
	function RelatedActionsCache( $table, $post=false ) {
		global $wpdb;

        if ( !$wpdb || !is_object($wpdb) )
            return NULL;
            
        $this->db = $wpdb;
		$this->table = $this->db->escape($table);
		if ( $post )
			$this->post = $post;
	}
    
	/*
	 * Checks if a cached result exists for a given post
	 *	 
	 * @returns bool
	 */
	function exists($id = false) {
        $cache = $this->get($id);
        if ( !$cache )
            return false;

        return $cache;
	}
	
	/*
	 * Returns number of hours between now and last cache update
	 *	 
	 * @returns integer $lastUpdate time in hours since last update
	 */
	function lastUpdate( $id = false ) {
		$id = $this->getID($id);
        if ( !$id )
			return NULL;
        if (!$this->exists())
            return 999;
        
		$sql = $this->db->prepare(
            'SELECT last_update FROM ' . $this->table . ' ' . ' WHERE post_id = %d',
            $id
        );
		$updateTime = $this->db->get_var($sql);
		
		if ( !$updateTime )
			return 999;	
		
		$updateTime = strtotime($updateTime);
		$current = strtotime(date("Y-m-d H:i:s"));		
		$lastUpdate = ( $current - $updateTime ) / 3600;		
		
		return $lastUpdate;
	}
	
	/*
	 * A short-cut function for raWordPressCache::exists() and 
	 * raWordPressCache::lastUpdate(). Checks if cache exists and 
	 * was updated within a given number of hours.
	 *	 
	 * @params integer $maxAge max hours between now and last cache update
	 * @returns bool
	 */
	function isValid( $maxAge=24, $id = false ) {
		$id = $this->getID($id);
        if ( !$id )
			return false;
			
		if ( $this->exists() && ($this->lastUpdate() < $maxAge) )
			return true;
			
		return false; 
	}
	
	/*
	 * Finds and returns cached result for a given post. If no cache can
	 * be found, the function returns false.
	 *	 
	 * @returns mixed $results string of raw html on success, bool false on failure.
	 */
	function get( $id = false) {
		$id = $this->getID($id);
        if ( !$id )
			return false;
            
        if ( !isset($this->cache[$id]) ) {
            $sql = $this->db->prepare(
                'SELECT cached_result FROM ' . $this->table . ' WHERE post_id = %d',
                 $id
            );
            $cache = $this->db->get_var( $sql );
            $this->cache[$id] = $cache;
        } else {
            $cache = $this->cache[$id];
        }

		if ( !$cache )
			return false;
        
		return $cache;
	}

    function set($results) {
        if ( $this->exists() ) {
            return $this->update($results);
        } else {
            return $this->add($results);
        }
    }
	
	/*
	 * Adds a cached result into database given a post
	 *	 
	 * @params mixed $results raw html generally in the form of a string.
	 * @returns bool
	 */
	function add( $results, $id = false ) {
		$id = $this->getID($id);
        if ( !$id )
			return false;

        if ( $this->exists() ) {
            return $this->update($results);
        }

        $insert = array( 'post_id' => $id,
                         'cached_result' => $results );
		if ( !$this->db->insert($this->table, $insert) )
			return false;
	 
		return true;
	}
	
	/*
	 * Updates a given cached result in the database.
	 *	 
	 * @params mixed $results raw html generally in the form of a string.
	 * @params bool $forece flag to force last_update timestamp to update to current time
	 * @returns bool
	 */
	function update ( $results, $id = false, $force = false ) {
		$id = $this->getID($id);
        if ( !$id )
			return false;

		$update = array( 'cached_result' => $results );
        $where = array( 'post_id' => $id );

        if ( !$this->db->update($this->table, $update, $where) ) {
            return false;
        }
			
		if ( $force ) {
            $update = array( 'last_update' => NULL );
			if ( !$this->db->update($this->table, $update, $where) ) {
                return false;
            }
		}	
		
		return true;
	}
	
	/*
	 * Function used to call a random cached result from the database. Changed from static
	 * to normal method due to PHP4 lacking such a feature
	 *	 
	 * @returns mixed $results raw html generally in the form of a string.
	 */
	function random() {
        $sql = 'SELECT MAX(cache_id) FROM ' . $this->table;
		$maxID = $wpdb->get_var( $sql );
		$randomID = mt_rand( 1, $maxID );

        $sql = $this->db->prepare(
            'SELECT cached_result FROM ' . $this->table . ' WHERE cache_id >= %d ' .
            'AND post_id >= 1 ORDER BY cache_id ASC LIMIT 1',
            $randomID
        );
		$cache = $this->db->get_var($sql);

		if ( !$cache )
			return "";		
			
		return $cache;			
	}
    
    function getID( $id = false ) {
        if ( !intval($id) ) {
            $id = $this->post;
        } else {
            $this->post = $id;
        }

        return $id;
    }

    function remove( $id = false ) {
		$id = $this->getID($id);
        if ( !$id )
			return NULL;

        $sql = $this->db->prepare(
            'DELETE FROM ' . $this->table . ' WHERE post_id = %d LIMIT 1',
            $id
        );
        $results = $this->db->query($sql);

        if ( !$results ) {
            return false;
        }

        return true;
    }

    /*function expire() {
        $update = array( 'last_update' => 1255747887 );
        $this->db->update($this->table, $update);
    }*/
}

?>