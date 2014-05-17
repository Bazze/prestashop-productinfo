<?php

include(dirname(__FILE__).'/../../config/config.inc.php');

if (!Employee::checkPassword((int)Tools::getValue('id_employee'), Tools::getValue('passwd'))) {
	die(json_encode(array('error' => true)));
}

$id_supplier = pSQL(urldecode(Tools::getValue('id_supplier')));
$id_manufacturer = pSQL(urldecode(Tools::getValue('id_manufacturer')));
$id_category = (int)Tools::getValue('id_category');
$id_lang = (int)Tools::getValue('id_lang');

$PS_VERSION = substr(_PS_VERSION_, 0, 3);

function bulkupdate_write_status($msg) {
	@file_put_contents(dirname(__FILE__).'/bulk-update.txt', "# " . $msg . @file_get_contents(dirname(__FILE__).'/bulk-update.txt'));
}

if (Tools::getValue('action') == 'update') {
	set_time_limit(0);
	@file_put_contents(dirname(__FILE__).'/bulk-update.txt', "");
	if ($id_supplier != 0 || $id_manufacturer != 0 || $id_category != 0) {
		$product_exceptions = (Tools::getValue('product_exceptions') ? Tools::getValue('product_exceptions') : array());
		$sql = "SELECT pl.`id_product`, pl.`name` from `" . _DB_PREFIX_ . "product_lang` pl
				LEFT JOIN `" . _DB_PREFIX_ . "product` p
					ON p.`id_product` = pl.`id_product`
				LEFT JOIN `" . _DB_PREFIX_ . "category_product` cp
					ON cp.`id_product` = pl.`id_product`
				WHERE (pl.`id_lang` = {$id_lang} OR pl.`id_lang` = " . (int)Configuration::get('PS_LANG_DEFAULT') .") " . (!empty($product_exceptions) ? "AND pl.`id_product` NOT IN (" . pSQL(implode(",", $product_exceptions)) . ")" : '') . " ";
		if ($PS_VERSION != "1.4") {
			$sql .= " AND p.`id_shop_default` = " . (int)Context::getContext()->shop->id . " ";
		}
		if ($id_supplier != 0) {
			$sql .= "AND " . ($id_manufacturer != 0 || $id_category != 0 ? '(' : '') . "p.`id_supplier` IN ({$id_supplier}) ";
		}
		if ($id_category != 0) {
			$sql .= ($id_supplier == 0 ? ' AND ' . ($id_manufacturer != 0 ? '(' : '') : ' OR ') . " (cp.`id_category` = {$id_category} OR p.`id_category_default` = {$id_category}) " . ($id_supplier != 0 && $id_manufacturer == 0 ? ')' : '');
		}
		if ($id_manufacturer != 0) {
			$sql .= ($id_supplier != 0 || $id_category != 0 ? ' OR ' : ' AND ') . "p.`id_manufacturer` IN ({$id_manufacturer})" . ($id_supplier != 0 || $id_category != 0 ? ')' : '');
		}
		$sql .= "\nGROUP by pl.`id_product`";
		bulkupdate_write_status('Fetching products according to selected criteria<br />');
		bulkupdate_write_status('<a href="#sql" style="font-family:Courier;color:green;" onclick="$(\'#sql_query\').toggle();return false;">Click here to see the products SQL query</a><pre id="sql_query" style="display:none;font-family:Courier;">' . str_replace("\t\t\t\t", "", $sql) . '</pre><br />');
		$products = Db::getInstance()->ExecuteS($sql);
		if ($products) {
			$fields = Tools::getValue('fields');
			if (is_array($fields)) {
				$i = 1;
				$total_products = count($products);
				$updated_products = 0;
				bulkupdate_write_status($total_products . ' ' . ($total_products > 1 ? 'products' : 'product') . ' matched your criteria and will be updated<br />');
				foreach ($products as $product) {
					$error = array();
					foreach ($fields as $field_name => $field_data) {					
						foreach ($field_data as $id_lang => $data) {
							if ($data != "" || (bool)Tools::getValue('force_empty')) {
								if ($PS_VERSION != "1.4") {
									$sql = "SELECT * from `"._DB_PREFIX_."product_info`
											WHERE `id_product` = " . (int)$product['id_product'] . " AND
												  `id_shop` = " . (int)Context::getContext()->shop->id . " AND
												  `id_lang` = " . (int)$id_lang;
								} else {
									$sql = "SELECT * from `"._DB_PREFIX_."product_info`
											WHERE `id_product` = " . (int)$product['id_product'] . " AND
												  `id_lang` = " . (int)$id_lang;
								}
								$result = Db::getInstance()->ExecuteS($sql);
								if ($result && count($result) > 0) {
									if ($PS_VERSION != "1.4") {
										$sql = "UPDATE `"._DB_PREFIX_."product_info`
												SET `" . pSQL($field_name) . "` = '".pSQL($data)."'
												WHERE `id_product` = " . (int)$product['id_product'] . " AND
													  `id_shop` = " . (int)Context::getContext()->shop->id . " AND
													  `id_lang` = " . (int)$id_lang;
									} else {
										$sql = "UPDATE `"._DB_PREFIX_."product_info`
												SET `" . pSQL($field_name) . "` = '".pSQL($data)."'
												WHERE `id_product` = " . (int)$product['id_product'] . " AND
													  `id_lang` = " . (int)$id_lang;
									}
									if (!Db::getInstance()->Execute($sql)) {
										$error[] = Db::getInstance()->getMsgError();
									}
								} else {
									$sql = "INSERT into `"._DB_PREFIX_."product_info`(`id_product`, `id_shop`, `id_lang`, `" . pSQL($field_name) . "`)
											VALUES(" . (int)$product['id_product'] . ", ".($PS_VERSION != "1.4" ? Context::getContext()->shop->id : 1).", " . (int)$id_lang . ", '".pSQL($data)."')";
									if (!Db::getInstance()->Execute($sql)) {
										$error[] = Db::getInstance()->getMsgError();
									}
								}
							}
						}
					}
					if (empty($error)) {
						$updated_products++;
						bulkupdate_write_status("[". $i++ ."/" . $total_products . "] Updated product <b>{$product['name']}</b> [id:{$product['id_product']}]<br />");
					} else {
						bulkupdate_write_status("[". $i++ ."/" . $total_products . "] <span style=\"font-family:Courier;color:red;\">Could not update product <b>{$product['name']}</b> [id:{$product['id_product']}] Error: " . implode("<br />", $error) . "</span><br />");
					}
				}
				bulkupdate_write_status($updated_products . ' ' . ($updated_products > 1 ? 'products were' : 'product was') . ' updated<br />');
				if ($updated_products != $total_products) {
					bulkupdate_write_status('<span style=\"font-family:Courier;color:red;\">' . ($total_products-$update_products) . ' products were not updated</span><br />');
				}
			}
		} else {
			bulkupdate_write_status('No products matched your criteria <br />');
		}
	} else {
		bulkupdate_write_status('You have to choose which products should be updated<br />');
	}
} elseif (Tools::getValue('action') == 'get-status') {
	echo @file_get_contents(dirname(__FILE__).'/bulk-update.txt');
	@file_put_contents(dirname(__FILE__).'/bulk-update.txt', "");
}