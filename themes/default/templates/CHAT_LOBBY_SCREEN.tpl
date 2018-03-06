{$REQUIRE_JAVASCRIPT,chat}

<div data-tpl="chatLobbyScreen" data-tpl-params="{+START,PARAMS_JSON,IM_AREA_TEMPLATE,IM_PARTICIPANT_TEMPLATE}{_*}{+END}">
	{TITLE}

	{+START,IF,{$HAS_FORUM,1}}
		{MESSAGE}
	{+END}

	<p>{!USE_CHAT_RULES,{$PAGE_LINK*,:rules},{$PAGE_LINK*,:privacy}}</p>

	<div class="box box---chat-lobby-screen-rooms box-prominent"><div class="box-inner">
		<h2>{!CHATROOMS_LOBBY_TITLE}</h2>

		<div class="float-surrounder">
			{+START,IF_NON_EMPTY,{ADD_CHATROOM_URL}{PRIVATE_CHATROOM}{BLOCKING_LINK}{MOD_LINK}{SETEFFECTS_LINK}}
				<nav class="chat-actions">
					<h3>{!OTHER_ACTIONS}</h3>

					<nav>
						<ul class="actions-list">
							{+START,IF_NON_EMPTY,{ADD_CHATROOM_URL}}
								<li class="icon-14-add"><a href="{ADD_CHATROOM_URL*}" rel="add">{!ADD_CHATROOM}</a></li>
							{+END}
							{+START,IF_NON_EMPTY,{PRIVATE_CHATROOM}}
								<li class="icon-14-proceed">{PRIVATE_CHATROOM}</li>
							{+END}
							{+START,IF_NON_EMPTY,{BLOCKING_LINK}}
								<li class="icon-14-ban">{BLOCKING_LINK}</li>
							{+END}
							{+START,IF_NON_EMPTY,{MOD_LINK}}
								<li class="icon-14-tools">{MOD_LINK}</li>
							{+END}
							{+START,IF_NON_EMPTY,{SETEFFECTS_LINK}}
								<li class="icon-14-sound-effects">{SETEFFECTS_LINK}</li>
							{+END}
						</ul>
					</nav>
				</nav>
			{+END}

			<div class="chat-rooms">
				<h3>{!SELECT_CHATROOM}</h3>

				{+START,IF_NON_EMPTY,{CHATROOMS}}
					<ul class="actions-list">
						{+START,LOOP,CHATROOMS}
							<li><a href="{URL*}">{NAME*}</a> <em class="associated-details">({$?,{PRIVATE},{!CHATROOM_STATUS_PRIVATE},{!CHATROOM_STATUS_PUBLIC}})</em><span class="associated-details">({!STATIC_USERS_ONLINE,{$TIME*},{USERNAMES}})</span></li>
						{+END}
					</ul>

					<p class="chat-multi-tab">{!OPEN_CHATROOMS_IN_TABS}</p>
				{+END}
				{+START,IF_EMPTY,{CHATROOMS}}
					<p class="nothing-here">{!NO_CATEGORIES,chat}</p>
				{+END}
			</div>
		</div>
	</div></div>

	{+START,IF,{$NOT,{$IS_GUEST}}}
		<div class="chat-im-convos-wrap">
			<div class="box box---chat-lobby-screen-im box-prominent"><div class="box-inner">
				<h2>{!INSTANT_MESSAGING}</h2>

				<div class="float-surrounder chat-im-convos-inner">
					<div class="chat-lobby-convos">
						<div class="chat-lobby-convos-tabs" id="chat-lobby-convos-tabs" style="display: none"></div>
						<div class="chat-lobby-convos-areas" id="chat-lobby-convos-areas">
							<p class="nothing-here">
								{!NO_IM_CONVERSATIONS}
							</p>
						</div>
					</div>

					<div class="chat-lobby-friends">
						<h3>{!FRIEND_LIST}</h3>

						{+START,IF_NON_EMPTY,{FRIENDS}}
							<form title="{!FRIEND_LIST}" method="post" action="{$?,{$IS_EMPTY,{URL_REMOVE_FRIENDS}},index.php,{URL_REMOVE_FRIENDS*}}" autocomplete="off">
								{$INSERT_SPAMMER_BLACKHOLE}

								<div id="friends-wrap">
									{FRIENDS}
								</div>

								<div class="friend-actions">
									{+START,IF,{CAN_IM}}
										<input class="button-screen-item admin--add-to-category js-click-btn-im-invite-ticked-people" disabled="disabled" id="invite-ongoing-im-button" type="button" value="{!INVITE_CURRENT_IM}" />
										<input class="button-screen-item menu--social--chat--chat js-click-btn-im-start-ticked-people" type="button" value="{!START_IM}" />
									{+END}
									{+START,IF_NON_EMPTY,{URL_REMOVE_FRIENDS}}
										<input data-click-pd="1" class="button-screen-item admin--delete3 js-click-btn-dump-friends-confirm" type="submit" value="{!DUMP_FRIENDS}" />
									{+END}
								</div>
							</form>
						{+END}

						{+START,IF_NON_EMPTY,{URL_ADD_FRIEND}}
							<p>{!MUST_ADD_CONTACTS}</p>

							<form class="js-form-submit-add-friend" data-submit-pd="1" title="{!ADD}: {!FRIEND_LIST}" method="post" action="{URL_ADD_FRIEND*}" autocomplete="off">
								{$INSERT_SPAMMER_BLACKHOLE}

								<label class="accessibility-hidden" for="friend_username">{!USERNAME}: </label>
								<input {+START,IF,{$MOBILE}} autocorrect="off"{+END} autocomplete="off" size="18" maxlength="80" class="js-keyup-input-update-ajax-member-list" type="text" placeholder="{!USERNAME}" id="friend_username" name="friend_username" />
								<input class="button-micro admin--add" type="submit" value="{!ADD}" />
							</form>
						{+END}

						<h3 class="chat-lobby-options-header">{!OPTIONS}</h3>

						{CHAT_SOUND}

						<form title="{!SOUND_EFFECTS}" action="index.php" method="post" class="inline sound-effects-form" autocomplete="off">
							{$INSERT_SPAMMER_BLACKHOLE}

							<p>
								<label for="play_sound">{!SOUND_EFFECTS}:</label> <input type="checkbox" id="play_sound" name="play_sound" checked="checked" />
							</p>
						</form>

						<div class="alert-box-wrap" id="alert-box-wrap" style="display: none">
							<section class="box"><div class="box-inner">
								<h3>{!ALERT}</h3>

								<div id="alert-box"></div>
							</div></section>
						</div>
					</div>
				</div>
			</div></div>
		</div>
	{+END}
</div>
