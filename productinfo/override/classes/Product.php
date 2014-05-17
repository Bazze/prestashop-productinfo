<?php

class Product extends ProductCore
{
	
	public $extra = array();	

	public function __construct($id_product = null, $full = false, $id_lang = null, $id_shop = null, Context $context = null)
	{
		parent::__construct($id_product, $full, $id_lang, $id_shop, $context);
		if ($this->id) {
			$this->extra = $this->getExtraProductInfo($this->id, $id_lang);
		}
	}
	
	public static function getExtraProductInfo($id_product, $id_lang) {
		$sql = "SELECT * from `" . _DB_PREFIX_ . "product_info`
				WHERE `id_shop` = ".(int)Context::getContext()->shop->id." AND `id_product` = " . (int)$id_product .
				($id_lang != NULL ? " AND `id_lang` = " . (int)$id_lang : '');
		$result = Db::getInstance()->ExecuteS($sql);
		if (is_array($result) && count($result) > 0) {
			$ignoreColumns = array("id_product", "id_lang", "id_shop");
			if ($id_lang == NULL) {
				$new_result = array();
				foreach ($result as $field) {
					foreach ($field as $field_name => $data) {
						if (!in_array($field_name, $ignoreColumns)) {
							$new_result[$field_name][$field['id_lang']] = $data;
						}
					}
				}
				$result = $new_result;
			} else {
				foreach ($ignoreColumns as $value) {
					unset($result[0][$value]);
				}
				$result = $result[0];
			}
		} else {
			// Just in case there is no product entry in the product_info table
			Module::getInstanceByName("productinfo");
			$fields = productinfo::get_all_fields();
			$result = array();
			foreach ($fields as $field) {
				$result[$field['name']] = "";
			}
		}
		return ($result ? $result : array());
	}
	
	/**
	* Get all available products
	*
	* @param integer $id_lang Language id
	* @param integer $start Start number
	* @param integer $limit Number of products to return
	* @param string $order_by Field for ordering
	* @param string $order_way Way for ordering (ASC or DESC)
	* @return array Products details
	*/
	public static function getProducts($id_lang, $start, $limit, $order_by, $order_way, $id_category = false,
		$only_active = false, Context $context = null)
	{
		if (!$context)
			$context = Context::getContext();

		$front = true;
		if (!in_array($context->controller->controller_type, array('front', 'modulefront')))
			$front = false;

		if (!Validate::isOrderBy($order_by) || !Validate::isOrderWay($order_way))
			die (Tools::displayError());
		if ($order_by == 'id_product' || $order_by == 'price' || $order_by == 'date_add')
			$order_by_prefix = 'p';
		else if ($order_by == 'name')
			$order_by_prefix = 'pl';
		else if ($order_by == 'position')
			$order_by_prefix = 'c';

		if (strpos($order_by, '.') > 0)
		{
			$order_by = explode('.', $order_by);
			$order_by_prefix = $order_by[0];
			$order_by = $order_by[1];
		}
		$sql = 'SELECT p.*, product_shop.*, pl.* , t.`rate` AS tax_rate, m.`name` AS manufacturer_name, s.`name` AS supplier_name
				FROM `'._DB_PREFIX_.'product` p
				'.Shop::addSqlAssociation('product', 'p').'
				INNER JOIN ps_product product_shop
				LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (p.`id_product` = pl.`id_product` '.Shop::addSqlRestrictionOnLang('pl').')
				LEFT JOIN `'._DB_PREFIX_.'tax_rule` tr ON (product_shop.`id_tax_rules_group` = tr.`id_tax_rules_group`
		 		  AND tr.`id_country` = '.(int)Context::getContext()->country->id.'
		 		  AND tr.`id_state` = 0)
	  		 	LEFT JOIN `'._DB_PREFIX_.'tax` t ON (t.`id_tax` = tr.`id_tax`)
				LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
				LEFT JOIN `'._DB_PREFIX_.'supplier` s ON (s.`id_supplier` = p.`id_supplier`)'.
				($id_category ? 'LEFT JOIN `'._DB_PREFIX_.'category_product` c ON (c.`id_product` = p.`id_product`)' : '').'
				WHERE pl.`id_lang` = '.(int)$id_lang.
					($id_category ? ' AND c.`id_category` = '.(int)$id_category : '').
					($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '').
					($only_active ? ' AND product_shop.`active` = 1' : '').'
				ORDER BY '.(isset($order_by_prefix) ? pSQL($order_by_prefix).'.' : '').'`'.pSQL($order_by).'` '.pSQL($order_way).
				($limit > 0 ? ' LIMIT '.(int)$start.','.(int)$limit : '');
		$rq = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
		if ($order_by == 'price')
			Tools::orderbyPrice($rq, $order_way);
		
		/* Extra product info module v1.0 */	
		if ($rq && is_array($rq)) {
			foreach ($rq as $key => $product) {
				$rq[$key]['extra'] = Product::getExtraProductInfo($product['id_product'], $id_lang);
			}
		}
		/* End extra product info module v1.0 */	
		
		return ($rq);
	}
	
	public static function getProductsProperties($id_lang, $query_result)
	{
		$results_array = array();

		if (is_array($query_result))
			foreach ($query_result as $row)
				if ($row2 = Product::getProductProperties($id_lang, $row)) {
					/* Extra product info module v1.0 */	
					$row2['extra'] = Product::getExtraProductInfo($row2['id_product'], $id_lang);
					/* End extra product info module v1.0 */
					$results_array[] = $row2;
				}

		return $results_array;
	}

}

