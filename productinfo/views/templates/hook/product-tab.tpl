<h4>{l s='Extra product information' mod='productinfo'}</h4>
<div class="separation"></div>

<table>
	{foreach from=$fields item=field name=fields}
	<tr>
		<td class="col-left">{$field.label}:</td>
		<td style="padding-bottom:5px;">
			<div class="translatable">
				{foreach from=$languages item=language}
				<div class="lang_{$language.id_lang}" style="display:{if $language.id_lang == $defaultFormLanguage}block{else}none{/if};float:left;">
					<input type="text" id="{$field.name}_{$language.id_lang}" name="extra[{$field.name}][{$language.id_lang}]" value="{if isset($product->extra[$field.name][$language.id_lang])}{$product->extra[$field.name][$language.id_lang]}{/if}" />
				</div>
				{/foreach}
			</div>
			<p class="clear">{$field.help_text}</p>
		</td>
	</tr>
	{/foreach}
	{if empty($fields)}
	<tr>
		<td class="col-left" colspan="2" style="padding-bottom:5px;">
			{l s='No extra fields have been created.' mod='productinfo'}
		</td>
	</tr>
	{/if}
	<tr>
		<td class="col-left" style="padding-bottom: 5px;" colspan="2">
			<a href="index.php?controller=AdminModules&amp;configure=productinfo&amp;token={Tools::getAdminTokenLite('AdminModules')}&amp;add=field&amp;
			redirect={$backURL}" title="{l s='Add new field' mod='productinfo'}"><img src="{$img_admin}add.gif" alt="" /> {l s='Add new field' mod='productinfo'}</a>
		</td>
	</tr>
	<tr>
		<td class="col-left" style="padding-bottom:5px;padding-right:10px;" colspan="2">
			<div class="hint clear" style="display:block;">
				<a href="index.php?controller=AdminModules&amp;configure=productinfo&amp;token={Tools::getAdminTokenLite('AdminModules')}#bulkUpdateForm" title="{l s='Bulk update' mod='productinfo'}">{l s='You can do a bulk update on these extra settings. Click here.' mod='productinfo'}</a>
			</div>
		</td>
	</tr>
</table>