CREATE TABLE IF NOT EXISTS `_DB_PREFIX_product_info` (
`id_product` INT NOT NULL ,
`id_shop` INT UNSIGNED NOT NULL DEFAULT '1' ,
`id_lang` INT NOT NULL,
INDEX ( `id_product`, `id_shop`, `id_lang` )
) ENGINE = _MYSQL_ENGINE_  DEFAULT CHARSET=utf8;
#[NEW_QUERY]#
CREATE TABLE IF NOT EXISTS `_DB_PREFIX_product_info_fields` (
`name` VARCHAR( 255 ) NOT NULL ,
`label` VARCHAR( 255 ) NOT NULL ,
`help_text` TEXT NOT NULL ,
`type` TEXT NOT NULL ,
`length` INT NOT NULL ,
PRIMARY KEY (`name`)
) ENGINE = _MYSQL_ENGINE_  DEFAULT CHARSET=utf8;