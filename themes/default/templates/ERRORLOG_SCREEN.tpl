{TITLE}

<h2>{!ERRORS_IN_ERRORLOG}</h2>

{ERRORS}

<ul class="actions-list">
	<li>{+START,INCLUDE,ICON}NAME=buttons/proceed2{+END} <a href="{CLEAR_URL*}">{!CLEAR}</a></li>
</ul>

{+START,LOOP,LOGS}
	<h2>{_loop_key*}</h2>

	{+START,IF_PASSED,LOG}
		{+START,IF_NON_EMPTY,{LOG}}
			<div class="raw-log">{LOG*}</div>
		{+END}
		{+START,IF_EMPTY,{LOG}}
			<p class="nothing-here">{!NO_ENTRIES}</p>
		{+END}
	{+END}

	<ul class="actions-list">
		{+START,IF_NON_EMPTY,{DOWNLOAD_URL}}
			<li>{+START,INCLUDE,ICON}NAME=buttons/proceed2{+END} <a href="{DOWNLOAD_URL*}">{!DOWNLOAD_LOG}</a></li>
		{+END}
		{+START,IF_NON_EMPTY,{DOWNLOAD_URL}}
			<li>{+START,INCLUDE,ICON}NAME=buttons/proceed2{+END} <a href="{CLEAR_URL*}">{!CLEAR_LOG}</a></li>
		{+END}
		{+START,IF_NON_EMPTY,{ADD_URL}}
			<li>{+START,INCLUDE,ICON}NAME=buttons/proceed2{+END} <a href="{ADD_URL*}">{!INIT_LOG}</a></li>
		{+END}
		{+START,IF_NON_EMPTY,{DELETE_URL}}
			<li>{+START,INCLUDE,ICON}NAME=buttons/proceed2{+END} <a href="{DELETE_URL*}">{!DELETE_LOG}</a></li>
		{+END}
	</ul>

	{+START,IF_NON_EMPTY,{ADDITIONAL}}
		<h3>{!ADDITIONAL_INFO}</h3>

		{ADDITIONAL}
	{+END}
{+END}
