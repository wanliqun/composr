{+START,IF_PASSED,CHILDREN}{+START,IF_PASSED,OTHER_IDS}{+START,IF,{$NEQ,{OTHER_IDS},}}
	<p class="post_show_more">
		<a onclick="return threaded_load_more(this.parentNode,'{+START,IMPLODE,\,,OTHER_IDS}{+END}','{ID;*}');" href="{$SELF_URL*,0,0,0,max_comments=200}">{+START,IF_NON_EMPTY,{CHILDREN}}{!SHOW_MORE_COMMENTS,{$NUMBER_FORMAT,{$MIN,{NUM_TO_SHOW_LIMIT},{OTHER_IDS}}}}{+END}{+START,IF_EMPTY,{CHILDREN}}{!SHOW_COMMENTS,{$NUMBER_FORMAT,{$MIN,{NUM_TO_SHOW_LIMIT},{OTHER_IDS}}}}{+END}</a>
	</p>
{+END}{+END}{+END}
