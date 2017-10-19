<div data-view="ZoneEditorPanel" data-view-params="{+START,PARAMS_JSON,COMCODE,CLASS,ID,CURRENT_ZONE}{_*}{+END}">
	<div class="block_mobile">
		 <h2>{ID*}</h2>
	</div>

	{$,Tab buttons}
	<div class="float_surrounder">
		<div class="ze_tabs tabs" role="tablist">
			{+START,IF_PASSED,PREVIEW}
				<a aria-controls="view_{ID*}" role="tab" title="{!PREVIEW}: {ID*}" href="#!" id="view_tab_{ID*}" class="tab tab_first tab_selected js-click-select-tab" data-js-tab="view"><img alt="" src="{$IMG*,icons/24x24/tabs/preview}" srcset="{$IMG*,icons/48x48/tabs/preview} 2x" /> <span>{!PREVIEW}</span></a>
			{+END}
			{+START,IF_PASSED,COMCODE}
				<a aria-controls="edit_{ID*}" role="tab" title="{!EDIT}: {ID*}" href="#!" id="edit_tab_{ID*}" class="tab{+START,IF_NON_PASSED,PREVIEW} tab_first{+END} js-click-select-tab" data-js-tab="edit"><img alt="" src="{$IMG*,icons/24x24/tabs/edit}" srcset="{$IMG*,icons/48x48/tabs/edit} 2x" /> <span>{!EDIT}</span></a>
			{+END}
			<a aria-controls="info_{ID*}" role="tab" title="{!DETAILS}: {ID*}" href="#!" id="info_tab_{ID*}" class="tab{+START,IF_NON_PASSED,SETTINGS} tab_last{+END}{+START,IF_NON_PASSED,PREVIEW}{+START,IF_NON_PASSED,COMCODE} tab_first{+END}{+END} js-click-select-tab" data-js-tab="info"><img alt="" src="{$IMG*,icons/24x24/menu/_generic_spare/page}" srcset="{$IMG*,icons/48x48/menu/_generic_spare/page} 2x" /> <span>{!DETAILS}</span></a>
			{+START,IF_PASSED,SETTINGS}
				<a aria-controls="settings_{ID*}" role="tab" title="{!SETTINGS}: {ID*}" href="#!" id="settings_tab_{ID*}" class="tab tab_last js-click-select-tab" data-js-tab="settings"><img alt="" src="{$IMG*,icons/24x24/tabs/settings}" srcset="{$IMG*,icons/48x48/tabs/settings} 2x" /> <span>{!SETTINGS}</span></a>
			{+END}
		</div>
	</div>

	{$,Actual tab' contents follows}

	{+START,IF_PASSED,PREVIEW}
		<div id="view_{ID*}" style="display: block" aria-labeledby="view_tab_{ID*}" role="tabpanel">
			{+START,IF_EMPTY,{PREVIEW}}
				<p class="nothing_here">{!NONE}</p>
			{+END}
			{+START,IF_NON_EMPTY,{PREVIEW}}
				{$PARAGRAPH,{PREVIEW}}
			{+END}
		</div>
	{+END}

	{+START,IF_PASSED,COMCODE}
		<div id="edit_{ID*}" style="{+START,IF_NON_PASSED,PREVIEW}display: block{+END}{+START,IF_PASSED,PREVIEW}display: none{+END}" aria-labeledby="edit_tab_{ID*}" role="tabpanel">
			<form title="{ID*}: {!COMCODE}" action="index.php" method="post" autocomplete="off" class="js-form-zone-editor-comcode">
				{$INSERT_SPAMMER_BLACKHOLE}

				<p>
					<label for="edit_{ID*}_textarea">{!COMCODE}:</label> <a data-open-as-overlay="{}" class="link_exempt" title="{!COMCODE_MESSAGE,Comcode} {!LINK_NEW_WINDOW}" target="_blank" href="{$PAGE_LINK*,_SEARCH:userguide_comcode}"><img alt="{!COMCODE_MESSAGE,Comcode}" src="{$IMG*,icons/16x16/editor/comcode}" srcset="{$IMG*,icons/32x32/editor/comcode} 2x" class="vertical_alignment" /></a>
					{+START,IF,{$IN_STR,{CLASS},wysiwyg}}
						<span class="horiz_field_sep associated_link"><a id="toggle_wysiwyg_edit_{ID*}_textarea" href="#!" class="js-a-toggle-wysiwyg"><abbr title="{!TOGGLE_WYSIWYG_2}"><img src="{$IMG*,icons/16x16/editor/wysiwyg_on}" srcset="{$IMG*,icons/32x32/editor/wysiwyg_on} 2x" alt="{!comcode:ENABLE_WYSIWYG}" title="{!comcode:ENABLE_WYSIWYG}" class="vertical_alignment" /></abbr></a></span>
					{+END}
				</p>
				{+START,IF_NON_EMPTY,{COMCODE_EDITOR}}
					<div>
						<div class="post_special_options">
							<div class="float_surrounder" role="toolbar">
								{COMCODE_EDITOR}
							</div>
						</div>
					</div>
				{+END}
				<div>
					<textarea rows="50" cols="20" class="{$?,{IS_PANEL},ze_textarea,ze_textarea_middle} {CLASS*} js-ta-ze-comcode textarea_scroll" id="edit_{ID*}_textarea" name="{ID*}">{COMCODE*}</textarea>

					{+START,IF_PASSED,DEFAULT_PARSED}
						<textarea cols="1" rows="1" style="display: none" readonly="readonly" disabled="disabled" name="edit_{ID*}_textarea_parsed">{DEFAULT_PARSED*}</textarea>
					{+END}
				</div>
			</form>
		</div>
	{+END}

	<div id="info_{ID*}" style="{+START,IF_NON_PASSED,PREVIEW}display: block{+END}{+START,IF_PASSED,PREVIEW}display: none{+END}" aria-labeledby="info_tab_{ID*}" role="tabpanel">
		<p class="lonely_label">
			<span class="field_name">{!PAGE_TYPE}:</span>
		</p>
		<p>{TYPE*}</p>

		<p class="lonely_label">
			<span class="field_name">{!NAME}:</span>
		</p>
		<p><kbd>{ID*}</kbd></p>

		{+START,IF_NON_EMPTY,{EDIT_URL}}
			<p class="lonely_label">
				<span class="field_name">{!ACTIONS}:</span>
			</p>
			<ul class="actions_list">
				<li><a title="{!EDIT_IN_FULL_PAGE_EDITOR}: {ID*} {!LINK_NEW_WINDOW}" target="_blank" href="{EDIT_URL*}">{!EDIT_IN_FULL_PAGE_EDITOR}</a></li>
			</ul>
		{+END}

		{$,Choosing where to redirect to, same page name but in a different zone}
		{+START,IF_PASSED,ZONES}
			{+START,IF,{$ADDON_INSTALLED,redirects_editor}}
				<form title="{ID*}: {!DRAWS_FROM}" action="index.php" method="post" autocomplete="off">
					{$INSERT_SPAMMER_BLACKHOLE}

					<p class="lonely_label">
						<label for="redirect_{ID*}" class="field_name">{!DRAWS_FROM}:</label>
					</p>
					{+START,IF_NON_EMPTY,{ZONES}}
						<select class="js-sel-zones-draw" id="redirect_{ID*}" name="redirect_{ID*}">
							<option value="{ZONE*}">{!NA}</option>
							{ZONES}
						</select>
					{+END}
					{+START,IF_EMPTY,{ZONES}}
						<input maxlength="80" class="js-inp-zones-draw" size="20" id="redirect_{ID*}" name="redirect_{ID*}" value="{CURRENT_ZONE*}" type="text" />
					{+END}
				</form>
			{+END}
		{+END}
	</div>

	{+START,IF_PASSED,SETTINGS}
		<div id="settings_{ID*}" style="display: none" aria-labeledby="settings_tab_{ID*}" role="tabpanel">
			<form title="{ID*}: {!SETTINGS}" id="middle_fields" action="index.php" autocomplete="off">
				{$INSERT_SPAMMER_BLACKHOLE}

				<div class="wide_table_wrap"><table class="map_table form_table wide_table">
					{+START,IF,{$DESKTOP}}
						<colgroup>
							<col class="field_name_column" />
							<col class="field_input_column" />
						</colgroup>
					{+END}

					<tbody>
						{SETTINGS}
					</tbody>
				</table></div>
			</form>
		</div>
	{+END}
</div>
