<section class="box box---simple-preview-box"><div class="box-inner">
	{+START,IF_NON_EMPTY,{TITLE}}
		<h3>
			{TITLE`}
		</h3>
	{+END}

	{+START,IF_NON_EMPTY,{HTML}}
		{HTML`}
	{+END}

	<p class="shunted-button">
		<a class="button-screen-item buttons--more" href="{URL*}"{+START,IF_PASSED,REL} rel="{REL*}"{+END}><span>{!VIEW}</span> {+START,INCLUDE,ICON}NAME=buttons/more{+END}</a>
	</p>
</div></section>
