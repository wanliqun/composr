{$REQUIRE_JAVASCRIPT,shopping}

<div data-tpl="ecomShoppingCartScreen" data-tpl-params="{+START,PARAMS_JSON,TYPE_CODES,EMPTY_CART_URL}{_*}{+END}">
	{TITLE}

	<form title="{!PRIMARY_PAGE_FORM}" action="{UPDATE_CART_URL*}" method="post" itemscope="itemscope" itemtype="http://schema.org/CheckoutPage" autocomplete="off">
		{$INSERT_SPAMMER_BLACKHOLE}

		{RESULTS_TABLE}

		<div class="cart-buttons">
			<div class="buttons-group cart-update-buttons" itemprop="significantLinks">
				{$,Put first, so it associates with the enter key}
				{+START,IF_NON_EMPTY,{TYPE_CODES}}
					<input id="cart_update_button" class="buttons--cart-update button-screen button-faded js-click-btn-cart-update" type="submit" name="update" title="{!UPDATE_CART}" value="{!_UPDATE_CART}" />
				{+END}

				{+START,IF_NON_EMPTY,{EMPTY_CART_URL*}}
					<input class="button-screen-item buttons--cart-empty js-click-btn-cart-empty" type="submit" value="{!EMPTY_CART}" />
				{+END}
			</div>

			<div class="buttons-group cart-continue-button" itemprop="significantLinks">
				<input type="hidden" name="type_codes" id="type_codes" value="{TYPE_CODES*}" />

				{+START,IF_NON_EMPTY,{CONTINUE_SHOPPING_URL}}
					<a class="button-screen-item menu--rich-content--catalogues--products" href="{CONTINUE_SHOPPING_URL*}"><span>{!CONTINUE_SHOPPING}</span></a>
				{+END}
			</div>
		</div>
	</form>

	{+START,IF_NON_EMPTY,{TYPE_CODES}}
		<div class="cart-payment-line">
			{!SHIPPING}:
			<span class="tax">{$CURRENCY,{$ADD,{TOTAL_SHIPPING_COST},{TOTAL_SHIPPING_TAX}},{CURRENCY},{$?,{$CONFIG_OPTION,currency_auto},{$CURRENCY_USER},{$CURRENCY}}}</span>
		</div>

		<div class="cart-payment-summary">
			{!GRAND_TOTAL}:
			<span class="price">{$CURRENCY,{GRAND_TOTAL},{CURRENCY},{$?,{$CONFIG_OPTION,currency_auto},{$CURRENCY_USER},{$CURRENCY}}}</span>
		</div>
	{+END}

	<form title="{!PRIMARY_PAGE_FORM}" method="post" enctype="multipart/form-data" action="{NEXT_URL*}" autocomplete="off">
		{$INSERT_SPAMMER_BLACKHOLE}

		{+START,IF_PASSED,FIELDS}
			<div class="wide-table-wrap"><table class="map-table form-table wide-table">
				{+START,IF,{$NOT,{$MOBILE}}}
					<colgroup>
						<col class="purchase-field-name-column" />
						<col class="purchase-field-input-column" />
					</colgroup>
				{+END}

				<tbody>
					{FIELDS}
				</tbody>
			</table></div>
		{+END}

		<p class="purchase-button">
			<input id="proceed-button" class="button-screen buttons--proceed js-click-do-cart-form-submit" accesskey="u" type="button" value="{!CHECKOUT}" />
		</p>
	</form>
</div>
