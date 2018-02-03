{+START,IF,{IS_SPACER_POST}}
	{+START,IF,{$NOT,{$IN_STR,{POST},<div}}}
		<div class="box box---post"><div class="box-inner">
			{POST}
		</div></div>
	{+END}

	{+START,IF,{$IN_STR,{POST},<div}}
		{POST}
	{+END}
{+END}

{+START,IF,{$NOT,{IS_SPACER_POST}}}
	<div class="box box---post{+START,IF,{$OR,{IS_UNREAD},{HIGHLIGHT}}} highlighted-post{+END}"><div class="box-inner">
		<div id="post_wrap_{ID*}" class="post time-{TIME_RAW*}" itemprop="reviews" itemscope="itemscope" itemtype="http://schema.org/Review">
			{+START,IF_NON_EMPTY,{ID}}<a id="post_{ID*}"></a>{+END}

			<div class="float-surrounder">
				{+START,IF_NON_EMPTY,{TITLE}}{+START,IF,{$NEQ,{TITLE},{$GET,topic_title}}}<h3 class="post-title" itemprop="name">{TITLE*}</h3>{+END}{+END}

				<div class="post-subline">
					{+START,IF_NON_EMPTY,{POSTER_URL}}{!BY_SIMPLE,<a class="post-poster" href="{POSTER_URL*}">{$DISPLAYED_USERNAME*,{POSTER_NAME}}</a>} {+START,INCLUDE,MEMBER_TOOLTIP}SUBMITTER={POSTER_ID}{+END}{+END}
					{+START,IF_EMPTY,{POSTER_URL}}{!BY_SIMPLE,{POSTER_NAME*}},{+END}

					<span class="post-time">
						{!POSTED_TIME_SIMPLE_LOWER,<time datetime="{$FROM_TIMESTAMP*,Y-m-d\TH:i:s\Z,{TIME_RAW}}" itemprop="datePublished">{TIME*}</time>}
					</span>

					{+START,IF_NON_EMPTY,{EMPHASIS}}
						<span class="post-action-link">({EMPHASIS})</span>
					{+END}

					{+START,IF_NON_EMPTY,{UNVALIDATED}}
						<span class="post-action-link">({UNVALIDATED})</span>
					{+END}

					{+START,LOOP,INDIVIDUAL_REVIEW_RATINGS}
						{+START,IF_PASSED,REVIEW_RATING}
							{+START,SET,REVIEWS}
								{+START,IF_NON_EMPTY,{REVIEW_TITLE}}
									<span class="field-title">{REVIEW_TITLE*}:</span>
								{+END}

								{$SET,rating_loop,0}
								{+START,WHILE,{$LT,{$GET,rating_loop},{$ROUND,{$DIV_FLOAT,{REVIEW_RATING},2}}}}
									<img width="14" height="14" src="{$IMG*,icons/feedback/rating}" alt="{$ROUND,{$DIV_FLOAT,{REVIEW_RATING},2}}" />
									{$INC,rating_loop}
								{+END}

								<span itemprop="reviewRating" itemscope="itemscope" itemtype="http://schema.org/Rating"><meta itemprop="ratingValue" content="{REVIEW_RATING*}" /></span>
							{+END}

							<span class="post-action-link">
								({$GET,REVIEWS})
							</span>
						{+END}
					{+END}

					{+START,IF_PASSED,RATING}
						<span class="post-action-link">{RATING}</span>
					{+END}

					{+START,IF,{$DESKTOP}}
						{+START,IF_NON_EMPTY,{ID}}{+START,IF_NON_PASSED_OR_FALSE,PREVIEWING}{+START,IF,{$MATCH_KEY_MATCH,_SEARCH:topicview}}
							<div id="cell-mark-{ID*}" class="cns-off post-action-link inline-block mobile-inline">
								<form class="webstandards-checker-off" title="{!MARKER} #{ID*}" method="post" action="index.php" id="form-mark-{ID*}" autocomplete="off">
									{$INSERT_SPAMMER_BLACKHOLE}

									<div>
										{+START,IF,{$NOT,{$IS_GUEST}}}<div class="accessibility-hidden"><label for="mark_{ID*}">{!MARKER} #{ID*}</label></div>{+END}{$,Guests don't see this so search engines don't; hopefully people with screen-readers are logged in}
										<input {+START,IF,{$NOT,{$IS_GUEST}}} title="{!MARKER} #{ID*}"{+END} value="1" type="checkbox" id="mark_{ID*}" name="mark_{ID*}" />
									</div>
								</form>
							</div>
						{+END}{+END}{+END}
					{+END}
				</div>

				<div itemprop="reviewBody">
					{POST}
				</div>

				{LAST_EDITED}
			</div>

			{+START,IF_NON_EMPTY,{BUTTONS}}
				<div class="post-buttons buttons-group">
					{BUTTONS}
				</div>
			{+END}

			{+START,IF_PASSED,CHILDREN}
				<div id="post-children-{ID*}" class="post-thread-children">
					{CHILDREN}
				</div>
			{+END}
			{+START,INCLUDE,POST_CHILD_LOAD_LINK}{+END}
		</div>
	</div></div>
{+END}
