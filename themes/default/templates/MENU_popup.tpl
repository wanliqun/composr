{$REQUIRE_CSS,menu__popup}
{$REQUIRE_JAVASCRIPT,menu_popup}

{+START,IF_NON_EMPTY,{CONTENT}}
	<nav class="menu_type__popup" data-view-core-menus="PopupMenu" data-view-args="{+START,PARAMS_JSON,MENU,JAVASCRIPT_HIGHLIGHTING}{_*}{+END}">
		<ul class="nl js-ul-menu-items" id="r_{MENU|*}_p">
			{CONTENT}
		</ul>
	</nav>
{+END}
