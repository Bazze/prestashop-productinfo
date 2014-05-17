<?php

if (!defined('_PS_VERSION_'))
  exit;

function upgrade_module_1_2($object) {
	$sql = "ALTER TABLE `"._DB_PREFIX_."product_info` ADD `id_shop` INT UNSIGNED NOT NULL DEFAULT '1' AFTER `id_product` , ADD INDEX ( `id_shop` )";
	Db::getInstance()->Execute($sql);
}