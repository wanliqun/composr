<input data-cms-invalid-pattern="[^\w\.\-{+START,LOOP,EXTRA_CHARS}\{_loop_var*}{+END}]" size="16"{+START,IF_PASSED,MAXLENGTH} maxlength="{MAXLENGTH*}"{+END}{+START,IF_NON_PASSED,MAXLENGTH} maxlength="80"{+END} tabindex="{TABINDEX*}" class="input_{$?,{$IS_EMPTY,{EXTRA_CHARS}},codename,line}{REQUIRED*}" type="text" id="{NAME*}" name="{NAME*}" value="{DEFAULT*}"{+START,IF_PASSED,PLACEHOLDER} placeholder="{PLACEHOLDER*}"{+END} />
