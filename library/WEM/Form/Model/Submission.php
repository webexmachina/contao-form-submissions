<?php

/**
 * Module Planning for Contao Open Source CMS
 *
 * Copyright (c) 2018 Web ex Machina
 *
 * @author Web ex Machina <https://www.webexmachina.fr>
 */

namespace WEM\Form\Model;

use Exception;
use Contao\Model;

/**
 * Reads and writes items
 */
class Submission extends Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_wem_form_submission';

	/**
	 * Find items, depends on the arguments
	 * @param Array
	 * @param Int
	 * @param Int
	 * @param Array
	 * @return Collection
	 */
	public static function findItems($arrConfig = array(), $intLimit = 0, $intOffset = 0, $arrOptions = array())
	{
		try
		{
			$t = static::$strTable;
			$arrColumns = static::formatColumns($arrConfig);
				
			if($intLimit > 0)
				$arrOptions['limit'] = $intLimit;

			if($intOffset > 0)
				$arrOptions['offset'] = $intOffset;

			if(!isset($arrOptions['order']))
				$arrOptions['order'] = "$t.createdAt ASC";

			if(empty($arrColumns))
				return static::findAll($arrOptions);
			else
				return static::findBy($arrColumns, null, $arrOptions);
		}
		catch(Exception $e)
		{
			throw $e;
		}
	}

	/**
	 * Count items, depends on the arguments
	 * @param Array
	 * @param Array
	 * @return Integer
	 */
	public static function countItems($arrConfig = array(), $arrOptions = array())
	{
		try
		{
			$t = static::$strTable;
			$arrColumns = static::formatColumns($arrConfig);

			if(empty($arrColumns))
				return static::countAll($arrOptions);
			else
				return static::countBy($arrColumns, null, $arrOptions);
		}
		catch(Exception $e)
		{
			throw $e;
		}
	}

	/**
	 * Format ItemModel columns
	 * @param  [Array] $arrConfig [Configuration to format]
	 * @return [Array]            [The Model columns]
	 */
	public static function formatColumns($arrConfig)
	{
		try
		{
			$t = static::$strTable;
			$arrColumns = array();

			if($arrConfig["pid"])
				$arrColumns[] = "$t.pid = ". $arrConfig["pid"];

			if($arrConfig["date_start"] && $arrConfig["date_stop"]){
				if(!$arrConfig["date_operator"])
					$arrConfig["date_operator"] = "OR";

				$arrColumns[] = "($t.date >= ". $arrConfig["date_start"]." ".$arrConfig["date_operator"]." $t.date <= ". $arrConfig["date_stop"].")";
			}
			else{
				if($arrConfig["createdAt_start"])
					$arrColumns[] = "$t.createdAt >= ". $arrConfig["createdAt_start"];

				if($arrConfig["createdAt_stop"])
					$arrColumns[] = "$t.createdAt <= ". $arrConfig["createdAt_stop"];
			}

			if($arrConfig["status"])
				$arrColumns[] = "$t.status = '". $arrConfig["status"] ."'";

			if($arrConfig["token"])
				$arrColumns[] = "$t.token = '". $arrConfig["token"] ."'";

			if($arrConfig["not"])
				$arrColumns[] = $arrConfig["not"];

			return $arrColumns;
		}
		catch(Exception $e)
		{
			throw $e;
		}
	}
}