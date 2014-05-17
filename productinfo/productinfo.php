<?php
/*
* Product info v1.2
*/

if ( !defined( '_PS_VERSION_' ) )
  exit;

if (!class_exists('SNSolutionsHelper'))
	include_once(dirname(__FILE__) . '/SNSolutionsHelper.class.php');

class ProductInfo extends Module {

	private $message;
	// 1 = success, 0 = error
	private $message_type;
	private $languages;
	private $default_form_language;
	private $PS_VERSION;

	public function __construct() {
		$this->name = 'productinfo';
		$this->tab = 'administration';
		$this->version = '1.2';
		$this->author = 'SN Solutions';
		$this->need_instance = 0;
		$this->module_key = '786b2598bda2df42d19192729beb32d0';

		parent::__construct();

		$this->displayName = $this->l( 'Extra product information' );
		$this->description = $this->l( 'Provide products with extra information' );
		$this->confirmUninstall = $this->l('Are you sure you want to delete all fields and settings made? NOTE (PS 1.4.x): The overridden class will be renamed to Product.php.bak.');

		$this->PS_VERSION = substr(_PS_VERSION_, 0, 3);
    }

	public function install() {
		return (
			parent::install() &&
			$this->installVersionSpecificStuff() &&
			$this->installSQL() &&
			$this->add_products()
		);
	}

	private function installVersionSpecificStuff() {
		if ($this->PS_VERSION == "1.4") {
			Db::getInstance()->Execute("UPDATE `" . _DB_PREFIX_ . "tab` SET `module` = 'productinfo' WHERE `class_name` = 'AdminCatalog'");
			return
				$this->override() &&
				$this->registerHook('deleteproduct') &&
				$this->registerHook('addproduct') &&
				$this->registerHook('updateProduct')
			;
		} elseif ($this->PS_VERSION == "1.5") {
			return
				$this->registerHook('actionProductAdd') &&
				$this->registerHook('actionProductUpdate') &&
				$this->registerHook('actionProductDelete') &&
				$this->registerHook('displayAdminProductsExtra')
			;
		}
	}

	private function uninstallVersionSpecificStuff() {
		$version = substr(_PS_VERSION_, 0, 3);
		if ($this->PS_VERSION == "1.4") {
			Db::getInstance()->Execute("UPDATE `" . _DB_PREFIX_ . "tab` SET `module` = NULL WHERE `class_name` = 'AdminCatalog'");
			$this->backupFile(dirname(__FILE__) . '/../../override/classes/', 'Product', '.php');
		} else {

		}
	}

	private function override() {
		$dir = dirname(__FILE__) . '/../../override/classes/';
		$filename = "Product";
		$ext = ".php";

		$this->backupFile($dir, $filename, $ext);

		if (copy(dirname(__FILE__) . '/' . $filename.$ext, $dir.$filename.$ext)) {
			return true;
		}
		return false;
	}

	private function backupFile($dir, $filename, $ext) {
		if (file_exists($dir.$filename.$ext)) {
			$i = 0;
			while (file_exists($dir.$filename.$ext.".bak".($i > 0 ? "-" . $i : ""))) {
				$i++;
			}
			rename($dir.$filename.$ext, $dir.$filename.$ext.".bak".($i > 0 ? "-" . $i : ""));
		}
	}

	private function installSQL() {
		$sql = file_get_contents(dirname(__FILE__) . '/install.sql');
		$sql = str_replace('_DB_PREFIX_', _DB_PREFIX_, $sql);
		$sql = str_replace('_MYSQL_ENGINE_', _MYSQL_ENGINE_, $sql);
		$sql = explode("#[NEW_QUERY]#", $sql);
		foreach ($sql as $query) {
			if (!Db::getInstance()->Execute($query)) {
				print Db::getInstance()->getMsgError();
				return false;
			}
		}
		return true;
	}

	public function add_products() {
		if ($this->PS_VERSION != "1.4") {
			$sql = "INSERT INTO `"._DB_PREFIX_."product_info`(`id_product`, `id_shop`, `id_lang`) SELECT p.`id_product`, p.`id_shop`, p.`id_lang` FROM `"._DB_PREFIX_."product_lang` p";
		} else {
			$sql = "INSERT INTO `"._DB_PREFIX_."product_info`(`id_product`, `id_lang`) SELECT p.`id_product`, p.`id_lang` FROM `"._DB_PREFIX_."product_lang` p";
		}
		if (Db::getInstance()->Execute($sql)) {
			return true;
		}
		print Db::getInstance()->getMsgError();
		return false;
	}

	public function uninstall() {
		Db::getInstance()->Execute("DROP TABLE `"._DB_PREFIX_."product_info`");
		Db::getInstance()->Execute("DROP TABLE `"._DB_PREFIX_."product_info_fields`");
		$this->uninstallVersionSpecificStuff();
		parent::uninstall();
	}

	public function hookdisplayAdminProductsExtra($params) {
		global $smarty, $currentIndex;
		$this->handle_language();
		$smarty->assign(
			array(
				"product" => new Product(Tools::getValue('id_product')),
				"languages" => $this->languages,
				"defaultFormLanguage" => $this->default_form_language,
				"fields" => $this->get_all_fields(),
				"backURL" => urlencode($currentIndex.'&id_product='.Tools::getValue('id_product').'&key_tab=ModuleProductinfo&updateproduct&token='.Tools::getAdminTokenLite('AdminProducts')),
				"img_admin" => _PS_ADMIN_IMG_
			)
		);
		return $this->display(__FILE__, 'product-tab.tpl');
	}

	public function hookactionProductDelete($params) {
		$this->hookdeleteProduct($params);
	}

	public function hookdeleteProduct($params) {
		if ($this->PS_VERSION != "1.4")	{
			Db::getInstance()->Execute("DELETE from `"._DB_PREFIX_."product_info` WHERE `id_product` = " . (int)$params['product']->id . " AND `id_shop` = " . (int)$this->context->shop->id);
		} else {
			Db::getInstance()->Execute("DELETE from `"._DB_PREFIX_."product_info` WHERE `id_product` = " . (int)$params['product']->id);
		}
	}

	public function hookactionProductAdd($params) {
		$this->hookaddProduct($params);
	}

	public function hookaddProduct($params) {
		if ($this->PS_VERSION != "1.4") {
			Db::getInstance()->Execute("INSERT into `"._DB_PREFIX_."product_info`(`id_product`, `id_shop`, `id_lang`) SELECT p.`id_product`, p.`id_shop`, p.`id_lang` FROM `"._DB_PREFIX_."product_lang` p WHERE p.`id_product` = " . (int)$params['product']->id . " AND p.`id_shop` = " . (int)$this->context->shop->id);
		} else {
			Db::getInstance()->Execute("INSERT into `"._DB_PREFIX_."product_info`(`id_product`, `id_lang`) SELECT p.`id_product`, p.`id_lang` FROM `"._DB_PREFIX_."product_lang` p WHERE p.`id_product` = " . (int)$params['product']->id);
		}
		$this->hookupdateProduct($params);
	}

	public function hookactionProductUpdate($params) {
		$params['product']->extra = Tools::getValue('extra');
		$this->hookupdateProduct($params);
	}

	public function hookupdateProduct($params) {
		if (is_array($params['product']->extra)) {
			foreach ($params['product']->extra as $field_name => $field_data) {
				foreach ($field_data as $id_lang => $data) {
					if ($this->PS_VERSION != "1.4") {
						$sql = "UPDATE `"._DB_PREFIX_."product_info`
								SET
									`" . pSQL($field_name) . "` = '".pSQL($data)."'
								WHERE `id_product` = " . (int)$params['product']->id . " AND
									  `id_shop` = " . (int)$this->context->shop->id . " AND
									  `id_lang` = " . (int)$id_lang;
					} else {
						$sql = "UPDATE `"._DB_PREFIX_."product_info`
								SET
									`" . pSQL($field_name) . "` = '".pSQL($data)."'
								WHERE `id_product` = " . (int)$params['product']->id . " AND
									  `id_lang` = " . (int)$id_lang;
					}
					Db::getInstance()->Execute($sql);
				}
			}
		}
	}

	private function handle_language() {
		global $cookie;
		$allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		if ($allow_employee_form_lang && !$cookie->employee_form_lang)
			$cookie->employee_form_lang = (int)(Configuration::get('PS_LANG_DEFAULT'));

		$use_lang_from_cookie = false;
		$this->languages = Language::getLanguages(false);
		if ($allow_employee_form_lang) {
			foreach ($this->languages AS $lang) {
				if ($cookie->employee_form_lang == $lang['id_lang']) {
					$use_lang_from_cookie = true;
				}
			}
		}
		if (!$use_lang_from_cookie) {
			$this->default_form_language = (int)(Configuration::get('PS_LANG_DEFAULT'));
		} else {
			$this->default_form_language = (int)($cookie->employee_form_lang);
		}
	}

	public function getContent() {
		global $cookie, $currentIndex;

		$this->handle_language();

		$html = new SNSolutionsHelper();
		$html->add_html('<h2>'.$this->displayName.'</h2>');

		$form_submitted = $this->handle_submit();
		if ($form_submitted) {
			// Error
			if ($this->message_type == 0) {
				$html->add_html( $this->displayError($this->message) );
			}
			// Success
			elseif ($this->message_type == 1) {
				$html->add_html( $this->displayConfirmation($this->message) );
			}
		}
		if ( ($form_submitted && $this->message_type == 0) || !$form_submitted ) {
			if ($sub_view = $this->handle_views($html)) {
				return $html->get_html();
			}
		}

		$html->fieldset_start( $this->l('Add extra fields') );

		$html->add_html('
			<p>
				<a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&add=field" title="' . $this->l('Add new field') . '"><img src="' . _PS_ADMIN_IMG_ . 'add.gif" alt="" />' . $this->l('Add new field') . '</a>
			</p>
		');

		$html->table_start('table', 'width:100%;');

		$html->thead_start();
		$html->th($this->l('Name'));
		$html->th($this->l('Label'));
		$html->th($this->l('Type'));
		$html->th($this->l('Length'));
		$html->th($this->l('Actions'), 'center', "min-width:7%;");
		$html->thead_end();

		$html->tbody_start();

		$fields = $this->get_all_fields();
		if (!empty($fields)) {
			foreach ($fields as $field) {
				$html->tr_start();
				$html->td($field['name']);
				$html->td($field['label']);
				$html->td($field['type']);
				$html->td(($field['type'] == 'TEXT' ? '-' : $field['length']));
				$html->td('<a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&edit=field&name=' . $field['name'] . '" title="' . $this->l('Edit field') . '"><img src="' . _PS_ADMIN_IMG_ . 'edit.gif" alt="" /></a><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&del=field&name=' . $field['name'] . '" title="' . $this->l('Delete') . '" onclick="return confirm(\''. $this->l('Are you sure you want to delete this field? All saved product data for this field will be permanently removed.') . '\');"><img src="' . _PS_ADMIN_IMG_ . 'delete.gif" alt="" /></a>', 'center');
				$html->tr_end();
			}
		} else {
			$html->tr_start();
			$html->td($this->l('No extra fields have been added.'), '', '', 5);
			$html->tr_end();
		}

		$html->tbody_end();

		$html->table_end();

		$html->fieldset_end();

		$html->add_html('<br />');

		$html->form_start('bulkUpdateForm', 'bulkUpdateForm', $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'));
		$html->fieldset_start( $this->l('Bulk product updates') );

		$html->select_start( $this->l('Supplier') );
		$suppliers = $this->getSuppliers();
		$ids = array();
		foreach ($suppliers as $supplier) {
			$ids[] = $supplier['id_supplier'];
		}
		$suppliers[] = array('id_supplier' => implode(',', $ids), 'name' => '&raquo; ' . $this->l('All suppliers'));
		$html->select('supplier', 'id_supplier', false, $suppliers, '', '', 'id_supplier', 'name', Tools::getValue('supplier'), array('0' => $this->l('-- Select --')), $this->l('Choose supplier whose products should be updated.'));
		$html->select_end();

		$html->select_start( $this->l('Manufacturer') );
		$manufacturers = $this->getManufacturers();
		$ids = array();
		foreach ($manufacturers as $manufacturer) {
			$ids[] = $manufacturer['id_manufacturer'];
		}
		$manufacturers[] = array('id_manufacturer' => implode(',', $ids), 'name' => '&raquo; ' . $this->l('All manufacturers'));
		$html->select('manufacturer', 'id_manufacturer', false, $manufacturers, '', '', 'id_manufacturer', 'name', Tools::getValue('manufacturer'), array('0' => $this->l('-- Select --')), $this->l('Choose manufacturer whose products should be updated.'));
		$html->select_end();

		$categories = ($this->PS_VERSION != "1.4" ? $this->getCategories($cookie->id_lang) : Category::getCategories($cookie->id_lang));
		$html->select_start($this->l('Categories'));
		$html->add_html('<select name="id_category" id="category">');
		$html->add_html('<option value="0">' . $this->l('-- Select --') . '</option>');
		$this->recurse_category($html, $categories, @$categories[0][1]);
		$html->add_html('</select>');
		$html->add_html('<p>' . $this->l('Choose category whose products should be updated.') . '</p>');
		$html->select_end();

		$html->add_html('
			<script type="text/javascript">
			$("#supplier, #manufacturer, #category").change( function() {
				$.ajax({
					url: "' . $this->_path . 'get-products.php",
					type: "GET",
					dataType: "json",
					data: {
						id_lang: 			"' . $cookie->id_lang . '",
						id_supplier: 		$("#supplier").val(),
						id_manufacturer: 	$("#manufacturer").val(),
						id_category:		$("#category").val(),
						id_employee:		' . $cookie->id_employee . ',
						passwd:				"' . $cookie->passwd . '"
					},
					beforeSend: function() {
						$("#loading-products").show();
						$("#products").attr("disabled", "disabled");
					},
					success: function(json) {
						$("#product_exceptions").find("option").remove();
						$("#products").find("option").remove();
						if (json != null) {
							if (json.error == null) {
								$.each(json, function(key, product) {
									$("#products").append(\'<option value="\' + product.id_product + \'">\' + product.name + \' [id:\' + product.id_product + \']</option>\');
								});
							} else {
								alert("' . $this->l('AJAX Error: Please refresh the page. Your cookie has expired.') . '");
							}
						}
					},
					complete: function() {
						$("#loading-products").hide();
						$("#products").removeAttr("disabled");
					},
					error: function(msg) {
						alert("' . $this->l('AJAX Error: Could not load products') . '");
					}
				});
			});
			</script>
		');

		$fields = $this->get_all_fields();
		$field_ids = array();
		foreach ($fields as $field) {
			$field_ids[] =  $field['name'];
		}
		$field_ids = implode('¤', $field_ids);
		foreach ($fields as $field) {
			$html->input_start($this->l($field['label']));
			$html->add_html('<div class="translatable">');
			foreach ($this->languages as $language) {
				$html->add_html('<div class="lang_' . $language['id_lang'] . '" style="display: '.($language['id_lang'] == $this->default_form_language ? 'block' : 'none') . ';float:left;">');
				$html->input('text', $field['name'] . '_' . $language['id_lang'], "fields[" . $field['name'] . '][' . $language['id_lang'] . ']');
				$html->add_html('</div>');
			}
			$html->add_html('</div>');
			$html->add_html('<p>' . $field['help_text'] . '</p>');
			$html->input_end();
		}

		$html->input_start($this->l('Update empty fields'));
		$html->input('radio', 'display_on', 'force_empty', '1');
		$html->add_html(' <label class="t" for="display_on"><img title="' . $this->l('Yes') . '" alt="' . $this->l('Yes') .'" src="../img/admin/enabled.gif"></label>');
		$html->input('radio', 'display_off', 'force_empty', '0', '', '', true);
		$html->add_html(' <label class="t" for="display_off"><img title="' . $this->l('Yes') . '" alt="' . $this->l('Yes') .'" src="../img/admin/disabled.gif"></label>');
		$html->add_html('<p>' . $this->l('Should fields left blank be updated with a blank value?') . '</p>');
		$html->input_end();

		$html->add_html("
			<script type=\"text/javascript\">
				$(document).ready(function() {
					id_language = ".$this->default_form_language.";
					languages = new Array();
		");
		$default_lang_index = 0;
		foreach ($this->languages as $i => $language) {
			if ($language['id_lang'] == $this->default_form_language) {
				$default_lang_index = $i;
			}
			$html->add_html("
				languages[{$i}] = {
					id_lang: {$language['id_lang']},
					iso_code: '{$language['iso_code']}',
					name: '{$language['name']}'
				};
			");
		}
		$html->add_html("
					displayFlags(languages, id_language, {$default_lang_index});
					$('.translatable').append('<div class=\"clear\"></div>');
				});
			</script>
		");

		$html->add_html('<div class="clear"></div>');

		$html->select_start( $this->l('Except these products'), 'position:relative;');
		$html->select('product_exceptions', 'product_exceptions[]', true, array(), '', 'padding:5px;width:250px;height:150px;');
		$html->add_html('&nbsp;');
		$html->select('products', 'products', true, array(), '', 'padding:5px;width:250px;height:150px;');
		$html->add_html('<br />');
		$html->add_html('<a id="removeException" style="float:left;width:244px;text-align:center;display:inline;border:1px solid #E0D0B1;text-decoration:none;background-color:#fafafa;color:#123456;margin:4px 3px 5px 0;padding:2px;cursor:pointer;">' . $this->l('Remove') . ' &raquo;</a>');
		$html->add_html('&nbsp;<a id="addException" style="float:left;width:244px;text-align:center;display:inline;border:1px solid #E0D0B1;text-decoration:none;background-color:#fafafa;color:#123456;margin:4px 0 5px 0;padding:2px;cursor:pointer;">&laquo; ' . $this->l('Add') . '</a>');
		$html->add_html('<p class="clear">' . $this->l('Select products to exclude from the bulk update.') . '</p>');
		$html->add_html('<div id="loading-products" style="display:none;position:absolute;left:625px;top:65px;"><img src="' . _PS_IMG_ . 'loader.gif" alt="" /></div>');
		$html->select_end();

		$html->add_html('
			<script type="text/javascript">
				$("#addException").click(function() {
					return !$("#products option:selected").remove().appendTo("#product_exceptions").removeAttr("selected");
				});
				$("#removeException").click(function() {
					return !$("#product_exceptions option:selected").remove().appendTo("#products").removeAttr("selected");
				});
			</script>
		');

		$html->input_start('&nbsp;');
		if (!empty($fields)) {
			$html->input('submit', 'bulkUpdate', 'bulkUpdate', $this->l('Bulk update'), 'button');
		} else {
			$html->add_html('<div style="display:block;" class="hint">'.$this->l('You have to add some fields before you can do a bulk update.').'</div>');
		}
		$html->add_html('<img id="bulkUpdateRunning" src="/img/loader.gif" alt="" style="margin-left:5px;display:none;" />');
		$html->input_end();

		$html->add_html('<div id="update_status_wrapper" style="display:none;">');
		$html->textarea_start( $this->l('Bulk update status') );
		$html->add_html('<div id="update_status" style="padding:5px;background-color:#fff;overflow-y:auto;border:1px solid #E0D0B1;width:600px;height:150px;font-family:Courier;font-size:12px;"></div>');
		$html->textarea_end();
		$html->add_html('</div>');

		$html->fieldset_end();
		$html->form_end();

		$html->add_html('
			<script type="text/javascript">
				var statusTimer;
				var borderBefore;
				var colorBefore;
				$("#bulkUpdateForm").submit( function(e) {
					if ($("#supplier").val() == 0 && $("#manufacturer").val() == 0 && $("#category").val() == 0) {
						alert("'.$this->l('You have to select at least one supplier, manufacturer or category.').'");
						return false;
					}
					$("#product_exceptions").find("option").attr("selected", "selected");
					if (confirm("' . $this->l('Are you sure you want to do this bulk update? There is no going back when it is done.') . '")) {
						$.ajax({
							url: "' . $this->_path . 'bulk-update.php",
							type: "GET",
							dataType: "json",
							data: "action=update&id_lang=' . $cookie->id_lang . '&id_employee=' . $cookie->id_employee . '&passwd=' . $cookie->passwd . '&" + $("#bulkUpdateForm").serialize(),
							beforeSend: function() {
								$("#bulkUpdateRunning").show();
								borderBefore = $("#bulkUpdate").css("border");
								colorBefore = $("#bulkUpdate").css("color");
								$("#bulkUpdateForm fieldset").attr("disabled", "disabled");
								$("#bulkUpdate").attr("disabled", "disabled").css({ "border" : "1px solid #CCCCCC", "color" : "#8C8C8C" });
								');
		if (is_writable(dirname(__FILE__).'/bulk-update.txt')) {
			$html->add_html('
				$("#update_status_wrapper").show();
				$("#update_status").text("");
				statusTimer = setInterval( function() {
					getStatus()
				}, 500);
			');
		}
		$html->add_html('
							},
							success: function(json) {

							},
							complete: function() {
								setTimeout( function() {
									$("#bulkUpdateRunning").hide();
									$("#bulkUpdateForm fieldset").removeAttr("disabled");
									$("#bulkUpdate").removeAttr("disabled").css({"border" : borderBefore, "color" : colorBefore});
									clearInterval(statusTimer);
								}, 1000);
							},
							error: function(msg) {
								alert("' . $this->l('AJAX Error: Something went wrong with the bulkupdate') . '");
							}
						});
					}
					e.preventDefault();
				});
				function getStatus() {
					$.ajax({
						url: "' . $this->_path . 'bulk-update.php",
						type: "GET",
						data: {
							action: 		"get-status",
							id_employee:	' . $cookie->id_employee . ',
							passwd:			"' . $cookie->passwd . '"
						},
						success: function(data) {
							$("#update_status").prepend(data);
						},
						error: function() {
							alert("error");
						}
					});
				}
			</script>
		');

		return $html->get_html();
	}

	public function add_field_view(&$html) {
		global $currentIndex;

		$html->add_html('<p><a href="'.$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules').'">&laquo; ' . $this->l('Back to module configuration') . '</a></p>');

		$html->add_html("
			<script type=\"text/javascript\">
			function str2field(str,encoding,ucfirst)
			{
				str = str.toUpperCase();
				str = str.toLowerCase();

				str = str.replace(/[\u0105\u0104\u00E0\u00E1\u00E2\u00E3\u00E4\u00E5]/g,'a');
				str = str.replace(/[\u00E7\u010D\u0107\u0106]/g,'c');
				str = str.replace(/[\u010F]/g,'d');
				str = str.replace(/[\u00E8\u00E9\u00EA\u00EB\u011B\u0119\u0118]/g,'e');
				str = str.replace(/[\u00EC\u00ED\u00EE\u00EF]/g,'i');
				str = str.replace(/[\u0142\u0141]/g,'l');
				str = str.replace(/[\u00F1\u0148]/g,'n');
				str = str.replace(/[\u00F2\u00F3\u00F4\u00F5\u00F6\u00F8\u00D3]/g,'o');
				str = str.replace(/[\u0159]/g,'r');
				str = str.replace(/[\u015B\u015A\u0161]/g,'s');
				str = str.replace(/[\u00DF]/g,'ss');
				str = str.replace(/[\u0165]/g,'t');
				str = str.replace(/[\u00F9\u00FA\u00FB\u00FC\u016F]/g,'u');
				str = str.replace(/[\u00FD\u00FF]/g,'y');
				str = str.replace(/[\u017C\u017A\u017B\u0179\u017E]/g,'z');
				str = str.replace(/[\u00E6]/g,'ae');
				str = str.replace(/[\u0153]/g,'oe');
				str = str.replace(/[\u013E\u013A]/g,'l');
				str = str.replace(/[\u0155]/g,'r');

				str = str.replace(/[^_a-z0-9\s\'\:\/\[\]-]/g,'');
				str = str.replace(/[\s\'\:\/\[\]-]+/g,' ');
				str = str.replace(/[ ]/g,'_');
				str = str.replace(/[\/]/g,'-');

				if (ucfirst == 1) {
					c = str.charAt(0);
					str = c.toUpperCase()+str.slice(1);
				}

				return str;
			}
			</script>
		");

		$values = Tools::getValue('form');

		$html->group_fields(true);

		$html->form_start('addFieldForm', 'addFieldForm', $_SERVER['REQUEST_URI']);
		$html->fieldset_start( $this->l('Add extra field') );

		$html->input_start($this->l('Label'));
		$html->input('text', 'label', 'label', @$values['label'], '', '', false, false, false, null, "$('#name').val(str2field(this.value, 'UTF-8'));");
		$html->input_end();

		$html->input_start($this->l('Help text'));
		$html->input('text', 'help_text', 'help_text', @$values['help_text'], '', '', false, false, false, $this->l('This text will be displayed below the field when editing a product and doing a bulk update.'));
		$html->input_end();

		$html->input_start($this->l('Name'));
		$html->input('text', 'name', 'name', @$values['name'], '', '', false, false, false, $this->l('This must be a unique field name. Only a-z, 0-9, _ and - is allowed.'), "if (!isArrowKey(event)) { this.value = str2field(this.value, 'UTF-8'); }");
		$html->input_end();

		$html->select_start($this->l('Type'));
		$html->select('type', 'type', false, array('VARCHAR' => 'VARCHAR', 'TEXT' => 'TEXT', 'SMALLINT' => 'SMALLINT', 'INT' => 'INT', 'BIGINT' => 'BIGINT'), '', '', false, false, @$values['type']);
		$html->select_end();

		$html->add_html('<div id="length_wrapper">');
		$html->input_start($this->l('Length'));
		$html->input('text', 'length', 'length', (isset($values['length']) ? $values['length'] : 255), '', '', false, false, false, $this->l('Change only if you know that the data for this field always will be less than the auto-selected length.'));
		$html->input_end();
		$html->add_html('</div>');

		$html->add_html('
			<script type="text/javascript">
			$("#type").change( function() {
				var val = $(this).val();
				if (val == "TEXT") {
					$("#length_wrapper").hide();
				} else {
					var len = "";
					if (val == "VARCHAR") {
						len = 255;
					} else if (val == "SMALLINT") {
						len = 6;
					} else if (val == "INT") {
						len = 11;
					} else if (val == "BIGINT") {
						len = 20;
					}
					$("#length").val(len);
					$("#length_wrapper").show();
				}
			});
			</script>
		');

		$html->add_html('<p style="margin-left:210px;">');
		$html->input('submit', 'addField', 'addField', $this->l('Add field'), 'button');
		$html->add_html('<a href="' . (!Tools::getValue('redirect') ?  $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') : urldecode(Tools::getValue('redirect')))  . '" class="button" style="position:relative; padding:3px 3px 4px 3px; left:10px;font-size:12px;" title="' . $this->l('Cancel') . '">' . $this->l('Cancel') . '</a>');
		$html->add_html('</p>');

		$html->form_end();
		$html->fieldset_end();

		$html->group_fields(false);
	}

	public function edit_field_view(&$html) {
		global $currentIndex;

		$html->add_html('<p><a href="'.$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules').'">&laquo; Back to module configuration</a></p>');

		$values = $this->get_field(Tools::getValue('name'));
		$html->group_fields(true);

		$html->form_start('addFieldForm', 'addFieldForm', $_SERVER['REQUEST_URI']);
		$html->fieldset_start( $this->l('Add extra field') );

		$html->input_start($this->l('Label'));
		$html->input('text', 'label', 'label', @$values['label'], '', '', false, false, false, null, "$('#name').val(str2field(this.value, 'UTF-8'));");
		$html->input_end();

		$html->input_start($this->l('Help text'));
		$html->input('text', 'help_text', 'help_text', @$values['help_text'], '', '', false, false, false, $this->l('This text will be displayed below the field when editing a product and doing a bulk update.'));
		$html->input_end();

		$html->input_start($this->l('Name'));
		$html->input('text', 'name', 'name', @$values['name'], '', '', false, true, false, $this->l('This must be a unique field name. Only a-z, 0-9, _ and - is allowed.'));
		$html->input_end();

		$html->select_start($this->l('Type'));
		$html->select('type', 'type', false, array('VARCHAR' => 'VARCHAR', 'TEXT' => 'TEXT', 'SMALLINT' => 'SMALLINT', 'INT' => 'INT', 'BIGINT' => 'BIGINT'), '', '', false, false, @$values['type'], false, null, true);
		$html->select_end();

		$html->add_html('<div id="length_wrapper">');
		$html->input_start($this->l('Length'));
		$html->input('text', 'length', 'length', @$values['length'], '', '', false, true);
		$html->input_end();
		$html->add_html('</div>');

		$html->add_html('
			<script type="text/javascript">
			$("#type").change( function() {
				if ($(this).val() == "TEXT") {
					$("#length_wrapper").hide();
				} else {
					$("#length_wrapper").show();
				}
			});
			</script>
		');

		$html->add_html('<p style="margin-left:210px;">');
		$html->input('submit', 'editField', 'editField', $this->l('Edit field'), 'button');
		$html->add_html('<a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '" class="button" style="position:relative; padding:3px 3px 4px 3px; left:10px;font-size:12px;" title="' . $this->l('Cancel') . '">' . $this->l('Cancel') . '</a>');
		$html->add_html('</p>');

		$html->add_html('<div class="hint" style="display:block;font-size:0.9em;">' . $this->l('If you want to edit something else than the label you have to delete the field and create a new one.') . '</div>');

		$html->form_end();
		$html->fieldset_end();

		$html->group_fields(false);
	}

	public function handle_views(&$html) {
		$add = Tools::getValue('add');
		$edit = Tools::getValue('edit');
		if ($add && $add == 'field') {
			$this->add_field_view($html);
		} elseif ($edit && $edit == 'field' && Tools::getValue('name')) {
			$this->edit_field_view($html);
		} else {
			return false;
		}
		return true;
	}

	public function handle_submit() {
		$data = Tools::getValue('form');
		$delete = Tools::getValue('del');
		$edit = Tools::getValue('edit');
		$name = Tools::getValue('name');
		if ($data) {
			if (isset($data['addField'])) {
				$this->add_field($data);
				if (Tools::getValue('redirect') && $this->message_type == 1) {
					header("Location: " . urldecode(Tools::getValue('redirect')));
				}
				return true;
			} elseif (isset($data['editField'])) {
				$this->edit_field($data, $name);
				return true;
			}
		} elseif ($delete) {
			if ($delete == 'field' && $name) {
				Db::getInstance()->Execute("ALTER TABLE `"._DB_PREFIX_."product_info` DROP `".pSQL($name)."`");
				Db::getInstance()->Execute("DELETE from `"._DB_PREFIX_."product_info_fields` WHERE `name` = '".pSQL($name)."'");
				$this->display_confirmation( $this->l('The field and its corresponding data has been deleted') );
			}
			return true;
		}
		return false;
	}

	public function edit_field($data, $name) {
		$sql = "UPDATE `"._DB_PREFIX_."product_info_fields` SET `label` = '".pSQL($data['label'])."', `help_text` = '".pSQL($data['help_text'])."' WHERE `name` = '" . pSQL($name) . "'";
		if (Db::getInstance()->Execute($sql)) {
			$this->display_confirmation( $this->l('Field has been updated') );
		} else {
			$this->display_error( $this->l('Field could not be updated. Error: ') . Db::getInstance()->getMsgError() );
		}
	}

	public function add_field($data) {
		$sql = "INSERT into `"._DB_PREFIX_."product_info_fields`(`label`, `name`, `help_text`, `type`, `length`)
				VALUES(
					'".pSQL($data['label'])."',
					'".pSQL($data['name'])."',
					'".pSQL($data['help_text'])."',
					'".pSQL($data['type'])."',
					".(int)$data['length']."
				)";
		if (Db::getInstance()->Execute($sql)) {
			$sql = "ALTER TABLE `"._DB_PREFIX_."product_info` ADD COLUMN `".pSQL($data['name'])."` ";
			$length = (int)$data['length'];
			switch ($data['type']) {
				case 'VARCHAR':
					$length = ($length == 0 ? 255 : $length);
					$sql .= "VARCHAR(".$length.") NULL";
				break;
				case 'TEXT':
					$length = 0;
					$sql .= "TEXT NULL";
				break;
				case 'SMALLINT':
					$length = ($length == 0 ? 6 : $length);
					$sql .= "SMALLINT(".$length.") NOT NULL";
				break;
				case 'INT':
					$length = ($length == 0 ? 11 : $length);
					$sql .= "INT(".$length.") NOT NULL";
				break;
				case 'BIGINT':
					$length = ($length == 0 ? 20 : $length);
					$sql .= "BIGINT(".$length.") NOT NULL";
				break;
			}
			if (Db::getInstance()->Execute($sql)) {
				$this->display_confirmation( $this->l('Field has been added') );
				if ($length != (int)$data['length']) {
					Db::getInstance()->Execute("UPDATE `"._DB_PREFIX_."product_info_fields` SET `length` = {$length} WHERE `name` = '".pSQL($data['name'])."'");
				}
			} else {
				$this->display_error( $this->l('Field could not be added. Error: ' . Db::getInstance()->getMsgError()) );
				Db::getInstance()->Execute("DELETE from `"._DB_PREFIX_."product_info_fields` WHERE `name` = '".pSQL($data['name'])."'");
			}
		} else {
			$this->display_error( $this->l('Field could not be added. Maybe you already have a field with this name? Error: ' . Db::getInstance()->getMsgError()) );
		}
	}

	public function display_confirmation($message) {
		$this->message_type = 1;
		$this->message = $message;
	}

	public function display_error($message) {
		$this->message_type = 0;
		$this->message = $message;
	}

	public function get_field($name) {
		$sql = "SELECT * from `"._DB_PREFIX_."product_info_fields`
				WHERE `name` = '" . pSQL($name) . "'";
		$result = Db::getInstance()->ExecuteS($sql);
		return ($result ? $result[0] : array());
	}

	public static function get_all_fields() {
		$sql = "SELECT * from `"._DB_PREFIX_."product_info_fields` ORDER by `name` asc";
		$result = Db::getInstance()->ExecuteS($sql);
		return ($result ? $result : array());
	}

	public function getSuppliers() {
		if ($this->PS_VERSION != "1.4") {
			$sql = "SELECT s.`id_supplier`, s.`name`
				FROM `"._DB_PREFIX_."supplier` s
				INNER JOIN `"._DB_PREFIX_."supplier_shop` ss
					ON s.`id_supplier` = ss.`id_supplier`
				WHERE ss.`id_shop` = " . (int)$this->context->shop->id;
		} else {
			$sql = "SELECT s.`id_supplier`, s.`name`
				FROM `"._DB_PREFIX_."supplier` s";
		}
		return Db::getInstance()->ExecuteS($sql);
	}

	public function getManufacturers() {
		if ($this->PS_VERSION != "1.4") {
			$sql = "SELECT m.`id_manufacturer`, m.`name`
				FROM `"._DB_PREFIX_."manufacturer` m
				INNER JOIN `"._DB_PREFIX_."manufacturer_shop` ms
					ON m.`id_manufacturer` = ms.`id_manufacturer`
				WHERE ms.`id_shop` = " . (int)$this->context->shop->id;
		} else {
			$sql = "SELECT m.`id_manufacturer`, m.`name`
				FROM `"._DB_PREFIX_."manufacturer` m";
		}
		return Db::getInstance()->ExecuteS($sql);
	}

	// Only 1.5
	public function getCategories($id_lang = false, $active = true, $order = true, $sql_filter = '', $sql_sort = '', $sql_limit = '')
	{
	 	if (!Validate::isBool($active))
	 		die(Tools::displayError());
		$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT *
			FROM `'._DB_PREFIX_.'category` c
			'.Shop::addSqlAssociation('category', 'c').'
			LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON c.`id_category` = cl.`id_category`'.Shop::addSqlRestrictionOnLang('cl').'
			WHERE category_shop.`id_shop` = '.$this->context->shop->id." ".$sql_filter.' '.($id_lang ? 'AND `id_lang` = '.(int)$id_lang : '').'
			'.($active ? 'AND `active` = 1' : '').'
			'.(!$id_lang ? 'GROUP BY c.id_category' : '').'
			'.($sql_sort != '' ? $sql_sort : 'ORDER BY c.`level_depth` ASC, category_shop.`position` ASC').'
			'.($sql_limit != '' ? $sql_limit : '')
		);

		if (!$order)
			return $result;

		$categories = array();
		foreach ($result as $row)
			$categories[$row['id_parent']][$row['id_category']]['infos'] = $row;

		return $categories;
	}

	public function recurse_category(&$html, $categories, $current, $id_category = 1, $id_selected = 1) {
		$html->add_html('<option value="'.$id_category.'"'.(($id_selected == $id_category && $id_category != 1) ? ' selected="selected"' : '').'>'.
		str_repeat('&nbsp;', $current['infos']['level_depth'] * 5).stripslashes($current['infos']['name']).'</option>');
		if (isset($categories[$id_category]))
			foreach (array_keys($categories[$id_category]) AS $key)
				$this->recurse_category($html, $categories, $categories[$id_category][$key], $key, $id_selected);
	}

}
