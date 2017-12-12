{$REQUIRE_JAVASCRIPT,core_form_interfaces}
{$REQUIRE_JAVASCRIPT,cns_forum}
<div data-view="CnsForumTopicWrapper">
	<div class="wide_table_wrap"><table class="columned_table wide_table cns-topic-list">
		{+START,IF,{$DESKTOP}}
			<colgroup>
				{+START,IF,{$CONFIG_OPTION,is_on_topic_emoticons}}
					<col class="cns-forum-topic-wrapper-column-column1 column_desktop" />
				{+END}
				<col class="cns-forum-topic-wrapper-column-column2" />
				<col class="cns-forum-topic-wrapper-column-column3 column_desktop" />
				<col class="cns-forum-topic-wrapper-column-column4 column_desktop" />
				<col class="cns-forum-topic-wrapper-column-column5 column_desktop" />
				{+START,IF,{$OR,{$EQ,{$LANG},EN},{$LT,{$LENGTH,{!COUNT_POSTS}{!COUNT_VIEWS}},12}}}
					<col class="cns-forum-topic-wrapper-column-column6{$?,{$MATCH_KEY_MATCH,_WILD:members},_shorter}" />
				{+END}
				{+START,IF_NON_EMPTY,{MODERATOR_ACTIONS}}{+START,IF,{$NOT,{$_GET,overlay}}}
					<col class="cns-forum-topic-wrapper-column-column7 column_desktop" />
				{+END}{+END}
			</colgroup>
		{+END}

		<thead>
			<tr>
				{+START,IF,{$DESKTOP}}
					{+START,IF,{$CONFIG_OPTION,is_on_topic_emoticons}}
						<th class="cns-forum-box-left column_desktop"></th>
					{+END}
				{+END}
				<th>{!TITLE}</th>
				{+START,IF,{$DESKTOP}}
					<th class="column_desktop">{!STARTER}{STARTER_TITLE*}</th>
					<th class="column_desktop">{!COUNT_POSTS}</th>
					{+START,IF,{$OR,{$EQ,{$LANG},EN},{$LT,{$LENGTH,{!COUNT_POSTS}{!COUNT_VIEWS}},12}}}
						<th class="cell_desktop">{!COUNT_VIEWS}</th>
					{+END}
				{+END}
				<th {+START,IF_EMPTY,{MODERATOR_ACTIONS}} class="cns-forum-box-right"{+END}>{!LAST_POST}</th>
				{+START,IF,{$DESKTOP}}
					{+START,IF_NON_EMPTY,{MODERATOR_ACTIONS}}{+START,IF_NON_EMPTY,{MODERATOR_ACTIONS}}{+START,IF,{$NOT,{$_GET,overlay}}}
						<th class="cns-forum-box-right">
							<a href="#!" class="js-click-mark-all-topics"><img src="{$IMG*,icons/14x14/cns_topic_modifiers/unvalidated}" srcset="{$IMG*,icons/28x28/cns_topic_modifiers/unvalidated} 2x" alt="{!TOGGLE_SELECTION}" title="{!TOGGLE_SELECTION}" /></a>
						</th>
					{+END}{+END}{+END}
				{+END}
			</tr>
		</thead>

		<tbody>
			{TOPICS}

			<tr class="cns-table-footer">
				{+START,IF,{$DESKTOP}}
					{+START,IF,{$CONFIG_OPTION,is_on_topic_emoticons}}
						<td class="cns-column1 cns-forum-box-bleft cell_desktop"></td>
					{+END}
				{+END}
				<td class="cns-column1{+START,IF,{$MOBILE}} cns_forum_box_bleft{+END}"></td>
				<td class="cns-column1"></td>
				{+START,IF,{$DESKTOP}}
					<td class="cns-column1 cell_desktop"></td>
					{+START,IF,{$OR,{$EQ,{$LANG},EN},{$LT,{$LENGTH,{!COUNT_POSTS}{!COUNT_VIEWS}},12}}}
						<td class="cns-column1 cell_desktop"></td>
					{+END}
				{+END}
				<td class="cns-column1{+START,IF,{$OR,{$MOBILE},{$IS_EMPTY,{MODERATOR_ACTIONS}}}} cns-forum-box-bright{+END}"></td>
				{+START,IF,{$DESKTOP}}
					{+START,IF_NON_EMPTY,{MODERATOR_ACTIONS}}{+START,IF,{$NOT,{$_GET,overlay}}}
						<td class="cns-column1 cns-forum-box-bright cell_desktop"></td>
					{+END}{+END}
				{+END}
			</tr>
		</tbody>
	</table></div>

{+START,IF_NON_EMPTY,{PAGINATION}}
	<div class="float-surrounder pagination_spacing ajax_block_wrapper_links">
		{PAGINATION}
	</div>
{+END}

{+START,IF,{$NOT,{$WIDE_HIGH}}}
	{+START,IF_NON_EMPTY,{MODERATOR_ACTIONS}}
		{+START,IF,{$DESKTOP}}
			<div class="box cns-topic-actions block_desktop"><div class="box-inner">
				<span class="field_name">
					<label for="fma_type">{!TOPIC_ACTIONS}: </label>
				</span>
				<form title="{!TOPIC_ACTIONS}" action="{$URL_FOR_GET_FORM*,{ACTION_URL}}" method="get" class="inline" autocomplete="off">
					{$HIDDENS_FOR_GET_FORM,{ACTION_URL}}

					<div class="inline">
						<select class="dropdown-actions" name="type" id="fma_type">
							<option value="browse">-</option>
							{MODERATOR_ACTIONS}
						</select><input class="button_micro buttons--proceed js-click-btn-add-form-marked-posts" type="submit" value="{!PROCEED}" />
					</div>
				</form>

				{+START,IF,{MAY_CHANGE_MAX}}
					<form title="{!PER_PAGE}" class="inline" action="{$URL_FOR_GET_FORM*,{$SELF_URL,0,1}}{+START,IF,{$EQ,{TYPE},pt}}#tab__pts{+END}" method="get" autocomplete="off">
						{$HIDDENS_FOR_GET_FORM,{$SELF_URL,0,1},forum_max}

						<div class="inline">
							<label for="forum_max">{!PER_PAGE}:</label>
							<select name="forum_max" id="forum_max" class="js-select-change-submit-form">
								<option value="10"{$?,{$EQ,{MAX},10}, selected="selected",}>10</option>
								<option value="20"{$?,{$EQ,{MAX},20}, selected="selected",}>20</option>
								<option value="30"{$?,{$EQ,{MAX},30}, selected="selected",}>30</option>
								<option value="50"{$?,{$EQ,{MAX},50}, selected="selected",}>50</option>
								<option value="100"{$?,{$EQ,{MAX},100}, selected="selected",}>100</option>
								<option value="300"{$?,{$EQ,{MAX},300}, selected="selected",}>300</option>
							</select><input class="button_micro buttons--proceed js-click-btn-add-form-marked-posts" type="submit" value="{!PROCEED}" />
						</div>
					</form>

					<form title="{!PER_PAGE}" class="inline" action="{$URL_FOR_GET_FORM*,{$SELF_URL,0,1}}{+START,IF,{$EQ,{TYPE},pt}}#tab__pts{+END}" method="get" autocomplete="off">
						{$HIDDENS_FOR_GET_FORM,{$SELF_URL,0,1},max}

						<div class="inline">
							<label for="order">{!SORT}:</label>
							<select class="js-select-change-submit-form" name="order" id="order">
								<option value="last_post"{$?,{$EQ,{ORDER},last_post}, selected="selected",}>{!FORUM_ORDER_BY_LAST_POST}</option>
								<option value="first_post"{$?,{$EQ,{ORDER},first_post}, selected="selected",}>{!FORUM_ORDER_BY_FIRST_POST}</option>
								<option value="title"{$?,{$EQ,{ORDER},title}, selected="selected",}>{!FORUM_ORDER_BY_TITLE}</option>
							</select>
						</div>
					</form>
				{+END}
			</div></div>
		{+END}
	{+END}
{+END}
</div>
