<?php

/* Copyright (C) 2016	Marcos García	<marcosgdf@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Class ProductAttribute
 * Used to represent a product attribute
 */
class ProductAttribute
{
	/**
	 * Database handler
	 * @var DoliDB
	 */
	private $db;

	/**
	 * Id of the product attribute
	 * @var int
	 */
	public $id;

	/**
	 * Ref of the product attribute
	 * @var
	 */
	public $ref;

	/**
	 * Label of the product attribute
	 * @var string
	 */
	public $label;

	/**
	 * Order of attribute.
	 * Lower ones will be shown first and higher ones last
	 * @var int
	 */
	public $rang;

	public function __construct(DoliDB $db)
	{
		global $conf;

		$this->db = $db;
		$this->entity = $conf->entity;
	}

	/**
	 * Fetches the properties of a product attribute
	 *
	 * @param int $id Attribute id
	 * @return int <1 KO, >1 OK
	 */
	public function fetch($id)
	{
		if (!$id) {
			return -1;
		}

		require_once __DIR__.'/../lib/product_attributes.lib.php';

		$sql = "SELECT rowid, ref, label, rang FROM ".MAIN_DB_PREFIX."product_attribute WHERE rowid = ".(int) $id." AND entity IN (".getProductEntities($this->db).")";

		$query = $this->db->query($sql);

		if (!$this->db->num_rows($query)) {
			return -1;
		}

		$result = $this->db->fetch_object($query);

		$this->id = $result->rowid;
		$this->ref = $result->ref;
		$this->label = $result->label;
		$this->rang = $result->rang;

		return 1;
	}

	/**
	 * Returns an array of all product attributes
	 *
	 * @return ProductAttribute[]
	 */
	public function fetchAll()
	{
		require_once __DIR__.'/../lib/product_attributes.lib.php';

		$return = array();

		$sql = 'SELECT rowid, ref, label, rang FROM '.MAIN_DB_PREFIX."product_attribute WHERE entity IN (".getProductEntities($this->db).')';
		$sql .= $this->db->order('rang', 'asc');
		$query = $this->db->query($sql);

		while ($result = $this->db->fetch_object($query)) {

			$tmp = new ProductAttribute($this->db);
			$tmp->id = $result->rowid;
			$tmp->ref = $result->ref;
			$tmp->label = $result->label;
			$tmp->rang = $result->rang;

			$return[] = $tmp;
		}

		return $return;
	}

	/**
	 * Creates a product attribute
	 *
	 * @return int <0 KO, >0 OK
	 */
	public function create()
	{
		//Ref must be uppercase
		$this->ref = strtoupper($this->ref);

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."product_attribute (ref, label, entity, rang) 
		VALUES ('".$this->db->escape($this->ref)."', '".$this->db->escape($this->label)."', ".(int) $this->entity.", ".(int) $this->rang.")";
		$query = $this->db->query($sql);

		if ($query) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'product_attribute');

			return 1;
		}

		return -1;
	}

	/**
	 * Updates a product attribute
	 *
	 * @return int <0 KO, >0 OK
	 */
	public function update()
	{
		//Ref must be uppercase
		$this->ref = strtoupper($this->ref);

		$sql = "UPDATE ".MAIN_DB_PREFIX."product_attribute SET ref = '".$this->db->escape($this->ref)."', label = '".$this->db->escape($this->label)."', rang = ".(int) $this->rang." WHERE rowid = ".(int) $this->id;

		if ($this->db->query($sql)) {
			return 1;
		}

		return -1;
	}

	/**
	 * Deletes a product attribute
	 *
	 * @return int <0 KO, >0 OK
	 */
	public function delete()
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."product_attribute WHERE rowid = ".(int) $this->id;

		if ($this->db->query($sql)) {
			return 1;
		}

		return -1;
	}

	/**
	 * Returns the number of products that are using this attribute
	 *
	 * @return int
	 */
	public function countChildProducts()
	{
		require_once __DIR__.'/../lib/product_attributes.lib.php';

		$sql = "SELECT COUNT(*) count FROM ".MAIN_DB_PREFIX."product_attribute_combination2val pac2v
		LEFT JOIN ".MAIN_DB_PREFIX."product_attribute_combination pac ON pac2v.fk_prod_combination = pac.rowid WHERE pac2v.fk_prod_attr = ".(int) $this->id." AND pac.entity IN (".getProductEntities($this->db).")";

		$query = $this->db->query($sql);

		$result = $this->db->fetch_object($query);

		return $result->count;
	}

	/**
	 * Reorders the order of the attributes.
	 * This is an internal function used by moveLine function
	 *
	 * @return int <0 KO >0 OK
	 */
	protected function reorderLines()
	{
		$tmp_order = array();

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'product_attribute WHERE rang = 0';
		$sql .= $this->db->order('rang, rowid', 'asc');

		$query = $this->db->query($sql);

		if (!$query) {
			return -1;
		}

		while ($result = $this->db->fetch_object($query)) {
			$tmp_order[] = $result->rowid;
		}

		foreach ($tmp_order as $order => $rowid) {
			$tmp = new ProductAttribute($this->db);
			$tmp->fetch($rowid);
			$tmp->rang = $order+1;

			if ($tmp->update() < 0) {
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Internal function to handle moveUp and moveDown functions
	 *
	 * @param string $type up/down
	 * @return int <0 KO >0 OK
	 */
	private function moveLine($type)
	{
		if ($this->reorderLines() < 0) {
			return -1;
		}

		$this->db->begin();

		if ($type == 'up') {
			$newrang = $this->rang - 1;
		} else {
			$newrang = $this->rang + 1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_attribute SET rang = '.$this->rang.' WHERE rang = '.$newrang;

		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return -1;
		}

		$this->rang = $newrang;

		if ($this->update() < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Shows this attribute before others
	 *
	 * @return int <0 KO >0 OK
	 */
	public function moveUp()
	{
		return $this->moveLine('up');
	}

	/**
	 * Shows this attribute after others
	 * 
	 * @return int <0 KO >0 OK
	 */
	public function moveDown()
	{
		return $this->moveLine('down');
	}

	/**
	 * Updates the order of all attributes. Used by AJAX page for drag&drop
	 *
	 * @param DoliDB $db Database handler
	 * @param array $order Array with row id ordered in ascendent mode
	 * @return int <0 KO >0 OK
	 */
	public static function bulkUpdateOrder(DoliDB $db, array $order)
	{
		$tmp = new ProductAttribute($db);

		foreach ($order as $key => $attrid) {
			if ($tmp->fetch($attrid) < 0) {
				return -1;
			}

			$tmp->rang = $key;

			if ($tmp->update() < 0) {
				return -1;
			}
		}

		return 1;
	}
}