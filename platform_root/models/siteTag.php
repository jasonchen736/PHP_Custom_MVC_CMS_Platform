<?php

	class siteTag extends bModel {
		// active record table
		protected $_table = 'siteTag';
		// history table (optional)
		protected $_historyTable = 'siteTagHistory';
		// field name of primary key
		protected $_id = 'siteTagID';
		// array of db field name => required
		//   array(field name => boolean)
		protected $_fields = array(
			'siteTagID' => false,
			'languageID' => true,
			'referrer' => true,
			'description' => true,
			'matchType' => true,
			'matchValue' => true,
			'placement' => true,
			'weight' => true,
			'HTTP' => false,
			'HTTPS' => false,
			'status' => true,
			'dateAdded' => false,
			'lastModified' => false
		);
		// tag/value array for variable content substitution
		private static $variables;
		// this array will keep track of which tags have been placed on the current page
		//   will prevent overlapping placements
		//   array(siteTagID => array(vals) ... )
		private static $retrievedSiteTags = array();

		/**
		 *  Assign a variable
		 *  Args: none
		 *	Return: none
		 */
		public static function assign($id, $value) {
			if (!is_array(self::$variables)) {
				self::$variables = array();
			}
			self::$variables['{'.strtoupper($id).'}'] = $value;
		} // function assign
	
		/**
		 *  Substitutes variables found in site tag
		 *  Args: none
		 *	Return: none
		 */
		private static function replaceTags($tag) {
			if (is_array(self::$variables) && !empty(self::$variables)) {
				// variable ids
				$ids = array_keys(self::$variables);
				// variable values
				$vals = array_values(self::$variables);
				// replace each variable with its value
				$tag = str_ireplace($ids, $vals, $tag);
			}
			// strip remaining variables and return
			return preg_replace('/{[A-Z0-9]*}/', '', $tag);
		} // function replaceTags
	
		/**
		 *  Sort tags by weight
		 *  Args: (array) tag data, (array) tag data
		 *	Return: (int) sort value
		 */
		public static function sortTags($a, $b) {
			if ($a['weight'] == $b['weight']) {
				return 0;
			}
			return ($a['weight'] < $b['weight']) ? -1 : 1;
		} // function sortTags
		
		/**
		 *  Retrieve and process site tags by page match
		 *	Args: (str) processing mode, (str) placement type
		 *	Return: (mixed) depending on processing mode, tag string or boolean
		 */
		public static function getSiteTagsByMatch($mode = false, $placement = false) {
			$page = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
			if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) {
				$protocol = 'HTTPS';
			} else {
				$protocol = 'HTTP';
			}
			$sql = "SELECT `siteTagID`, `".$protocol."` AS `tag`, `weight`, 
						`matchType`, `matchValue`, `placement` 
					FROM `siteTag` 
					WHERE `status` = 'active' 
					AND `languageID` = ? 
					AND (
						`matchType` = 'regular expression' 
						OR (
							`matchType` = 'exact match' 
							AND `matchValue` IN (?, 'ALL')
						)
					)".($placement !== false ? " AND `placement` = '".$placement."'" : '');
			$result = query($sql, array(language::getCurrent('languageID'), $page));
			if ($result->count > 0) {
				$uri = $_SERVER['REQUEST_URI'];
				$matched = array();
				$retrieved = array();
				while ($row = $result->fetch()) {
					switch ($row['matchType']) {
						case 'regular expression':
							$regex = preg_replace('/\//', '\/', $row['matchValue']);
							if (preg_match('/'.$regex.'/', $uri)) {
								$matched[$row['siteTagID']] = $row;
							}
							break;
						case 'exact match':
						default:
							$matched[$row['siteTagID']] = $row;
							break;
					}
				}
				if (!empty($matched)) {
					foreach ($matched as $row) {
						// if the site tag has not already been pulled or placed, pull it
						if (!isset(self::$retrievedSiteTags[$row['siteTagID']])) {
							self::$retrievedSiteTags[$row['siteTagID']] = array();
							self::$retrievedSiteTags[$row['siteTagID']]['placed'] = false;
							self::$retrievedSiteTags[$row['siteTagID']]['weight'] = $row['weight'];
							self::$retrievedSiteTags[$row['siteTagID']]['placement'] = $row['placement'];
							// substitue tags, if any
							self::$retrievedSiteTags[$row['siteTagID']]['tag'] = self::replaceTags($row['tag']);
							$retrieved[$row['siteTagID']] = true;
						}
					}
				}
				return self::processRetrieved($mode, $retrieved);
			}
			return false;
		} // function getSiteTagsByMatch
	
		/**
		 *  Retrieve site tags by page match and return site tags
		 *	Args: (str) site tag placement type
		 *	Return: (str) retrieved site tags
		 */
		public static function returnSiteTagsByMatch($placement = false) {
			return self::getSiteTagsByMatch('return', $placement);
		} // function returnSiteTagsByMatch
	
		/**
		 *  Retrieve site tags by page match and queue up site tags
		 *	Args: (str) site tag placement type
		 *	Return: (boolean) successful retrieval
		 */
		public static function retrieveSiteTagsByMatch($placement = false) {
			return self::getSiteTagsByMatch('retrieve', $placement);
		} // function retrieveSiteTagsByMatch
	
		/**
		 *  Retrieve site tags by page match and echo site tags
		 *	Args: (str) site tag placement type
		 *	Return: (boolean) successful retrieval
		 */
		public static function echoSiteTagsByMatch($placement = false) {
			return self::getSiteTagsByMatch('echo', $placement);
		} // function echoSiteTagsByMatch
	
		/**
		 *  Retrieve and process a site tag by explicit match
		 *	Args: (str) match type, (str) match value, (str) processing mode
		 *  Args: (str) allow a site tag to be placed more than once per page
		 *	Return: (mixed) depending on processing mode, tag string or boolean
		 */
		public static function getSiteTagByExplicitMatch($matchType, $matchValue, $mode = false, $allowMultiple = false) {
			if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) {
				$protocol = 'HTTPS';
			} else {
				$protocol = 'HTTP';
			}
			$sql = "SELECT `siteTagID`, `".$protocol."` AS `tag`, `weight`, `placement` 
							FROM `siteTags` 
							WHERE `status` = 'active' 
							AND `languageID` = ? 
							AND `matchValue` = ? 
							AND `matchType` = ?";
			$result = query($sql, array(language::getCurrent('languageID'), $matchType, $matchValue));
			if ($result->count > 0) {
				$retrieved = array();
				while ($row = $result->fetch()) {
					if ($allowMultiple) {
						$instance = 1;
						while (isset(self::$retrievedSiteTags[$row['siteTagID'].$instance])) {
							++$instance;
						}
						$row['siteTagID'] .= $instance;
					}
					// if the tag has not already been pulled or placed, pull it and place it
					if (!isset(self::$retrievedSiteTags[$row['siteTagID']]) || !self::$retrievedSiteTags[$row['siteTagID']]['placed']) {
						// substitue tags, if any, and place
						self::$retrievedSiteTags[$row['siteTagID']] = array();
						self::$retrievedSiteTags[$row['siteTagID']]['placed'] = true;
						self::$retrievedSiteTags[$row['siteTagID']]['weight'] = $row['weight'];
						self::$retrievedSiteTags[$row['siteTagID']]['placement'] = $row['placement'];
						self::$retrievedSiteTags[$row['siteTagID']]['tag'] = self::replaceTags($row['tag']);
						$retrieved[$row['siteTagID']] = true;
					}
				}
				return self::processRetrieved($mode, $retrieved);
			}
			return false;
		} // function getSiteTagByExplicitMatch
	
		/**
		 *  Retrieve by explicit match and return
		 *	Args: (str) match type, (str) match value
		 *  Args: (str) allow a site tag to be placed more than once per page
		 *	Return: (str) site tags
		 */
		public static function returnSiteTagByExplicitMatch($matchType, $matchValue, $allowMultiple = false) {
			return self::getSiteTagByExplicitMatch($matchType, $matchValue, 'return', $allowMultiple);
		} // function returnSiteTagByExplicitMatch
	
		/**
		 *  Retrieve by explicit match and qeueu
		 *	Args: (str) match type, (str) match value
		 *  Args: (str) allow a site tag to be placed more than once per page
		 *	Return: (boolean) success retrieval
		 */
		public static function retrieveSiteTagByExplicitMatch($matchType, $matchValue, $allowMultiple = false) {
			return self::getSiteTagByExplicitMatch($matchType, $matchValue, 'retrieve', $allowMultiple);
		} // function retrieveSiteTagByExplicitMatch
	
		/**
		 *  Retrieve by explicit match and echo
		 *	Args: (str) match type, (str) match value
		 *  Args: (str) allow a site tag to be placed more than once per page
		 *	Return: (boolean) success retrieval
		 */
		public static function echoSiteTagByExplicitMatch($matchType, $matchValue, $allowMultiple = false) {
			return self::getSiteTagByExplicitMatch($matchType, $matchValue, 'echo', $allowMultiple);
		} // function echoSiteTagByExplicitMatch

		/**
		 *  Process retrieved site tags
		 *	Args: (str) processing mode, (array) array of retrived tags
		 *	Return: (mixed) depending on processing mode, tag string or boolean
		 */
		private static function processRetrieved($mode, $retrieved) {
			if (!empty($retrieved)) {
				if (count(self::$retrievedSiteTags) > 1) {
					uasort(self::$retrievedSiteTags, array('siteTag', 'sortTags'));
				}
				if ($mode) {
					$mode = strtolower($mode);
				}
				switch ($mode) {
					case 'return':
						$tags = '';
						// return retrieved site tags
						foreach (self::$retrievedSiteTags as $siteTagID => &$val) {
							if (isset($retrieved[$siteTagID]) && !$val['placed']) {
								$tags .= "\n\r".$val['tag']."\n\r";
								$val['placed'] = true;
							}
						}
						return $tags;
						break;
					case 'retrieve':
						// just queue the retrieved site tags
						return true;
						break;
					case 'echo':
					default:
						// echo retrieved site tags
						foreach (self::$retrievedSiteTags as $siteTagID => &$val) {
							if (isset($retrieved[$siteTagID]) && !$val['placed']) {
								echo "\n\r".$val['tag']."\n\r";
								$val['placed'] = true;
							}
						}
						return true;
						break;
				}
			}
			return false;
		} // function processRetrieved
	
		/**
		 *  Return all retrieved and unplaced site tags
		 *	Args: (str) site tag placement type
		 *	Return: (str) site tags
		 */
		public static function returnAllSiteTags($placement = false) {
			if (!empty(self::$retrievedSiteTags)) {
				$tags = '';
				// return all unplaced site tags
				foreach (self::$retrievedSiteTags as $key => &$val) {
					if (!$val['placed'] && ($placement === false || $placement == $val['placement'])) {
						$tags .= "\n\r".$val['tag']."\n\r";
						$val['placed'] = true;
					}
				}
				return $tags;
			}
			return false;
		} // function returnAllSiteTags
	
		/**
		 *  Echo all retrieved and unplaced site tags
		 *	Args: (str) site tag placement type
		 *	Return: (boolean) output success
		 */
		public static function echoAllSiteTags($placement = false) {
			if (!empty(self::$retrievedSiteTags)) {
				// echo all unplaced site tags
				foreach (self::$retrievedSiteTags as $key => &$val) {
					if (!$val['placed'] && ($placement === false || $placement == $val['placement'])) {
						echo "\n\r".$val['tag']."\n\r";
						$val['placed'] = true;
					}
				}
			}
			return false;
		} // function echoAllSiteTags
	
		/**
		 *  Append and sort site tags in argument array
		 *	Arg: (array) an array of site tags, (str) site tag placement type
		 *	Return: (array) array of site tags
		 */	
		public static function appendSiteTags($siteTags, $placement = false) {
			if (!is_array($siteTags)) {
				$siteTags = array();
			}
			// return all retrieved site tags
			foreach (self::$retrievedSiteTags as $key => &$val) {
				if (!$val['placed'] && ($placement === false || $placement == $val['placement'])) {
					$siteTags[$key] = array();
					$siteTags[$key]['siteTag'] = $val['tag'];
					$siteTags[$key]['weight'] = $val['weight'];
					$val['placed'] = true;
				}
			}
			if (count($siteTags) > 1) {
				uasort($siteTags, array('siteTag', 'sortTags'));
			}
			return $siteTags;
		} // function appendSiteTags
	
		/**
		 *  Reset all site tag placed flags
		 *  Args: none
		 *	Return: none
		 */
		public static function unplaceSiteTags() {
			foreach (self::$retrievedSiteTags as $key => &$val) {
				$val['placed'] = false;
			}
		} // function unplaceSiteTags

		/**
		 *  Pre save checks
		 *  Args: none
		 *  Return: (boolean) ok to save
		 */
		public function beforeSave() {
			$result = parent::beforeSave();
			if (!$this->exists()) {
				$this->siteTagID = NULL;
				$this->setRaw('dateAdded', 'NOW()');
				$this->setRaw('lastModified', 'NOW()');
			} else {
				$this->setRaw('lastModified', 'NOW()');
			}
			return $result;
		} // function beforeSave

		/**
		 *  Check for duplicate site tag
		 *  Args: none
		 *  Return: (boolean) is duplicate site tag
		 */
		public function isDuplicate() {
			$duplicate = false;
			$sql = "SELECT `siteTagID` 
				FROM `".$this->_table."` 
				WHERE `referrer` = ? 
				AND `description` = ? 
				AND `languageID` = ?";
			$result = query($sql, array($this->referrer, $this->description, $this->languageID));
			if ($result->count > 0) {
				$id = $this->siteTagID;
				while ($row = $result->fetch()) {
					if ($row['siteTagID'] != $id) {
						$this->addError('Duplicate site tag', 'duplicate');
						$duplicate = true;
					}
				}
			}
			return $duplicate;
		} // function isDuplicate
	} // class siteTag

?>
