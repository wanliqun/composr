{+START,IF_NON_PASSED_OR_FALSE,GET}
	<form title="{!UNINSTALL}: {NAME*}" class="inline top-vertical-alignment" action="{URL*}" method="post" autocomplete="off"><input type="image" width="14" height="14" src="{$IMG*,icons/admin/delete2}" title="{!UNINSTALL}: {NAME*}" alt="{!UNINSTALL}: {NAME*}" />{+START,IF_PASSED,HIDDEN}{$INSERT_SPAMMER_BLACKHOLE}{HIDDEN}{+END}</form>
{+END}
{+START,IF_PASSED_AND_TRUE,GET}
	<a{+START,IF_PASSED_AND_TRUE,CONFIRM} data-cms-confirm-click="{!Q_SURE*}"{+END} class="link-exempt vertical-alignment" href="{URL*}"><img width="14" height="14" src="{$IMG*,icons/admin/delete2}" title="{!UNINSTALL}: {NAME*}" alt="{!UNINSTALL}: {NAME*}" /></a>
{+END}