{+START,IF_EMPTY,{$CONFIG_OPTION,recaptcha_site_key}}
	{+START,SET,CAPTCHA}
		{+START,IF_PASSED_AND_TRUE,USE_CAPTCHA}
			<div class="comments_captcha">
				<div class="box box___comments_posting_form__captcha"><div class="box_inner">
					{+START,IF,{$CONFIG_OPTION,audio_captcha}}
						<p>{+START,IF,{$NOT,{$CONFIG_OPTION,js_captcha}}}<label for="captcha">{+END}{!DESCRIPTION_CAPTCHA_2,<a class="js-click-play-self-audio-link" title="{!captcha:AUDIO_VERSION}" href="{$FIND_SCRIPT*,captcha,1}?mode=audio&amp;cache_break={$RAND}{$KEEP*,0,1}">{!captcha:AUDIO_VERSION}</a>}{+START,IF,{$NOT,{$CONFIG_OPTION,js_captcha}}}</label>{+END}</p>
					{+END}
					{+START,IF,{$NOT,{$CONFIG_OPTION,audio_captcha}}}
						<p>{+START,IF,{$NOT,{$CONFIG_OPTION,js_captcha}}}<label for="captcha">{+END}{!DESCRIPTION_CAPTCHA_3}{+START,IF,{$NOT,{$CONFIG_OPTION,js_captcha}}}</label>{+END}</p>
					{+END}
					{+START,IF,{$CONFIG_OPTION,css_captcha}}
						<iframe {$?,{$BROWSER_MATCHES,ie}, frameBorder="0" scrolling="no"} id="captcha_frame" class="captcha_frame" title="{!CONTACT_STAFF_TO_JOIN_IF_IMPAIRED}" src="{$FIND_SCRIPT*,captcha}&amp;cache_break={$RAND}{$KEEP*,0,1}">{!CONTACT_STAFF_TO_JOIN_IF_IMPAIRED}</iframe>
					{+END}
					{+START,IF,{$NOT,{$CONFIG_OPTION,css_captcha}}}
						<img id="captcha_image" title="{!CONTACT_STAFF_TO_JOIN_IF_IMPAIRED}" alt="{!CONTACT_STAFF_TO_JOIN_IF_IMPAIRED}" src="{$FIND_SCRIPT*,captcha}&amp;cache_break={$RAND}{$KEEP*,0,1}" />
					{+END}
					<input maxlength="6" size="8" class="input_text_required" type="text" id="captcha" name="captcha" />
				</div></div>
			</div>
		{+END}
	{+END}

	{+START,IF,{$CONFIG_OPTION,js_captcha}}
		{+START,IF_NON_EMPTY,{$TRIM,{$GET,CAPTCHA}}}
			<div id="captcha_spot"></div>
		{+END}
	{+END}
	{+START,IF,{$NOT,{$CONFIG_OPTION,js_captcha}}}
		{$GET,CAPTCHA}
	{+END}
{+END}

{+START,IF_NON_EMPTY,{$CONFIG_OPTION,recaptcha_site_key}}
	<div id="captcha"{+START,IF_PASSED,TABINDEX} data-tabindex="{TABINDEX*}"{+END}></div>
{+END}